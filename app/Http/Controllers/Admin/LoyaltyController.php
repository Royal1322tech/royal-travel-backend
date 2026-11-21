<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\B2BLoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class LoyaltyController extends Controller
{
    public function index(Request $request, B2BLoyaltyService $loyalty)
    {
        $companies = DB::table('rt_companies')->select('id', 'legal_name', 'display_name')->orderBy('legal_name')->paginate(25);
        $companies->getCollection()->transform(function ($c) use ($loyalty) { $c->balances = $loyalty->balances($c->id); return $c; });
        return view('admin.loyalty.index', [
            'companies' => $companies,
            'rules' => DB::table('rt_loyalty_rules as r')->leftJoin('rt_companies as c', 'c.id', '=', 'r.company_id')
                ->select('r.*', 'c.legal_name')->orderBy('r.currency')->get(),
            'entries' => DB::table('rt_loyalty_entries as e')->join('rt_companies as c', 'c.id', '=', 'e.company_id')
                ->select('e.*', 'c.legal_name')->orderByDesc('e.created_at')->limit(50)->get(),
        ]);
    }

    public function saveRule(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'uuid', 'exists:rt_companies,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:rt_currencies,code'],
            'points_per_100' => ['required', 'numeric', 'min:0', 'max:100000'],
            'enabled' => ['required', 'boolean'],
        ]);
        $company = $data['company_id'] ?? null;
        DB::transaction(function () use ($data, $company, $request) {
            // Lock the global scope row (or company row) so concurrent saves cannot create duplicates.
            if ($company) DB::table('rt_companies')->where('id', $company)->lockForUpdate()->first();
            else DB::select('SELECT pg_advisory_xact_lock(?)', [1932401]);
            $existing = DB::table('rt_loyalty_rules')->where('currency', strtoupper($data['currency']))
                ->where('company_id', $company)->first();
            if ($existing) {
                DB::table('rt_loyalty_rules')->where('id', $existing->id)->update([
                    'points_per_100' => $data['points_per_100'], 'enabled' => (bool) $data['enabled'],
                    'effective_from' => now(), 'created_by' => $request->user()->id, 'updated_at' => now(),
                ]);
            } else {
                DB::table('rt_loyalty_rules')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(), 'company_id' => $company,
                    'currency' => strtoupper($data['currency']), 'points_per_100' => $data['points_per_100'],
                    'enabled' => (bool) $data['enabled'], 'effective_from' => now(),
                    'created_by' => $request->user()->id, 'updated_at' => now(),
                ]);
            }
        });
        return back()->with('success', 'Loyalty rule saved. Only bookings confirmed after this change are eligible.');
    }

    public function adjust(Request $request, B2BLoyaltyService $loyalty)
    {
        $data = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:rt_companies,id'],
            'action' => ['required', Rule::in(['credit', 'debit'])],
            'points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $loyalty->manual($data['company_id'], (int) $request->user()->id,
            ($data['action'] === 'credit' ? 1 : -1) * (int) $data['points'], $data['reason']);
        return back()->with('success', 'Points adjustment recorded in the ledger.');
    }
}
