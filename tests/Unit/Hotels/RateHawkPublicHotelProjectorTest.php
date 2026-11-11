<?php

namespace Tests\Unit\Hotels;

use App\Services\Hotels\RateHawkPublicHotelProjector;
use PHPUnit\Framework\TestCase;

final class RateHawkPublicHotelProjectorTest extends TestCase
{
    public function test_public_preview_does_not_leak_supplier_fields(): void
    {
        $content = [
            'name' => 'Test Hotel', 'address' => 'Dubai', 'star_rating' => 5,
            'images' => ['https://example.test/{size}/hotel.jpg'],
            'secret_supplier_cost' => '10.00', 'hid' => 12345,
        ];
        $result = (new RateHawkPublicHotelProjector())->preview(0, [
            ['room_name' => 'Standard', 'selling_price' => null, 'booking_enabled' => false],
        ], $content);
        self::assertSame('Test Hotel', $result['name']);
        self::assertSame('https://example.test/640x400/hotel.jpg', $result['image']);
        self::assertFalse($result['booking_enabled']);
        self::assertStringNotContainsString('secret_supplier_cost', json_encode($result));
        self::assertStringNotContainsString('12345', json_encode($result));
    }

    public function test_rejects_non_https_image_url(): void
    {
        $result = (new RateHawkPublicHotelProjector())->preview(1, [], [
            'images' => ['javascript:alert(1)'],
        ]);
        self::assertNull($result['image']);
    }
}
