<?php

namespace Tests\Feature\Pricing;

use App\Services\Pricing\HotelPricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class HotelPricingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private HotelPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(HotelPricingService::class);
    }

    private function createCompany(): string
    {
        $id = (string) Str::uuid();

        DB::table('rt_companies')->insert([
            'id' => $id,
            'legal_name' => 'Pricing Test Company',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        return $id;
    }

    public function test_b2c_price_applies_markup_without_supplier_cost(): void
    {
        $this->pricing->saveRule('royal_direct', null, '10', '5.00');

        $quote = $this->pricing->quoteUsd('100.00', 'b2c');

        $this->assertSame('USD', $quote['currency']);
        $this->assertSame('115.00', $quote['selling_price']);
        $this->assertArrayNotHasKey('supplier_cost', $quote);
        $this->assertArrayNotHasKey('company_buy_price', $quote);
    }

    public function test_b2b_applies_two_markup_levels(): void
    {
        $companyId = $this->createCompany();

        $this->pricing->saveRule('royal_b2b', $companyId, '10', '5.00');
        $this->pricing->saveRule('company_b2b', $companyId, '5', '2.00');

        $quote = $this->pricing->quoteUsd('100.00', 'b2b', $companyId);

        $this->assertSame('115.00', $quote['company_buy_price']);
        $this->assertSame('122.75', $quote['selling_price']);
        $this->assertArrayNotHasKey('supplier_cost', $quote);
    }

    public function test_new_policy_version_supersedes_previous(): void
    {
        $this->pricing->saveRule('royal_direct', null, '10', '0');
        $first = $this->pricing->activeRule('royal_direct');

        $this->pricing->saveRule('royal_direct', null, '20', '0');
        $second = $this->pricing->activeRule('royal_direct');

        $this->assertNotSame($first['policy_id'], $second['policy_id']);
        $this->assertSame(
            (int) $first['version'] + 1,
            (int) $second['version']
        );

        $this->assertSame(
            'superseded',
            DB::table('rt_pricing_policies')
                ->where('id', $first['policy_id'])
                ->value('status')
        );

        $quote = $this->pricing->quoteUsd('100.00', 'b2c');
        $this->assertSame('120.00', $quote['selling_price']);
    }

    public function test_missing_company_pricing_is_rejected(): void
    {
        $companyId = $this->createCompany();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Company pricing is not configured');

        $this->pricing->quoteUsd('100.00', 'b2b', $companyId);
    }
}
