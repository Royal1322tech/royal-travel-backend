<?php

namespace Tests\Unit;

use App\Services\Hotels\RateHawkPriceResolver;
use App\Services\Hotels\RateHawkRateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RateHawkVerifiedPriceTest extends TestCase
{
    private static function rate(): array
    {
        return [
            'room_name' => 'Standard Double Room',
            'book_hash' => 'h-test-rate',
            'daily_prices' => ['40.00', '60.00'],
            'payment_options' => [
                'payment_types' => [[
                    'type' => 'deposit',
                    'amount' => '100.00',
                    'currency_code' => 'USD',
                    'show_amount' => '100.00',
                    'show_currency_code' => 'USD',
                    'vat_data' => ['included' => false, 'applied' => false],
                    'tax_data' => [],
                ]],
            ],
        ];
    }

    public function test_verified_usd_deposit_rate(): void
    {
        $normalized = (new RateHawkRateNormalizer())->normalize(self::rate());
        $resolved = (new RateHawkPriceResolver())->resolve($normalized);

        self::assertIsArray($resolved);
        self::assertTrue($resolved['pricing_verified']);
        self::assertSame('100.00', $resolved['amount']);
        self::assertSame('USD', $resolved['currency']);
    }

    public function test_mismatched_daily_prices_are_not_verified(): void
    {
        $rate = self::rate();
        $rate['daily_prices'] = ['40.00', '50.00'];

        $resolved = (new RateHawkPriceResolver())->resolve(
            (new RateHawkRateNormalizer())->normalize($rate)
        );

        self::assertIsArray($resolved);
        self::assertFalse($resolved['pricing_verified']);
    }

    public function test_extra_tax_is_not_verified(): void
    {
        $rate = self::rate();
        $rate['payment_options']['payment_types'][0]['tax_data'] = [
            ['name' => 'city_tax', 'amount' => '2.00'],
        ];

        $resolved = (new RateHawkPriceResolver())->resolve(
            (new RateHawkRateNormalizer())->normalize($rate)
        );

        self::assertIsArray($resolved);
        self::assertFalse($resolved['pricing_verified']);
    }

    public function test_non_deposit_payment_is_not_verified(): void
    {
        $rate = self::rate();
        $rate['payment_options']['payment_types'][0]['type'] = 'now';

        $resolved = (new RateHawkPriceResolver())->resolve(
            (new RateHawkRateNormalizer())->normalize($rate)
        );

        self::assertIsArray($resolved);
        self::assertFalse($resolved['pricing_verified']);
    }
}
