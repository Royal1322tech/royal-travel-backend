<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkDestinationService;
use App\Services\Hotels\RateHawkHotelContentService;
use App\Services\Hotels\RateHawkRateNormalizer;
use App\Services\Hotels\RateHawkSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class HotelSearchController extends Controller
{
    private const OCCUPANCY = [
        '1-2' => ['rooms' => 1, 'adults_per_room' => 2],
        '2-4' => ['rooms' => 2, 'adults_per_room' => 2],
        '3-6' => ['rooms' => 3, 'adults_per_room' => 2],
    ];

    // These are hotel-level content amenities, not room-level amenities.
    private const AMENITY_FILTERS = [
        'non_smoking' => ['non-smoking rooms', 'non smoking rooms', 'non-smoking', 'smoke-free property'],
        'room_service' => ['room service'],
        'air_conditioning' => ['air conditioning', 'air-conditioning', 'air conditioner'],
        'bathroom' => ['bathroom', 'private bathroom'],
        'gym' => ['gym', 'fitness centre', 'fitness center', 'fitness facilities'],
        'heating' => ['heating'],
    ];

    public function index(
        Request $request,
        RateHawkSearchService $searchService,
        RateHawkHotelContentService $contentService,
        RateHawkRateNormalizer $rateNormalizer,
        RateHawkDestinationService $destinations
    ) {
        $hotels = [];
        $searchPerformed = false;
        $total = 0;
        $searchError = null;
        $filteredTotal = 0;
        $contentUnavailable = 0;
        $contentChecked = 0;

        if (! $request->has('search')) {
            return view('admin.hotels.index', compact(
                'hotels', 'searchPerformed', 'total', 'searchError', 'filteredTotal', 'contentUnavailable', 'contentChecked'
            ));
        }

        $validated = $request->validate([
            'search' => ['required', 'in:1'],
            // Legacy slug (dubai, cairo, riyadh, ...) or "region:<id>" from the destinations API.
            'destination' => ['required', 'string', 'max:40', 'regex:' . RateHawkDestinationService::TOKEN_PATTERN,
                function (string $attribute, mixed $value, \Closure $fail) use ($destinations): void {
                    if (!is_string($value) || $destinations->resolve($value) === null) {
                        $fail('The selected destination is not supported.');
                    }
                }],
            'occupancy' => ['required', Rule::in(array_keys(self::OCCUPANCY))],
            'checkin' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'checkout' => ['required', 'date_format:Y-m-d', 'after:checkin'],
            'residency' => ['required', Rule::in(['eg', 'sa', 'ae', 'gb', 'us'])],
            'board' => ['sometimes', 'array', 'max:6'],
            'board.*' => ['string', Rule::in(['nomeal', 'breakfast', 'half-board', 'full-board', 'all-inclusive', 'ultra-all-inclusive'])],
            'package_type' => ['sometimes', Rule::in(['all', 'with_package', 'without_package'])],
            'hotel_name' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'stars' => ['sometimes', 'array', 'max:5'],
            'stars.*' => ['integer', Rule::in([1, 2, 3, 4, 5])],
            'amenities' => ['sometimes', 'array', 'max:6'],
            'amenities.*' => ['string', Rule::in(array_keys(self::AMENITY_FILTERS))],
            'sort' => ['sometimes', Rule::in(['recommended', 'stars_desc', 'name_asc'])],
        ]);

        $searchPerformed = true;
        $destination = $destinations->resolve($validated['destination']);
        $occupancy = self::OCCUPANCY[$validated['occupancy']];
        $guests = [];

        for ($room = 0; $room < $occupancy['rooms']; $room++) {
            $guests[] = [
                'adults' => $occupancy['adults_per_room'],
                'children' => [],
            ];
        }

        try {
            $result = $destination['mode'] === 'region'
                ? $searchService->searchByRegion([
                    'checkin' => $validated['checkin'],
                    'checkout' => $validated['checkout'],
                    'region_id' => $destination['region_id'],
                    'residency' => $validated['residency'],
                    'currency' => 'USD',
                    'language' => 'en',
                    'guests' => $guests,
                ])
                : $searchService->searchByGeo([
                    'checkin' => $validated['checkin'],
                    'checkout' => $validated['checkout'],
                    'latitude' => $destination['latitude'],
                    'longitude' => $destination['longitude'],
                    'radius' => $destination['radius'],
                    'residency' => $validated['residency'],
                    'currency' => 'USD',
                    'language' => 'en',
                    'guests' => $guests,
                ]);

            $supplierHotels = $result['hotels'] ?? [];
            $total = $result['total'] ?? count($supplierHotels);

            $selectedBoards = array_values(array_unique($validated['board'] ?? []));
            $selectedPackage = $validated['package_type'] ?? 'all';
            $selectedStars = array_map('intval', $validated['stars'] ?? []);
            $selectedAmenities = array_values(array_unique($validated['amenities'] ?? []));
            $nameQuery = trim($validated['hotel_name'] ?? '');
            $areaQuery = trim($validated['area'] ?? '');
            $sort = $validated['sort'] ?? 'recommended';
            $needsAllContent = $nameQuery !== '' || $areaQuery !== ''
                || $selectedStars !== [] || $selectedAmenities !== []
                || $sort !== 'recommended';

            // Build a view-safe hotel list. Do not pass raw supplier rates to Blade.
            foreach ($supplierHotels as $supplierHotel) {
                $hotel = [
                    'id' => $supplierHotel['id'] ?? null,
                    'hid' => $supplierHotel['hid'] ?? null,
                    'room_previews' => [],
                    'filter_data' => [
                        'boards' => [],
                        'package_types' => [],
                        'rate_combinations' => [],
                    ],
                ];

                $rates = $supplierHotel['rates'] ?? [];
                if (is_array($rates)) {
                    // Collect filter facets from ALL rates, not just the three previews.
                    foreach ($rates as $rate) {
                        if (! is_array($rate)) {
                            continue;
                        }

                        $meal = self::canonicalBoard((string) ($rate['meal'] ?? ''));
                        if ($meal !== '') {
                            $hotel['filter_data']['boards'][$meal] = true;
                        }

                        $packageType = null;
                        if (isset($rate['is_package']) && is_bool($rate['is_package'])) {
                            $packageType = $rate['is_package'] ? 'with_package' : 'without_package';
                            $hotel['filter_data']['package_types'][$packageType] = true;
                        }
                        $hotel['filter_data']['rate_combinations'][] = [
                            'board' => $meal,
                            'package_type' => $packageType,
                        ];
                    }

                    $hotel['filter_data']['boards'] = array_keys($hotel['filter_data']['boards']);
                    $hotel['filter_data']['package_types'] = array_keys($hotel['filter_data']['package_types']);

                    foreach ($rates as $rate) {
                        if (! is_array($rate)) {
                            continue;
                        }

                        $rateBoard = self::canonicalBoard((string) ($rate['meal'] ?? ''));
                        $ratePackage = isset($rate['is_package']) && is_bool($rate['is_package'])
                            ? ($rate['is_package'] ? 'with_package' : 'without_package')
                            : null;
                        if ($selectedBoards !== [] && !in_array($rateBoard, $selectedBoards, true)) {
                            continue;
                        }
                        if ($selectedPackage !== 'all' && $ratePackage !== $selectedPackage) {
                            continue;
                        }
                        if (count($hotel['room_previews']) >= 3) {
                            break;
                        }

                        try {
                            $hotel['room_previews'][] = $rateNormalizer->publicPreview(
                                $rateNormalizer->normalize($rate)
                            );
                        } catch (Throwable $exception) {
                            Log::warning('RateHawk rate preview unavailable', [
                                'hid' => $supplierHotel['hid'] ?? null,
                                'exception' => get_class($exception),
                            ]);
                        }
                    }
                }

                $hotels[] = $hotel;
            }

            // Apply filters across ALL SERP hotels before selecting five for content enrichment.
            // Multiple board choices use OR; board and package conditions use AND.
            if ($selectedBoards !== [] || $selectedPackage !== 'all') {
                $hotels = array_values(array_filter($hotels, static function (array $hotel) use ($selectedBoards, $selectedPackage): bool {
                    $facets = $hotel['filter_data'];
                    foreach ($facets['rate_combinations'] as $combination) {
                        $boardMatches = $selectedBoards === [] || in_array($combination['board'], $selectedBoards, true);
                        $packageMatches = $selectedPackage === 'all' || $combination['package_type'] === $selectedPackage;
                        if ($boardMatches && $packageMatches) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
            // Content-dependent filters must inspect every candidate hotel, not just five.
            // First unfiltered searches retain the previous five-hotel enrichment behavior.
            $enrichmentLimit = $needsAllContent ? count($hotels) : min(5, count($hotels));
            for ($index = 0; $index < $enrichmentLimit; $index++) {
                $hid = $hotels[$index]['hid'] ?? null;
                if (! $hid) {
                    $contentUnavailable++;
                    continue;
                }
                try {
                    $content = $contentService->getHotel($hid, 'en');
                    $contentChecked++;
                    $hotels[$index]['name'] = $content['name'] ?? null;
                    $hotels[$index]['location'] = $content['address'] ?? null;
                    $hotels[$index]['stars'] = $content['star_rating'] ?? null;
                    $image = $content['images'][0] ?? ($content['images_ext'][0]['url'] ?? null);
                    if (is_string($image)) {
                        $hotels[$index]['image'] = str_replace('{size}', '640x400', $image);
                    }
                    $amenities = [];
                    foreach (($content['amenity_groups'] ?? []) as $group) {
                        foreach (($group['amenities'] ?? []) as $amenity) {
                            if (is_string($amenity)) {
                                $amenities[] = $amenity;
                            }
                        }
                    }
                    $hotels[$index]['amenities'] = array_slice(array_values(array_unique($amenities)), 0, 5);
                    $hotels[$index]['filter_data']['stars'] = is_numeric($content['star_rating'] ?? null)
                        ? (int) $content['star_rating'] : null;
                    $hotels[$index]['filter_data']['location'] = $content['address'] ?? null;
                    $hotels[$index]['filter_data']['amenities'] = $amenities;
                } catch (Throwable $exception) {
                    $contentUnavailable++;
                    Log::warning('RateHawk hotel content unavailable', [
                        'hid' => $hid,
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            if ($needsAllContent) {
                $hotels = array_values(array_filter($hotels, static function (array $hotel) use (
                    $nameQuery, $areaQuery, $selectedStars, $selectedAmenities
                ): bool {
                    $facets = $hotel['filter_data'];
                    if ($nameQuery !== '' && ! str_contains(mb_strtolower((string) ($hotel['name'] ?? '')), mb_strtolower($nameQuery))) {
                        return false;
                    }
                    if ($areaQuery !== '' && ! str_contains(mb_strtolower((string) ($facets['location'] ?? '')), mb_strtolower($areaQuery))) {
                        return false;
                    }
                    if ($selectedStars !== [] && ! in_array($facets['stars'] ?? null, $selectedStars, true)) {
                        return false;
                    }
                    // Selected amenities use AND; each must be found in real supplier hotel content.
                    $actualAmenities = array_map(static fn ($a) => mb_strtolower(trim((string) $a)), $facets['amenities'] ?? []);
                    foreach ($selectedAmenities as $selected) {
                        $aliases = self::AMENITY_FILTERS[$selected];
                        $found = false;
                        foreach ($actualAmenities as $actual) {
                            foreach ($aliases as $alias) {
                                if ($actual === $alias) {
                                    $found = true;
                                    break 2;
                                }
                            }
                        }
                        if (! $found) {
                            return false;
                        }
                    }
                    return true;
                }));
                if ($sort === 'stars_desc') {
                    usort($hotels, static fn ($a, $b) => (($b['stars'] ?? -1) <=> ($a['stars'] ?? -1)));
                } elseif ($sort === 'name_asc') {
                    usort($hotels, static function ($a, $b): int {
                        if (empty($a['name'])) return 1;
                        if (empty($b['name'])) return -1;
                        return strcasecmp($a['name'], $b['name']);
                    });
                }
            }
            $filteredTotal = count($hotels);

            // Short-lived, session-bound server-side snapshots. No supplier rate data in HTML.
            // Only keep rates matching the selected board + package filters.
            $rateIndex = [];
            foreach ($supplierHotels as $supplierHotel) {
                $key = (string) ($supplierHotel['hid'] ?? '');
                if ($key !== '') {
                    $rateIndex[$key] = $supplierHotel['rates'] ?? [];
                }
            }
            foreach ($hotels as &$hotel) {
                $hid = (string) ($hotel['hid'] ?? '');
                if ($hid === '' || ! isset($rateIndex[$hid])) {
                    continue;
                }
                $roomRates = [];
                foreach ($rateIndex[$hid] as $rate) {
                    if (! is_array($rate)) continue;
                    $board = self::canonicalBoard((string) ($rate['meal'] ?? ''));
                    $package = isset($rate['is_package']) && is_bool($rate['is_package'])
                        ? ($rate['is_package'] ? 'with_package' : 'without_package') : null;
                    if ($selectedBoards !== [] && ! in_array($board, $selectedBoards, true)) continue;
                    if ($selectedPackage !== 'all' && $package !== $selectedPackage) continue;
                    try {
                        $roomRates[] = $rateNormalizer->normalize($rate);
                    } catch (Throwable $exception) {
                        Log::warning('RateHawk room normalization unavailable', ['hid' => $hid]);
                    }
                }
                if ($roomRates === []) continue;
                $token = Str::random(48);

Cache::put(
    'admin_hotel_rooms:' . hash('sha256', $token),
    [
        'session' => hash(
            'sha256',
            $request->session()->getId()
        ),

        'environment' => $result['environment'] ?? config(
            'services.ratehawk.environment',
            'sandbox'
        ),

        'hid' => (int) $hotel['hid'],

        'hotel' => [
            'name' => $hotel['name']
                ?? $hotel['id']
                ?? 'Hotel',
            'location' => $hotel['location'] ?? null,
            'stars' => $hotel['stars'] ?? null,
            'image' => $hotel['image'] ?? null,
        ],

        'search' => [
            'checkin' => $validated['checkin'],
            'checkout' => $validated['checkout'],
            'occupancy' => $validated['occupancy'],
            'residency' => $validated['residency'],
            'language' => 'en',
            'currency' => 'USD',
            'guests' => $guests,
        ],

        // SERP rates are previews only.
        // Hotelpage rates will replace them after
        // the selected hotel's Hotelpage request.
        'rates_source' => 'serp',
        'rates' => $roomRates,
    ],
    now()->addMinutes(15)
);

                $hotel['rooms_token'] = $token;
            }
            unset($hotel);
        } catch (Throwable $exception) {
            Log::error('Admin RateHawk hotel search failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            $searchError = 'Unable to retrieve hotels from RateHawk Sandbox. Please try again.';
        }

        return view('admin.hotels.index', compact(
            'hotels', 'searchPerformed', 'total', 'searchError', 'filteredTotal', 'contentUnavailable', 'contentChecked'
        ));
    }
    private static function canonicalBoard(string $value): string
    {
        $value = strtolower(trim($value));
        $compact = str_replace(['_', '-', ' '], '', $value);

        return match ($compact) {
            'nomeal', 'roomonly', 'ro' => 'nomeal',
            'breakfast', 'bedandbreakfast', 'bb' => 'breakfast',
            'halfboard', 'hb' => 'half-board',
            'fullboard', 'fb' => 'full-board',
            'allinclusive', 'ai' => 'all-inclusive',
            'ultraallinclusive', 'uai' => 'ultra-all-inclusive',
            default => $value,
        };
    }

}
