<?php

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\B2BLoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LoyaltyWalletController extends Controller
{
    public function index(Request $request, B2BLoyaltyService $loyalty)
    {
        $companyId = (string) $request->user()->currentAccessToken()?->abilities[0];
        // Never trust company_id from request input: derive it from the authorized membership.
        $memberships = DB::table('rt_company_memberships')->where('user_id', $request->user()->id)
            ->where('status', 'active')->pluck('company_id');
        $tokenAbilities = $request->user()->currentAccessToken()?->abilities ?? [];
        $company = $memberships->first(fn ($id) => in_array('company:'.$id, $tokenAbilities, true));
        abort_unless($company, 403, 'No active company in this access token.');
        return response()->json([
            'company_id' => $company, 'balances' => $loyalty->balances($company),
            'transactions' => DB::table('rt_loyalty_entries')->where('company_id', $company)
                ->orderByDesc('created_at')->limit(100)->get(),
            'redemption_enabled' => false,
        ]);
    }
}
