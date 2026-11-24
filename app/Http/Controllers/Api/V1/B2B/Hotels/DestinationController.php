<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hotels\DestinationSearchRequest;
use App\Services\Hotels\RateHawkDestinationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/** GET /api/v1/b2b/destinations?q=&language=&limit= */
final class DestinationController extends Controller
{
    public function __invoke(DestinationSearchRequest $request, RateHawkDestinationService $destinations): JsonResponse
    {
        // Company context must come from the authorization middleware.
        $companyId = $request->attributes->get('authenticated_company_id');
        if (!is_string($companyId) || !Str::isUuid($companyId)) {
            return response()->json([
                'success' => false,
                'code' => 'COMPANY_CONTEXT_MISSING',
                'message' => 'Authenticated company context is required.',
            ], 403);
        }

        return DestinationResponse::from($destinations->autocomplete(...$request->searchArguments()));
    }
}
