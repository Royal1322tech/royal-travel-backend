<?php

namespace App\Services\Hotels;

use App\Services\Suppliers\SupplierEntitlementService;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class HotelSearchApplicationService
{
    private const AMENITIES = [
        'non_smoking' => ['non-smoking rooms', 'non smoking rooms', 'non-smoking', 'smoke-free property'],
        'room_service' => ['room service'],
        'air_conditioning' => ['air conditioning', 'air-conditioning', 'air conditioner'],
        'bathroom' => ['bathroom', 'private bathroom'],
        'gym' => ['gym', 'fitness centre', 'fitness center', 'fitness facilities'],
        'heating' => ['heating'],
    ];

    public function __construct(
        private readonly SupplierEntitlementService $entitlements,
        private readonly RateHawkSearchService $rateHawk,
        private readonly RateHawkRateNormalizer $normalizer,
        private readonly RateHawkHotelContentService $content,
        private readonly RateHawkPublicHotelProjector $projector,
        private readonly RateHawkDestinationService $destinations,
    ) {}

    public function search(array $params, string $channel, ?string $companyId = null): array
    {
        if (!in_array($channel, ['b2b', 'b2c'], true)) {
            throw new InvalidArgumentException('Unsupported channel.');
        }
        if ($channel === 'b2b' && (!is_string($companyId) || !Str::isUuid($companyId))) {
            throw new InvalidArgumentException('Authenticated company is required.');
        }

        $displayCurrency = strtoupper(trim((string) ($params['currency'] ?? 'USD')));
        if (!in_array($displayCurrency, ['USD', 'EGP', 'SAR'], true)) {
            throw new InvalidArgumentException('Unsupported display currency.');
        }
        if (!$this->entitlements->isAllowed('RATEHAWK', $channel, 'hotel')) {
            return $this->failure('HOTEL_SUPPLIER_UNAVAILABLE', 'Hotel search is currently unavailable.');
        }

        // A destination token (region:<id> or legacy slug) takes precedence over
        // raw coordinates; it is validated by the request before reaching here.
        $location = null;
        if (isset($params['destination']) && is_string($params['destination'])) {
            $location = $this->destinations->resolve($params['destination']);
            if ($location === null) {
                return $this->failure('DESTINATION_NOT_FOUND', 'Destination is not supported.');
            }
        }

        try {
            $supplierParams = array_merge($params, ['currency' => 'USD']);
            unset($supplierParams['destination']);
            if ($location !== null && $location['mode'] === 'region') {
                $supplierResult = $this->rateHawk->searchByRegion(
                    array_merge($supplierParams, ['region_id' => $location['region_id']])
                );
            } else {
                if ($location !== null) {
                    $supplierParams['latitude'] = $location['latitude'];
                    $supplierParams['longitude'] = $location['longitude'];
                    $supplierParams['radius'] = $supplierParams['radius'] ?? $location['radius'];
                }
                $supplierResult = $this->rateHawk->searchByGeo($supplierParams);
            }
        } catch (Throwable $exception) {
            report($exception);
            return $this->failure('HOTEL_SEARCH_NOT_READY', 'Hotel search is temporarily unavailable.');
        }

        $supplierHotels = array_values(array_filter($supplierResult['hotels'] ?? [], 'is_array'));
        $filters = $params['filters'] ?? [];
        $boards = array_values(array_unique($filters['board'] ?? []));
        $package = $filters['package_type'] ?? 'all';
        $stars = array_map('intval', $filters['stars'] ?? []);
        $amenities = array_values(array_unique($filters['amenities'] ?? []));
        $name = mb_strtolower(trim((string) ($filters['hotel_name'] ?? '')));
        $area = mb_strtolower(trim((string) ($filters['area'] ?? '')));
        $sort = $params['sort'] ?? 'recommended';
        $needsContent = $name !== '' || $area !== '' || $stars !== [] || $amenities !== [] || $sort !== 'recommended';

        // Filter supplier rates before pagination. A board/package combination must
        // exist on the SAME rate; independent hotel-level facets are insufficient.
        $candidates = [];
        foreach ($supplierHotels as $hotel) {
            $matchingRates = [];
            foreach (($hotel['rates'] ?? []) as $rate) {
                if (!is_array($rate)) {
                    continue;
                }
                $rateBoard = self::canonicalBoard((string) ($rate['meal'] ?? ''));
                $ratePackage = isset($rate['is_package']) && is_bool($rate['is_package'])
                    ? ($rate['is_package'] ? 'with_package' : 'without_package') : null;
                if ($boards !== [] && !in_array($rateBoard, $boards, true)) {
                    continue;
                }
                if ($package !== 'all' && $ratePackage !== $package) {
                    continue;
                }
                $matchingRates[] = $rate;
            }
            if ($matchingRates === []) {
                continue;
            }
            $hotel['rates'] = $matchingRates;
            $candidates[] = ['hotel' => $hotel, 'content' => []];
        }

        // Content-dependent filters must inspect every candidate, not just the page.
        // This may be expensive on large searches; content service caching is important.
        if ($needsContent) {
            // Stars and amenities don't depend on the language, so they are checked against one
            // shared (English) content cache: the same search gives the same hotels in every
            // language. Names and addresses are matched, and sorted, in the requested language.
            $contentLanguage = ($name !== '' || $area !== '' || $sort === 'name_asc')
                ? (string) ($params['language'] ?? 'en')
                : 'en';
            $filtered = [];
            $unavailable = 0;
            foreach ($candidates as $candidate) {
                $hid = $candidate['hotel']['hid'] ?? null;
                if (!self::validHid($hid)) {
                    continue;
                }
                try {
                    $content = $this->content->getHotel($hid, $contentLanguage);
                } catch (Throwable $exception) {
                    report($exception);
                    $content = null;
                }
                if (!is_array($content)) {
                    // Never drop a hotel silently: a partial filtered result would show a wrong total.
                    $unavailable++;
                    continue;
                }
                if ($name !== '' && !str_contains(mb_strtolower((string) ($content['name'] ?? '')), $name)) {
                    continue;
                }
                if ($area !== '' && !str_contains(mb_strtolower((string) ($content['address'] ?? '')), $area)) {
                    continue;
                }
                $hotelStars = is_numeric($content['star_rating'] ?? null)
                    ? (float) $content['star_rating'] : null;
                if ($stars !== [] && ($hotelStars === null || !in_array($hotelStars, $stars, false))) {
                    continue;
                }
                if ($amenities !== [] && !self::matchesAmenities($content, $amenities)) {
                    continue;
                }
                $candidate['content'] = $content;
                $filtered[] = $candidate;
            }
            if ($unavailable > 0) {
                return $this->failure(
                    'HOTEL_SEARCH_NOT_READY',
                    'Hotel details are still loading. Please try again shortly.'
                );
            }
            $candidates = $filtered;
            if ($sort === 'stars_desc') {
                usort($candidates, static fn (array $a, array $b): int =>
                    ((float) ($b['content']['star_rating'] ?? -1)) <=> ((float) ($a['content']['star_rating'] ?? -1)));
            } elseif ($sort === 'name_asc') {
                usort($candidates, static fn (array $a, array $b): int =>
                    strcasecmp((string) ($a['content']['name'] ?? ''), (string) ($b['content']['name'] ?? '')));
            }
        }

        $total = count($candidates);
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(20, max(1, (int) ($params['per_page'] ?? 20)));
        $start = ($page - 1) * $perPage;
        $pageCandidates = array_slice($candidates, $start, $perPage);
        $hotels = [];

        foreach ($pageCandidates as $offset => $candidate) {
            $hotel = $candidate['hotel'];
            $rooms = [];
            foreach (($hotel['rates'] ?? []) as $rate) {
                try {
                    $rooms[] = $this->normalizer->publicPreview($this->normalizer->normalize($rate));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $content = $candidate['content'];
            $hid = $hotel['hid'] ?? null;
            // Preserve the previous maximum of five content enrichments per page
            // when no content-dependent filter or sort was requested.
            $displayLanguage = (string) ($params['language'] ?? 'en');
            // Filtered on the shared English details: show this page's hotels in the requested language.
            $relocalize = $needsContent && isset($contentLanguage) && $contentLanguage !== $displayLanguage;
            if (((!$needsContent && $offset < 5) || $relocalize) && self::validHid($hid)) {
                try {
                    $loaded = $this->content->getHotel($hid, $displayLanguage);
                    $content = is_array($loaded) ? $loaded : $content;
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
            $preview = $this->projector->preview($start + $offset, $rooms, $content);
            $preview['hid'] = self::validHid($hid) ? (int) $hid : null;
            $hotels[] = $preview;
        }

        return [
            'success' => true,
            'code' => 'HOTEL_SEARCH_PREVIEW',
            'message' => 'Hotel availability preview. Prices are not yet verified.',
            'data' => [
                'hotels' => $hotels,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'currency' => $displayCurrency,
                'supplier_currency' => 'USD',
                'pricing_verified' => false,
                'booking_enabled' => false,
            ],
        ];
    }

    private static function validHid(mixed $hid): bool
    {
        return (is_int($hid) || is_string($hid))
            && ctype_digit((string) $hid)
            && (int) $hid > 0;
    }

    private static function canonicalBoard(string $value): string
    {
        $value = strtolower(trim($value));
        return match (str_replace(['_', '-', ' '], '', $value)) {
            'nomeal', 'roomonly', 'ro' => 'nomeal',
            'breakfast', 'bedandbreakfast', 'bb' => 'breakfast',
            'halfboard', 'hb' => 'half-board',
            'fullboard', 'fb' => 'full-board',
            'allinclusive', 'ai' => 'all-inclusive',
            'ultraallinclusive', 'uai' => 'ultra-all-inclusive',
            default => $value,
        };
    }

    private static function matchesAmenities(array $content, array $selected): bool
    {
        $actual = [];
        foreach (($content['amenity_groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach (($group['amenities'] ?? []) as $amenity) {
                if (is_string($amenity)) {
                    $actual[] = mb_strtolower(trim($amenity));
                }
            }
        }
        foreach ($selected as $code) {
            $aliases = self::AMENITIES[$code] ?? [];
            $found = false;
            foreach ($aliases as $alias) {
                if (in_array($alias, $actual, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    private function failure(string $code, string $message): array
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => ['hotels' => [], 'total' => 0],
        ];
    }
}
