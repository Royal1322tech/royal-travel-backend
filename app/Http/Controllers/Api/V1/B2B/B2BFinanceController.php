<?php

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Services\Finance\B2BFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Read-only B2B financial account (wallet and credit) for the authenticated company.
 *
 * Token scope, active approved membership and the payments.view permission are enforced by the
 * b2b.api.permission middleware, which also resolves the company. Entries are returned with a fixed
 * set of fields: the staff actor is never exposed.
 */
final class B2BFinanceController extends Controller
{
    public function show(Request $request, B2BFinanceService $finance): JsonResponse
    {
        abort_unless($request->user() !== null, 401, 'Unauthenticated.');
        $companyId = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($companyId) && Str::isUuid($companyId), 403, 'Invalid company context.');
        $companyId = strtolower($companyId);

        $entries = collect($finance->entries($companyId))->map(fn ($entry) => [
            'id' => $entry->id,
            'type' => $entry->type,
            'amount_cents' => $entry->amount_cents === null ? null : (int) $entry->amount_cents,
            'wallet_after_cents' => (int) $entry->wallet_after_cents,
            'limit_after_cents' => (int) $entry->limit_after_cents,
            'used_after_cents' => (int) $entry->used_after_cents,
            'frozen_after' => (bool) $entry->frozen_after,
            'reference' => $entry->reference,
            'reason' => $entry->reason,
            'created_at' => $entry->created_at,
        ])->values();

        return response()->json([
            'account' => $finance->snapshot($companyId),
            'entries' => $entries,
        ])->header('Cache-Control', 'no-store');
    }
}
