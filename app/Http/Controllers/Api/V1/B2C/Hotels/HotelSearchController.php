<?php

namespace App\Http\Controllers\Api\V1\B2C\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\B2C\Hotels\HotelSearchRequest;
use App\Services\Hotels\HotelSearchApplicationService;
use Illuminate\Http\JsonResponse;

final class HotelSearchController extends Controller
{
    public function __invoke(
        HotelSearchRequest $request,
        HotelSearchApplicationService $searchService
    ): JsonResponse {
        $result = $searchService->search(
            params: $request->validated(),
            channel: 'b2c'
        );

        $status = match ($result['code'] ?? null) {
            'HOTEL_SUPPLIER_UNAVAILABLE',
            'HOTEL_SEARCH_NOT_READY' => 503,

            default => ($result['success'] ?? false) === true
                ? 200
                : 500,
        };

        return response()->json($result, $status);
    }
}
