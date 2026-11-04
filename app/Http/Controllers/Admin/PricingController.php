<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Pricing\HotelPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PricingController extends Controller
{
    public function index(HotelPricingService $pricing)
    {
        $companies = DB::table('rt_companies')->select('id', 'legal_name', 'display_name', 'status')
            ->orderBy('legal_name')->paginate(25);
        $rows = $companies->getCollection()->map(function ($company) use ($pricing) {
            $company->royal_rule = $pricing->activeRule('royal_b2b', $company->id);
            return $company;
        });
        $companies->setCollection($rows);
        return view('admin.pricing.index', [
            'companies' => $companies,
            'direct' => $pricing->activeRule('royal_direct'),
        ]);
    }

    public function save(Request $request, HotelPricingService $pricing)
    {
        $data = $request->validate([
            'level' => ['required', Rule::in(['royal_direct', 'royal_b2b'])],
            'company_id' => ['nullable', 'uuid', 'exists:rt_companies,id'],
            'percentage' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,6})?$/D', 'numeric', 'between:0,100'],
            'fixed' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D', 'numeric', 'between:0,1000000'],
        ]);
        if ($data['level'] === 'royal_b2b' && empty($data['company_id'])) {
            return back()->withErrors(['company_id' => 'Select a company.']);
        }
        $companyId = $data['level'] === 'royal_direct' ? null : $data['company_id'];
        $pricing->saveRule($data['level'], $companyId, $data['percentage'], $data['fixed']);
        return redirect()->route('admin.pricing.index')->with('success', 'Pricing rule version saved.');
    }
}
