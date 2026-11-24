<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B\Hotels;

use Illuminate\Http\JsonResponse;

/** Single public response shape for the B2B and Admin destination endpoints. */
final class DestinationResponse
{
    public static function from(array $result): JsonResponse
    {
        if (($result['success'] ?? false) !== true) {
            return response()->json([
                'success' => false,
                'code' => 'DESTINATION_SEARCH_UNAVAILABLE',
                'message' => 'Destination search is temporarily unavailable. Please try again.',
                'data' => [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'scope' => $result['scope'],
            'data' => $result['destinations'],
        ]);
    }
}
