<?php

namespace App\Http\Controllers\B2B;

use App\Http\Controllers\Controller;
use App\Services\Pricing\HotelPricingService;
use App\Services\Pricing\PricingVersionConflictException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PricingController extends Controller
{
    public function index(Request $request, HotelPricingService $pricing)
    {
        $companyId = $this->companyId($request);
        return view('b2b.pricing.index', [
            'companyRule' => $pricing->activeRule('company_b2b', $companyId),
            'hasRoyalPrice' => $pricing->activeRule('royal_b2b', $companyId) !== null,
        ]);
    }

    public function save(Request $request, HotelPricingService $pricing)
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'percentage' => ['required', 'string', 'regex:/^\d{1,3}(?:\.\d{1,6})?$/D', 'numeric', 'between:0,100'],
            'fixed' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D', 'numeric', 'between:0,1000000'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        abort_unless($pricing->activeRule('royal_b2b', $companyId) !== null, 409,
            __('pricing.royal_missing'));

        try {
            $pricing->saveRule(
                'company_b2b', $companyId, $data['percentage'], $data['fixed'],
                (int) $data['expected_version'], (int) $request->user()->id,
                (string) Str::uuid()
            );
        } catch (PricingVersionConflictException $e) {
            return redirect()->route('b2b.pricing.index')
                ->with('pricing_conflict', __('pricing.conflict'));
        }
        return redirect()->route('b2b.pricing.index')
            ->with('success', __('pricing.saved'));
    }

    private function companyId(Request $request): string
    {
        $id = $request->session()->get('active_company_id');
        abort_unless(is_string($id) && Str::isUuid($id), 403);
        return $id;
    }
}
