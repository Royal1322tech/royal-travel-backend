<?php

namespace App\Http\Controllers\Api\V1\B2B\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\B2C\Hotels\HotelSearchRequest;
use App\Services\Hotels\HotelSearchApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class HotelSearchController extends Controller
{
    public function __invoke(
        HotelSearchRequest $request,
        HotelSearchApplicationService $searchService
    ): JsonResponse {
        // Company context must come from the authorization middleware.
        $companyId = $request->attributes->get(
            'authenticated_company_id'
        );

        if (!is_string($companyId) || !Str::isUuid($companyId)) {
            return response()->json([
                'success' => false,
                'code' => 'COMPANY_CONTEXT_MISSING',
                'message' => 'Authenticated company context is required.',
            ], 403);
        }

        $result = $searchService->search(
            params: $request->validated(),
            channel: 'b2b',
            companyId: $companyId
        );

        $status = match ($result['code'] ?? null) {
            'HOTEL_SUPPLIER_UNAVAILABLE',
            'HOTEL_SEARCH_NOT_READY' => 503,

            'DESTINATION_NOT_FOUND' => 422,

            default => ($result['success'] ?? false) === true
                ? 200
                : 500,
        };

        return response()->json($result, $status);
    }
}
