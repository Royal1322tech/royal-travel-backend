<?php

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\B2BLoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LoyaltyWalletController extends Controller
{
    /**
     * The company's loyalty balances and latest 100 entries. The company comes from the
     * b2b.api.permission middleware (token scope, approved membership, dashboard.view). Entries have
     * a fixed field set: no staff actor ID, deduplication key or company ID.
     */
    public function index(Request $request, B2BLoyaltyService $loyalty)
    {
        abort_unless($request->user() !== null, 401, 'Unauthenticated.');
        $company = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($company) && \Illuminate\Support\Str::isUuid($company), 403, 'No active company in this access token.');
        $company = strtolower($company);

        $transactions = DB::table('rt_loyalty_entries')
            ->where('company_id', $company)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'booking_id', 'entry_type', 'points', 'balance_bucket', 'note', 'created_at'])
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'booking_id' => $entry->booking_id,
                'entry_type' => $entry->entry_type,
                'points' => (int) $entry->points,
                'balance_bucket' => $entry->balance_bucket,
                'note' => $entry->note,
                'created_at' => $entry->created_at,
            ])
            ->values();

        return response()->json([
            'company_id' => $company,
            'balances' => $loyalty->balances($company),
            'transactions' => $transactions,
            'redemption_enabled' => false,
        ])->header('Cache-Control', 'no-store');
    }
}
