<?php

namespace App\Http\Controllers\B2B;

use App\Http\Controllers\Controller;
use App\Services\Pricing\HotelPricingService;
use Illuminate\Http\Request;

final class PricingController extends Controller
{
    public function index(Request $request, HotelPricingService $pricing)
    {
        $companyId = $request->session()->get('active_company_id');
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        return view('b2b.pricing.index', [
            'companyRule' => $pricing->activeRule('company_b2b', $companyId),
            'hasRoyalPrice' => $pricing->activeRule('royal_b2b', $companyId) !== null,
        ]);
    }

    public function save(Request $request, HotelPricingService $pricing)
    {
        $companyId = $request->session()->get('active_company_id');
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        $data = $request->validate([
            'percentage' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,6})?$/D', 'numeric', 'between:0,100'],
            'fixed' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D', 'numeric', 'between:0,1000000'],
        ]);
        abort_unless($pricing->activeRule('royal_b2b', $companyId) !== null, 409, 'Royal has not configured your pricing.');
        $pricing->saveRule('company_b2b', $companyId, $data['percentage'], $data['fixed']);
        return redirect()->route('b2b.pricing.index')->with('success', 'Your company markup has been saved.');
    }
}
