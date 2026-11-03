<?php

namespace Tests\Unit\Pricing;

use App\Services\Pricing\HotelPricingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class HotelPricingCalculationTest extends TestCase
{
    private HotelPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new HotelPricingService();
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod(
            HotelPricingService::class,
            $method
        ))->invoke($this->pricing, ...$args);
    }

    public function test_percentage_markup(): void
    {
        $result = $this->invokePrivate('apply', 10000, [
            'percentage' => '10.000000',
            'fixed' => '0',
        ]);

        $this->assertSame(11000, $result);
    }

    public function test_fixed_markup(): void
    {
        $result = $this->invokePrivate('apply', 10000, [
            'percentage' => '0',
            'fixed' => '25.00',
        ]);

        $this->assertSame(12500, $result);
    }

    public function test_percentage_rounds_to_nearest_cent(): void
    {
        $result = $this->invokePrivate('apply', 101, [
            'percentage' => '0.500000',
            'fixed' => '0',
        ]);

        $this->assertSame(102, $result);
    }

    public function test_two_level_b2b_markup(): void
    {
        $supplier = 10000;

        $wholesale = $this->invokePrivate('apply', $supplier, [
            'percentage' => '10.000000',
            'fixed' => '5.00',
        ]);

        $retail = $this->invokePrivate('apply', $wholesale, [
            'percentage' => '5.000000',
            'fixed' => '2.00',
        ]);

        $this->assertSame(11500, $wholesale);
        $this->assertSame(12275, $retail);
    }

    public function test_rejects_invalid_supplier_amounts(): void
    {
        foreach (['-1', '10.999', 'abc', '01.00'] as $amount) {
            try {
                $this->invokePrivate('toCents', $amount);
                $this->fail("Amount {$amount} should be rejected");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    'Expected nonnegative USD amount',
                    $e->getMessage()
                );
            }
        }
    }

    public function test_rejects_fractional_cent_fixed_markup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Fixed USD markup cannot contain fractional cents'
        );

        $this->invokePrivate('apply', 10000, [
            'percentage' => '0',
            'fixed' => '1.234',
        ]);
    }
}
