<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\V1\B2B\Hotels\DestinationResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hotels\DestinationSearchRequest;
use App\Services\Hotels\RateHawkDestinationService;
use Illuminate\Http\JsonResponse;

/** GET /admin/hotels/destinations?q=&language=&limit= (session auth, hotels.view). */
final class HotelDestinationController extends Controller
{
    public function __invoke(DestinationSearchRequest $request, RateHawkDestinationService $destinations): JsonResponse
    {
        return DestinationResponse::from($destinations->autocomplete(...$request->searchArguments()));
    }
}
