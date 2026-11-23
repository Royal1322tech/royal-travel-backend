<?php

namespace Tests\Unit\Hotels;

use App\Services\Hotels\RateHawkPriceResolver;
use PHPUnit\Framework\TestCase;

/** Supplier price verification: which RateHawk payment, VAT and tax structures reconcile. */
final class RateHawkPriceReconciliationTest extends TestCase
{
    private function rate(array $payment = [], array $supplier = []): array
    {
        return ['supplier' => $supplier + [
            'daily_prices' => ['50.00', '50.00'],
            'payment_options' => [$payment + [
                'type' => 'deposit',
                'supplier_amount' => '100.00',
                'supplier_currency' => 'USD',
                'supplier_show_amount' => '100.00',
                'supplier_show_currency' => 'USD',
                'vat_data' => ['included' => false, 'applied' => false, 'amount' => '0.00', 'currency_code' => 'USD', 'value' => '0.00'],
                'tax_data' => [],
            ]],
        ]];
    }

    private function resolve(array $rate): array
    {
        $result = (new RateHawkPriceResolver())->resolve($rate);
        $this->assertIsArray($result);
        if ($result['reason'] !== null) {
            $this->assertContains($result['reason'], RateHawkPriceResolver::REASONS);
        }

        return $result;
    }

    public function test_controlled_100_usd_deposit_rate_is_verified(): void
    {
        $result = $this->resolve($this->rate());
        $this->assertTrue($result['pricing_verified']);
        $this->assertSame('100.00', $result['amount']);
        $this->assertNull($result['reason']);
        $this->assertSame([], $result['taxes_at_property']);
    }

    public function test_empty_taxes_object_is_verified(): void
    {
        $this->assertTrue($this->resolve($this->rate(['tax_data' => ['taxes' => []]]))['pricing_verified']);
    }

    public function test_taxes_included_by_the_supplier_are_verified_and_not_listed(): void
    {
        $result = $this->resolve($this->rate(['tax_data' => ['taxes' => [
            ['name' => 'service_fee', 'included_by_supplier' => true, 'amount' => '8.00', 'currency_code' => 'USD'],
        ]]]));
        $this->assertTrue($result['pricing_verified']);
        $this->assertSame('100.00', $result['amount']);
        $this->assertSame([], $result['taxes_at_property']);
    }

    public function test_taxes_payable_at_the_property_are_disclosed_separately(): void
    {
        $result = $this->resolve($this->rate(['tax_data' => ['taxes' => [
            ['name' => 'city_tax', 'included_by_supplier' => false, 'amount' => '15.00', 'currency_code' => 'AED'],
            ['name' => 'tourism_fee', 'included_by_supplier' => false, 'amount' => '8.00', 'currency_code' => 'AED'],
            ['name' => 'resort_fee', 'included_by_supplier' => false, 'amount' => '5.00', 'currency_code' => 'USD'],
        ]]]));
        $this->assertTrue($result['pricing_verified']);
        $this->assertSame('100.00', $result['amount'], 'Property taxes never change the supplier total.');
        $this->assertSame([
            ['amount' => '23.00', 'currency' => 'AED'],
            ['amount' => '5.00', 'currency' => 'USD'],
        ], $result['taxes_at_property']);
    }

    public function test_unreconcilable_tax_structures_are_not_verified(): void
    {
        foreach ([
            null,
            [['name' => 'city_tax', 'amount' => '20.00']],
            ['taxes' => [['name' => 'city_tax', 'amount' => '20.00', 'currency_code' => 'AED']]],
            ['taxes' => [['included_by_supplier' => false, 'amount' => 'twenty', 'currency_code' => 'AED']]],
            ['taxes' => [['included_by_supplier' => false, 'amount' => '20.00', 'currency_code' => 'aed']]],
            ['taxes' => [], 'other' => 1],
        ] as $taxData) {
            $result = $this->resolve($this->rate(['tax_data' => $taxData]));
            $this->assertFalse($result['pricing_verified']);
            $this->assertSame('tax_structure_unsupported', $result['reason']);
        }
    }

    public function test_vat_included_in_the_total_is_verified(): void
    {
        $result = $this->resolve($this->rate(['vat_data' => ['included' => true, 'applied' => true, 'amount' => '4.76', 'currency_code' => 'USD']]));
        $this->assertTrue($result['pricing_verified']);
    }

    public function test_vat_added_on_top_or_unexplained_is_not_verified(): void
    {
        foreach ([
            ['included' => false, 'applied' => true, 'amount' => '5.00'],
            ['included' => false, 'applied' => false, 'amount' => '5.00'],
            ['included' => true, 'applied' => true, 'amount' => '150.00'],
            ['included' => 'no', 'applied' => false],
        ] as $vat) {
            $result = $this->resolve($this->rate(['vat_data' => $vat]));
            $this->assertFalse($result['pricing_verified']);
            $this->assertSame('vat_not_reconciled', $result['reason']);
        }
        $this->assertSame('vat_data_missing', $this->resolve($this->rate(['vat_data' => null]))['reason']);
    }

    public function test_one_deposit_among_several_payment_options_is_used(): void
    {
        $rate = $this->rate();
        $card = $rate['supplier']['payment_options'][0];
        $card['type'] = 'now';
        $card['supplier_amount'] = '104.00';
        $rate['supplier']['payment_options'][] = $card;

        $result = $this->resolve($rate);
        $this->assertTrue($result['pricing_verified']);
        $this->assertSame('100.00', $result['amount']);
    }

    public function test_unsupported_payment_structures_are_not_verified(): void
    {
        $this->assertSame('payment_type_not_deposit', $this->resolve($this->rate(['type' => 'hotel']))['reason']);
        $this->assertSame('supplier_currency_not_usd', $this->resolve($this->rate(['supplier_currency' => 'EUR']))['reason']);
        $this->assertSame('show_amount_mismatch', $this->resolve($this->rate(['supplier_show_amount' => '101.00']))['reason']);
        $this->assertSame('daily_prices_mismatch', $this->resolve($this->rate([], ['daily_prices' => ['50.00', '49.99']]))['reason']);

        $twoDeposits = $this->rate();
        $twoDeposits['supplier']['payment_options'][] = $twoDeposits['supplier']['payment_options'][0];
        $this->assertSame('payment_options_ambiguous', $this->resolve($twoDeposits)['reason']);
    }
}
