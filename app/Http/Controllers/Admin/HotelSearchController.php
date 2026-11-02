<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkHotelContentService;
use App\Services\Hotels\RateHawkSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class HotelSearchController extends Controller
{
    private const DESTINATIONS = [
        'dubai' => [
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ],
        'cairo' => [
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ],
        'riyadh' => [
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ],
    ];

    private const OCCUPANCY = [
        '1-2' => ['rooms' => 1, 'adults_per_room' => 2],
        '2-4' => ['rooms' => 2, 'adults_per_room' => 2],
        '3-6' => ['rooms' => 3, 'adults_per_room' => 2],
    ];

    public function index(
        Request $request,
        RateHawkSearchService $searchService,
        RateHawkHotelContentService $contentService
    ) {
        $hotels = [];
        $searchPerformed = false;
        $total = 0;
        $searchError = null;

        if (! $request->has('search')) {
            return view('admin.hotels.index', compact(
                'hotels',
                'searchPerformed',
                'total',
                'searchError'
            ));
        }

        $validated = $request->validate([
            'search' => ['required', 'in:1'],
            'destination' => [
                'required',
                Rule::in(array_keys(self::DESTINATIONS)),
            ],
            'occupancy' => [
                'required',
                Rule::in(array_keys(self::OCCUPANCY)),
            ],
            'checkin' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'checkout' => [
                'required',
                'date_format:Y-m-d',
                'after:checkin',
            ],
            'residency' => [
                'required',
                Rule::in(['eg', 'sa', 'ae', 'gb', 'us']),
            ],
        ]);

        $searchPerformed = true;

        $destination = self::DESTINATIONS[$validated['destination']];
        $occupancy = self::OCCUPANCY[$validated['occupancy']];

        $guests = [];

        for ($room = 0; $room < $occupancy['rooms']; $room++) {
            $guests[] = [
                'adults' => $occupancy['adults_per_room'],
                'children' => [],
            ];
        }

        try {
            $result = $searchService->searchByGeo([
                'checkin' => $validated['checkin'],
                'checkout' => $validated['checkout'],
                'latitude' => $destination['latitude'],
                'longitude' => $destination['longitude'],
                'radius' => 10000,
                'residency' => $validated['residency'],
                'currency' => 'USD',
                'language' => 'en',
                'guests' => $guests,
            ]);

            $hotels = $result['hotels'] ?? [];
            $total = $result['total'] ?? count($hotels);

            // Enrich only the first five hotels to limit API usage.
            $enrichmentLimit = min(5, count($hotels));

            for ($index = 0; $index < $enrichmentLimit; $index++) {
                $hid = $hotels[$index]['hid'] ?? null;

                if (! $hid) {
                    continue;
                }

                try {
                    $content = $contentService->getHotel($hid, 'en');

                    $hotels[$index]['name'] = $content['name'] ?? null;
                    $hotels[$index]['location'] = $content['address'] ?? null;
                    $hotels[$index]['stars'] = $content['star_rating'] ?? null;

                    $image = $content['images'][0]
                        ?? ($content['images_ext'][0]['url'] ?? null);

                    if (is_string($image)) {
                        $hotels[$index]['image'] = str_replace(
                            '{size}',
                            '640x400',
                            $image
                        );
                    }

                    $amenities = [];

                    foreach (($content['amenity_groups'] ?? []) as $group) {
                        foreach (($group['amenities'] ?? []) as $amenity) {
                            if (is_string($amenity)) {
                                $amenities[] = $amenity;
                            }

                            if (count($amenities) >= 5) {
                                break 2;
                            }
                        }
                    }

                    $hotels[$index]['amenities'] = $amenities;

                } catch (Throwable $exception) {
                    Log::warning('RateHawk hotel content unavailable', [
                        'hid' => $hid,
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

        } catch (Throwable $exception) {
            Log::error('Admin RateHawk hotel search failed', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            $searchError = 'Unable to retrieve hotels from RateHawk Sandbox. Please try again.';
        }

        return view('admin.hotels.index', compact(
            'hotels',
            'searchPerformed',
            'total',
            'searchError'
        ));
    }
}
