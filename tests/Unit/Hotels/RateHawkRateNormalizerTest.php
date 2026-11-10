<?php

namespace Tests\Unit\Hotels;

use App\Services\Hotels\RateHawkRateNormalizer;
use PHPUnit\Framework\TestCase;

class RateHawkRateNormalizerTest extends TestCase
{
    private RateHawkRateNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new RateHawkRateNormalizer();
    }

    public function test_normalizes_room_and_meal_details(): void
    {
        $result = $this->normalizer->normalize([
            'room_name' => 'Deluxe Room',
            'room_data_trans' => [
                'main_name' => 'Deluxe',
                'bedding_type' => 'king',
            ],
            'meal' => 'breakfast',
            'meal_data' => [
                'value' => 'Breakfast Included',
                'has_breakfast' => true,
            ],
        ]);

        $this->assertSame('Deluxe Room', $result['room']['name']);
        $this->assertSame('king', $result['room']['bedding_type']);
        $this->assertSame('Breakfast Included', $result['meal']['name']);
        $this->assertTrue($result['meal']['has_breakfast']);
    }

    public function test_preserves_supplier_payment_data_internally(): void
    {
        $result = $this->normalizer->normalize([
            'match_hash' => 'private-match-hash',
            'daily_prices' => ['100.00', '120.00'],
            'payment_options' => [
                'payment_types' => [
                    [
                        'type' => 'now',
                        'amount' => '220.00',
                        'currency_code' => 'USD',
                        'commission_info' => ['amount' => '20.00'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'private-match-hash',
            $result['supplier']['match_hash']
        );
        $this->assertSame(
            '220.00',
            $result['supplier']['payment_options'][0]['supplier_amount']
        );
        $this->assertSame(
            ['amount' => '20.00'],
            $result['supplier']['payment_options'][0]['commission_info']
        );
    }

    public function test_normalizes_cancellation_policy(): void
    {
        $result = $this->normalizer->normalize([
            'payment_options' => [
                'payment_types' => [
                    [
                        'cancellation_penalties' => [
                            'free_cancellation_before' => '2027-03-08T12:00:00',
                            'policies' => [
                                ['amount' => '50.00'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $cancellation = $result['supplier']['payment_options'][0]['cancellation'];

        $this->assertSame(
            '2027-03-08T12:00:00',
            $cancellation['free_cancellation_before']
        );
        $this->assertCount(1, $cancellation['policies']);
    }

    public function test_handles_missing_optional_fields(): void
    {
        $result = $this->normalizer->normalize([]);

        $this->assertNull($result['room']['name']);
        $this->assertSame([], $result['room']['attributes']);
        $this->assertSame([], $result['supplier']['payment_options']);
        $this->assertSame([], $result['supplier']['daily_prices']);
    }

    public function test_public_preview_never_exposes_supplier_pricing(): void
    {
        $normalized = $this->normalizer->normalize([
            'room_name' => 'Deluxe Room',
            'meal' => 'breakfast',
            'match_hash' => 'SECRET_MATCH_HASH',
            'search_hash' => 'SECRET_SEARCH_HASH',
            'daily_prices' => ['100.00'],
            'payment_options' => [
                'payment_types' => [
                    [
                        'amount' => '100.00',
                        'currency_code' => 'USD',
                        'commission_info' => ['amount' => '15.00'],
                    ],
                ],
            ],
        ]);

        $preview = $this->normalizer->publicPreview($normalized);

        $this->assertSame('Deluxe Room', $preview['room_name']);
        $this->assertNull($preview['selling_price']);
        $this->assertFalse($preview['booking_enabled']);

        foreach ([
            'supplier',
            'payment_options',
            'daily_prices',
            'match_hash',
            'search_hash',
            'commission_info',
        ] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $preview);
        }

        $json = json_encode($preview);

        $this->assertStringNotContainsString('SECRET_MATCH_HASH', $json);
        $this->assertStringNotContainsString('SECRET_SEARCH_HASH', $json);
        $this->assertStringNotContainsString('100.00', $json);
        $this->assertStringNotContainsString('15.00', $json);
    }



public function test_hotelpage_book_hash_is_preserved_internally(): void
{
    $normalized = $this->normalizer->normalize([
        'room_name' => 'Deluxe Room',
        'book_hash' => 'h-private-hotelpage-hash',
        'match_hash' => 'm-private-match-hash',
    ]);

    $this->assertSame(
        'h-private-hotelpage-hash',
        $normalized['supplier']['book_hash']
    );

    $preview = $this->normalizer->publicPreview($normalized);

    $this->assertArrayNotHasKey('supplier', $preview);
    $this->assertArrayNotHasKey('book_hash', $preview);

    $this->assertStringNotContainsString(
        'h-private-hotelpage-hash',
        json_encode($preview)
    );
}

}
