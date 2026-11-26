<?php

namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Services\Pricing\HotelPricingService;
use App\Services\Pricing\PricingVersionConflictException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class MarkupController extends Controller
{
    public function index(
        Request $request,
        HotelPricingService $pricing
    ) {
        $company = $request->attributes->get('portal_company');

        $companyId = $this->companyId($request);

        $companyRule = $pricing->activeRule(
            'company_b2b',
            $companyId
        );

        $hasRoyalPrice = $pricing->activeRule(
            'royal_b2b',
            $companyId
        ) !== null;

        return view(
            'b2b.company-portal.markup.index',
            compact(
                'company',
                'companyRule',
                'hasRoyalPrice'
            )
        );
    }

    public function save(
        Request $request,
        HotelPricingService $pricing
    ) {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'percentage' => [
                'required',
                'string',
                'regex:/^\d{1,3}(?:\.\d{1,6})?$/D',
                'numeric',
                'between:0,100',
            ],

            'fixed' => [
                'required',
                'string',
                'regex:/^\d{1,7}(?:\.\d{1,2})?$/D',
                'numeric',
                'between:0,1000000',
            ],

            'expected_version' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        if (
            $pricing->activeRule(
                'royal_b2b',
                $companyId
            ) === null
        ) {
            return redirect()
                ->route('company-portal.markup.index')
                ->withErrors([
                    'pricing' =>
                        'Royal Travel pricing must be configured first.',
                ]);
        }

        try {

            $pricing->saveRule(
                'company_b2b',
                $companyId,
                $data['percentage'],
                $data['fixed'],
                (int) $data['expected_version'],
                (int) $request->user()->id,
                (string) Str::uuid()
            );

        } catch (PricingVersionConflictException $e) {

            return redirect()
                ->route('company-portal.markup.index')
                ->with(
                    'pricing_conflict',
                    'Pricing settings have changed. Please review and try again.'
                );
        }

        return redirect()
            ->route('company-portal.markup.index')
            ->with(
                'success',
                'Company markup updated successfully.'
            );
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->session()
            ->get('active_company_id');

        abort_unless(
            is_string($companyId)
            && Str::isUuid($companyId),
            403
        );

        return $companyId;
    }
}