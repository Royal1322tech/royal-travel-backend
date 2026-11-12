<?php

namespace Tests\Unit;

use App\Services\Hotels\RateHawkPriceResolver;
use PHPUnit\Framework\TestCase;

final class RateHawkPriceResolverTest extends TestCase
{
    private RateHawkPriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new RateHawkPriceResolver();
    }

    private function validRate(): array
    {
        return [
            'supplier' => [
                'daily_prices' => [
                    '100.00',
                    '100.00',
                ],
                'payment_options' => [
                    [
                        'type' => 'deposit',
                        'supplier_amount' => '200.00',
                        'supplier_currency' => 'USD',
                        'supplier_show_amount' => '200.00',
                        'supplier_show_currency' => 'USD',
                        'vat_data' => [
                            'included' => false,
                            'applied' => false,
                            'amount' => '0.00',
                        ],
                        'tax_data' => [],
                    ],
                ],
            ],
        ];
    }

    public function test_accepts_valid_supplier_total(): void
    {
        $result = $this->resolver->resolve(
            $this->validRate()
        );

        $this->assertIsArray($result);
        $this->assertTrue(
            $result['pricing_verified']
        );
        $this->assertSame('USD', $result['currency']);
        $this->assertEquals(
            200,
            (float) $result['amount']
        );
    }

    public function test_rejects_mismatched_daily_prices(): void
    {
        $rate = $this->validRate();

        $rate['supplier']['daily_prices'] = [
            '90.00',
            '90.00',
        ];

        $result = $this->resolver->resolve($rate);

        $this->assertFalse(
            $result['pricing_verified'] ?? false
        );
    }

    public function test_rejects_additional_taxes(): void
    {
        $rate = $this->validRate();

        $rate['supplier']['payment_options'][0]
            ['tax_data'] = [
                ['name' => 'City tax', 'amount' => '20.00'],
            ];

        $result = $this->resolver->resolve($rate);

        $this->assertFalse(
            $result['pricing_verified'] ?? false
        );
    }

    public function test_rejects_invalid_currency(): void
    {
        $rate = $this->validRate();

        $rate['supplier']['payment_options'][0]
            ['supplier_currency'] = 'EUR';

        $result = $this->resolver->resolve($rate);

        $this->assertFalse(
            $result['pricing_verified'] ?? false
        );
    }

    public function test_rejects_multiple_payment_options(): void
    {
        $rate = $this->validRate();

        $rate['supplier']['payment_options'][] =
            $rate['supplier']['payment_options'][0];

        $result = $this->resolver->resolve($rate);

        $this->assertFalse(
            $result['pricing_verified'] ?? false
        );
    }
}
