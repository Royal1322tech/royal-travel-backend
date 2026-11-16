<?php

namespace App\Http\Controllers\Api\V1\B2B\Hotels;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkHotelContentService;
use App\Services\Hotels\RateHawkSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TopRatedController extends Controller
{
    public function topRated(
        Request $request,
        RateHawkSearchService $searchService,
        RateHawkHotelContentService $contentService
    ): JsonResponse {
        $params = $request->validate([
            'checkin' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'checkout' => ['required', 'date_format:Y-m-d', 'after:checkin'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:1', 'max:50000'],
            'residency' => ['sometimes', 'string', 'size:2'],
            'language' => ['sometimes', 'in:en,ar'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'guests' => ['sometimes', 'array', 'min:1'],
            'guests.*.adults' => ['required_with:guests', 'integer', 'min:1'],
            'guests.*.children' => ['required_with:guests', 'array'],
            'guests.*.children.*' => ['integer', 'between:0,17'],
        ]);

        $search = $searchService->searchByGeo($params);
        $hotels = [];

        // Limit supplier detail calls to protect API usage.
        $candidates = collect($search['hotels'])
            ->filter(fn ($hotel) => !empty($hotel['hid']))
            ->unique('hid')
            ->take(30);

        foreach ($candidates as $candidate) {
            try {
                $hotel = $contentService->getHotel(
                    $candidate['hid'],
                    $params['language'] ?? 'en'
                );

                $stars = (int) ($hotel['star_rating'] ?? 0);

                if ($stars < 1 || !empty($hotel['is_closed']) || !empty($hotel['deleted'])) {
                    continue;
                }

                $images = $hotel['images'] ?? [];

                $hotels[] = [
                    'id' => $hotel['id'] ?? $candidate['id'],
                    'hid' => $hotel['hid'] ?? $candidate['hid'],
                    'name' => $hotel['name'] ?? null,
                    'stars' => $stars,
                    'address' => $hotel['address'] ?? null,
                    'city' => $hotel['region']['name'] ?? null,
                    'image_url' => is_array($images) && !empty($images)
                        ? str_replace('{size}', '640x400', $images[0])
                        : null,
                ];
            } catch (Throwable $e) {
                Log::warning('RateHawk hotel content failed', [
                    'hid' => $candidate['hid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $topHotels = collect($hotels)
            ->sortBy([
                ['stars', 'desc'],
                ['name', 'asc'],
            ])
            ->take(10)
            ->values();

        return response()->json([
            'message' => 'Top hotels by star rating retrieved successfully.',
            'ranking' => 'hotel_stars',
            'total' => $topHotels->count(),
            'data' => $topHotels,
        ]);
    }
}
