<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ExchangeRateController extends Controller
{
    private const CURRENCIES = ['EGP', 'SAR'];

    public function index(Request $request, PermissionService $permissions): View
    {
        $canManage = $permissions->adminCan($request->user(), 'fx.manage');
        $rates = [];
        $adjustments = [];
        foreach (self::CURRENCIES as $currency) {
            $rates[$currency] = DB::table('rt_fx_rates')
                ->where('base_currency', 'USD')
                ->where('quote_currency', $currency)
                ->where('rate_type', 'reference')
                ->where('effective_at', '<=', now())
                ->where('expires_at', '>', now())
                ->orderByDesc('effective_at')
                ->first();
            $adjustments[$currency] = DB::table('rt_fx_adjustments')
                ->where('base_currency', 'USD')
                ->where('quote_currency', $currency)
                ->first();
        }

        return view('admin.exchange-rates.index', compact('rates', 'adjustments', 'canManage'));
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quote_currency' => ['required', Rule::in(self::CURRENCIES)],
            'adjustment_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'adjustment_value' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,6})?$/D', 'numeric', 'between:0,1000000'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        if ($data['adjustment_type'] === 'percentage' && (float) $data['adjustment_value'] > 100) {
            return back()->withErrors(['adjustment_value' => 'Percentage cannot exceed 100.'])->withInput();
        }

        DB::table('rt_fx_adjustments')->updateOrInsert(
            ['base_currency' => 'USD', 'quote_currency' => $data['quote_currency']],
            [
                'adjustment_type' => $data['adjustment_type'],
                'adjustment_value' => $data['adjustment_value'],
                'is_enabled' => (bool) $data['is_enabled'],
                'updated_at' => now(),
            ]
        );

        return redirect()->route('admin.exchange-rates.index')
            ->with('success', 'Exchange-rate adjustment saved for ' . $data['quote_currency'] . '.');
    }
}
