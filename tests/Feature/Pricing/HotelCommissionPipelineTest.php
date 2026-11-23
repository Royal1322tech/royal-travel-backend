<?php

namespace Tests\Feature\Pricing;

use App\Services\Pricing\FxRateUnavailableException;
use App\Services\Pricing\HotelCurrencyQuoteService;
use App\Services\Pricing\HotelPricingService;
use App\Services\Pricing\PricingNotConfiguredException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Royal markup → agency purchase price → company markup → customer selling price, per company. */
final class HotelCommissionPipelineTest extends TestCase
{
    use DatabaseTransactions;

    private HotelPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(HotelPricingService::class);
    }

    private function company(): string
    {
        $id = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $id, 'legal_name' => 'Commission Test ' . substr($id, 0, 8), 'status' => 'approved', 'created_at' => now()]);

        return $id;
    }

    public function test_controlled_100_usd_example(): void
    {
        $company = $this->company();
        $this->pricing->saveRule('royal_b2b', $company, '10', '1.00');
        $this->pricing->saveRule('company_b2b', $company, '5', '2.00');

        $quote = $this->pricing->quoteUsd('100.00', 'b2b', $company);
        $this->assertSame('111.00', $quote['company_buy_price']);
        $this->assertSame('118.55', $quote['selling_price']);

        $public = app(HotelCurrencyQuoteService::class)->quoteVerifiedUsd('100.00', 'b2b', 'USD', $company);
        $this->assertSame(['currency' => 'USD', 'customer_selling_price' => '118.55', 'agency_purchase_price' => '111.00'], $public);
    }

    public function test_percentage_rounding_is_to_the_nearest_cent(): void
    {
        $company = $this->company();
        $this->pricing->saveRule('royal_b2b', $company, '7.5', '0');
        $this->pricing->saveRule('company_b2b', $company, '3.333', '0.10');

        $quote = $this->pricing->quoteUsd('99.99', 'b2b', $company);
        // 99.99 + 7.49925 → 107.49 ; 107.49 + 3.58264 → 111.07 + 0.10 = 111.17
        $this->assertSame('107.49', $quote['company_buy_price']);
        $this->assertSame('111.17', $quote['selling_price']);
    }

    public function test_one_companys_policy_never_prices_another_company(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->pricing->saveRule('royal_b2b', $a, '10', '1.00');
        $this->pricing->saveRule('company_b2b', $a, '5', '2.00');
        $this->pricing->saveRule('royal_b2b', $b, '20', '0');
        $this->pricing->saveRule('company_b2b', $b, '0', '0');

        $this->assertSame('118.55', $this->pricing->quoteUsd('100.00', 'b2b', $a)['selling_price']);
        $this->assertSame('120.00', $this->pricing->quoteUsd('100.00', 'b2b', $b)['selling_price']);

        $c = $this->company();
        $this->expectException(PricingNotConfiguredException::class);
        $this->pricing->quoteUsd('100.00', 'b2b', $c);
    }

    public function test_missing_royal_or_company_policy_is_explicit(): void
    {
        $onlyCompany = $this->company();
        $this->pricing->saveRule('company_b2b', $onlyCompany, '5', '0');
        try {
            $this->pricing->quoteUsd('100.00', 'b2b', $onlyCompany);
            $this->fail('A missing Royal policy must not be priced.');
        } catch (PricingNotConfiguredException) {
            $this->addToAssertionCount(1);
        }

        $onlyRoyal = $this->company();
        $this->pricing->saveRule('royal_b2b', $onlyRoyal, '10', '0');
        $this->expectException(PricingNotConfiguredException::class);
        $this->pricing->quoteUsd('100.00', 'b2b', $onlyRoyal);
    }

    public function test_latest_active_version_is_used_and_stale_updates_are_refused(): void
    {
        $company = $this->company();
        $this->pricing->saveRule('royal_b2b', $company, '10', '1.00');
        $this->pricing->saveRule('company_b2b', $company, '5', '2.00');
        $version = (int) $this->pricing->activeRule('company_b2b', $company)['version'];
        $this->pricing->saveRule('company_b2b', $company, '10', '0', $version);

        $this->assertSame('122.10', $this->pricing->quoteUsd('100.00', 'b2b', $company)['selling_price']);

        $this->expectException(\App\Services\Pricing\PricingVersionConflictException::class);
        $this->pricing->saveRule('company_b2b', $company, '1', '0', $version);
    }

    public function test_display_currency_without_a_current_rate_is_not_priced(): void
    {
        $company = $this->company();
        $this->pricing->saveRule('royal_b2b', $company, '10', '1.00');
        $this->pricing->saveRule('company_b2b', $company, '5', '2.00');
        DB::table('rt_fx_rates')->where('quote_currency', 'SAR')->delete();

        $this->expectException(FxRateUnavailableException::class);
        app(HotelCurrencyQuoteService::class)->quoteVerifiedUsd('100.00', 'b2b', 'SAR', $company);
    }
}
