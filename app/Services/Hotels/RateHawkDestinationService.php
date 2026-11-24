<?php

declare(strict_types=1);

namespace App\Services\Hotels;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Locale;
use Throwable;

/**
 * Worldwide destination autocomplete and destination-token resolution shared by
 * the B2B API and the Admin dashboard.
 *
 * Supplier source: RateHawk "Suggest hotel and region"
 * (POST /api/b2b/v3/search/multicomplete/, body {query, language}), whose
 * data.regions[] items are {id:int, name, type, country_code}. Region ids are
 * searched with POST /api/b2b/v3/search/serp/region/ (see RateHawkSearchService).
 *
 * Destination tokens accepted by hotel search:
 *   - "region:<RateHawk region id>"  -> serp/region search
 *   - legacy slug (e.g. "dubai")      -> serp/geo search at the existing coordinates
 */
final class RateHawkDestinationService
{
    public const MIN_QUERY = 2;
    public const MAX_QUERY = 100;
    public const MAX_LIMIT = 20;
    public const DEFAULT_LIMIT = 10;
    public const LANGUAGES = ['en', 'ar'];
    public const TOKEN_PATTERN = '/^(?:region:[1-9][0-9]{0,11}|[a-z][a-z0-9_-]{1,39})$/D';

    private const CACHE_PREFIX = 'ratehawk:destinations:v1:';
    private const CACHE_TTL_SECONDS = 86400;      // successful non-empty supplier results
    private const EMPTY_CACHE_TTL_SECONDS = 3600; // empty results
    private const FAILURE_CACHE_TTL_SECONDS = 60; // short back-off after supplier failure
    private const SUPPLIER_TIMEOUT_SECONDS = 10;

    /**
     * Legacy destinations used by the existing B2B frontend (?destination=<slug>)
     * and the Admin dashboard. Coordinates are the ones already in use; radius in metres.
     * "sandbox" marks destinations documented to return inventory in the RateHawk sandbox.
     */
    private const LEGACY = [
        'alexandria' => ['name' => 'Alexandria', 'name_ar' => 'الإسكندرية', 'country_code' => 'EG', 'latitude' => 31.2001, 'longitude' => 29.9187, 'sandbox' => false],
        'cairo' => ['name' => 'Cairo', 'name_ar' => 'القاهرة', 'country_code' => 'EG', 'latitude' => 30.0444, 'longitude' => 31.2357, 'sandbox' => false],
        'hurghada' => ['name' => 'Hurghada', 'name_ar' => 'الغردقة', 'country_code' => 'EG', 'latitude' => 27.2579, 'longitude' => 33.8116, 'sandbox' => false],
        'sharm-el-sheikh' => ['name' => 'Sharm El Sheikh', 'name_ar' => 'شرم الشيخ', 'country_code' => 'EG', 'latitude' => 27.9158, 'longitude' => 34.3299, 'sandbox' => false],
        'dubai' => ['name' => 'Dubai', 'name_ar' => 'دبي', 'country_code' => 'AE', 'latitude' => 25.2048, 'longitude' => 55.2708, 'sandbox' => true],
        'paris' => ['name' => 'Paris', 'name_ar' => 'باريس', 'country_code' => 'FR', 'latitude' => 48.8566, 'longitude' => 2.3522, 'sandbox' => true],
        'los-angeles' => ['name' => 'Los Angeles', 'name_ar' => 'لوس أنجلوس', 'country_code' => 'US', 'latitude' => 34.0522, 'longitude' => -118.2437, 'sandbox' => true],
        'riyadh' => ['name' => 'Riyadh', 'name_ar' => 'الرياض', 'country_code' => 'SA', 'latitude' => 24.7136, 'longitude' => 46.6753, 'sandbox' => false],
    ];

    private const LEGACY_ALIASES = [
        'sharm_el_sheikh' => 'sharm-el-sheikh',
        'sharmelsheikh' => 'sharm-el-sheikh',
        'sharm' => 'sharm-el-sheikh',
        'los_angeles' => 'los-angeles',
        'losangeles' => 'los-angeles',
        'la' => 'los-angeles',
    ];

    private const LEGACY_RADIUS_METRES = 10000;

    public function __construct(private readonly RateHawkClient $client)
    {
    }

    public static function scope(): string
    {
        $scope = config('services.ratehawk.destination_scope', 'all');

        return $scope === 'sandbox_known' ? 'sandbox_known' : 'all';
    }

    /**
     * @return array{success: bool, code: string, scope: string, destinations: list<array>}
     */
    public function autocomplete(string $query, string $language = 'en', int $limit = self::DEFAULT_LIMIT): array
    {
        $query = $this->normalizeQuery($query);
        if (mb_strlen($query) < self::MIN_QUERY || mb_strlen($query) > self::MAX_QUERY) {
            throw new InvalidArgumentException('Query must be between 2 and 100 characters.');
        }
        if (!in_array($language, self::LANGUAGES, true)) {
            throw new InvalidArgumentException('Unsupported language.');
        }
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $scope = self::scope();

        if ($scope === 'sandbox_known') {
            return $this->result(true, 'OK', $scope, array_slice($this->matchLegacy($query, $language, true), 0, $limit));
        }

        $environment = (string) config('services.ratehawk.environment', 'sandbox');
        $key = self::CACHE_PREFIX . $environment . ':' . $language . ':' . hash('sha256', mb_strtolower($query));

        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['code'], $cached['destinations'])) {
            return $this->result($cached['code'] === 'OK', $cached['code'], $scope,
                array_slice($cached['destinations'], 0, $limit));
        }

        try {
            $destinations = $this->fetchRegions($query, $language);
        } catch (Throwable $exception) {
            // Never log the query payload headers or credentials; class name only.
            Log::warning('RateHawk destination autocomplete unavailable', [
                'exception' => get_class($exception),
            ]);
            Cache::put($key, ['code' => 'DESTINATION_SEARCH_UNAVAILABLE', 'destinations' => []],
                self::FAILURE_CACHE_TTL_SECONDS);

            return $this->result(false, 'DESTINATION_SEARCH_UNAVAILABLE', $scope, []);
        }

        Cache::put($key, ['code' => 'OK', 'destinations' => $destinations],
            $destinations === [] ? self::EMPTY_CACHE_TTL_SECONDS : self::CACHE_TTL_SECONDS);

        return $this->result(true, 'OK', $scope, array_slice($destinations, 0, $limit));
    }

    /**
     * Resolve a destination token into supplier search location parameters.
     *
     * @return array{mode: 'region', region_id: int}|array{mode: 'geo', latitude: float, longitude: float, radius: int}|null
     */
    public function resolve(string $token): ?array
    {
        $token = strtolower(trim($token));
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }
        if (str_starts_with($token, 'region:')) {
            return ['mode' => 'region', 'region_id' => (int) substr($token, 7)];
        }
        $slug = self::LEGACY_ALIASES[$token] ?? $token;
        $legacy = self::LEGACY[$slug] ?? null;
        if ($legacy === null) {
            return null;
        }

        return [
            'mode' => 'geo',
            'latitude' => $legacy['latitude'],
            'longitude' => $legacy['longitude'],
            'radius' => self::LEGACY_RADIUS_METRES,
        ];
    }

    /** @return list<string> legacy slugs (canonical form) */
    public static function legacySlugs(): array
    {
        return array_keys(self::LEGACY);
    }

    private function fetchRegions(string $query, string $language): array
    {
        $response = $this->client->post('/api/b2b/v3/search/multicomplete/', [
            'query' => $query,
            'language' => $language,
        ], self::SUPPLIER_TIMEOUT_SECONDS);

        if (($response['status'] ?? null) !== 'ok') {
            throw new \RuntimeException('RateHawk multicomplete was not successful.');
        }

        // Documented: data.regions may be null (always null in the sandbox).
        $regions = $response['data']['regions'] ?? [];
        if ($regions === null) {
            return [];
        }
        if (!is_array($regions)) {
            throw new \RuntimeException('Unexpected RateHawk regions response.');
        }

        $destinations = [];
        $seen = [];
        foreach ($regions as $region) {
            $mapped = is_array($region) ? $this->mapRegion($region, $language) : null;
            if ($mapped !== null && !isset($seen[$mapped['id']])) {
                $seen[$mapped['id']] = true;
                $destinations[] = $mapped;
            }
        }

        return $destinations;
    }

    private function mapRegion(array $region, string $language): ?array
    {
        $id = $region['id'] ?? null;
        $name = $region['name'] ?? null;
        $type = $region['type'] ?? null;
        $countryCode = $region['country_code'] ?? null;

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }
        $id = (int) $id;
        if ($id < 1 || $id > 999999999999 || !is_string($name) || trim($name) === '' || !is_string($type)) {
            return null;
        }
        // Regions that cannot be searched as a hotel destination.
        if (in_array($type, ['Continent'], true)) {
            return null;
        }
        $countryCode = is_string($countryCode) && preg_match('/^[A-Za-z]{2}$/D', $countryCode) === 1
            ? strtoupper($countryCode)
            : null;

        return [
            'id' => 'region:' . $id,
            'type' => $this->normalizeType($type),
            'name' => mb_substr(trim($name), 0, 200),
            'country' => $countryCode !== null ? $this->countryName($countryCode, $language) : null,
            'country_code' => $countryCode,
            'parent' => null,
        ];
    }

    /** Documented RateHawk region types collapsed into a small stable set. */
    private function normalizeType(string $type): string
    {
        return match ($type) {
            'Country' => 'country',
            'City' => 'city',
            'Airport' => 'airport',
            'Point of Interest' => 'point_of_interest',
            'Neighborhood' => 'neighborhood',
            default => 'region',
        };
    }

    private function countryName(string $countryCode, string $language): ?string
    {
        if (!class_exists(Locale::class)) {
            return null;
        }
        $name = Locale::getDisplayRegion('-' . $countryCode, $language);

        return is_string($name) && $name !== '' && $name !== $countryCode ? $name : null;
    }

    private function matchLegacy(string $query, string $language, bool $sandboxOnly): array
    {
        $needle = mb_strtolower($query);
        $matches = [];
        foreach (self::LEGACY as $slug => $destination) {
            if ($sandboxOnly && !$destination['sandbox']) {
                continue;
            }
            $haystacks = [mb_strtolower($destination['name']), $destination['name_ar'], $slug];
            $hit = false;
            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $needle)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
            $matches[] = [
                'id' => $slug,
                'type' => 'city',
                'name' => $language === 'ar' ? $destination['name_ar'] : $destination['name'],
                'country' => $this->countryName($destination['country_code'], $language),
                'country_code' => $destination['country_code'],
                'parent' => null,
            ];
        }

        return $matches;
    }

    private function normalizeQuery(string $query): string
    {
        $query = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $query) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $query) ?? '');
    }

    private function result(bool $success, string $code, string $scope, array $destinations): array
    {
        return [
            'success' => $success,
            'code' => $code,
            'scope' => $scope,
            'destinations' => array_values($destinations),
        ];
    }
}
