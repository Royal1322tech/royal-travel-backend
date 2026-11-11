<?php

namespace Tests\Feature\Hotels;

use App\Services\Hotels\RateHawkPrebookService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RateHawkPrebookServiceTest extends TestCase
{
    private function response(
        string $hash = 'p-PRIVATE_PREBOOK_HASH',
        int $hid = 123456,
        bool $priceChanged = false
    ): array {
        return [
            'status' => 'ok',
            'data' => [
                'changes' => [
                    'price_changed' => $priceChanged,
                ],
                'hotels' => [
                    [
                        'id' => 'test_hotel',
                        'hid' => $hid,
                        'rates' => [
                            [
                                'book_hash' => $hash,
                                'payment_options' => [
                                    'payment_types' => [
                                        ['type' => 'now'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function serviceWithResponse(array $response): RateHawkPrebookService
    {
        $client = Mockery::mock(RateHawkClient::class);

        $client->shouldReceive('post')
            ->once()
            ->with(
                '/api/b2b/v3/hotel/prebook/',
                [
                    'hash' => 'h-PRIVATE_HOTELPAGE_HASH',
                    'price_increase_percent' => 0,
                ],
                60
            )
            ->andReturn($response);

        return new RateHawkPrebookService($client);
    }

    public function test_prebook_returns_valid_internal_rate(): void
    {
        $service = $this->serviceWithResponse(
            $this->response()
        );

        $result = $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            123456
        );

        $this->assertSame('ratehawk', $result['supplier']);
        $this->assertSame(123456, $result['hotel']['hid']);
        $this->assertFalse($result['price_changed']);
        $this->assertSame(
            'p-PRIVATE_PREBOOK_HASH',
            $result['rate']['book_hash']
        );
    }

    public function test_invalid_hotelpage_hash_is_rejected(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');

        $service = new RateHawkPrebookService($client);

        $this->expectException(InvalidArgumentException::class);

        $service->prebook('invalid-hash', 123456);
    }

    public function test_invalid_hotel_id_is_rejected(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');

        $service = new RateHawkPrebookService($client);

        $this->expectException(InvalidArgumentException::class);

        $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            0
        );
    }

    public function test_supplier_failure_is_rejected(): void
    {
        $response = $this->response();
        $response['status'] = 'error';

        $service = $this->serviceWithResponse($response);

        $this->expectException(RuntimeException::class);

        $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            123456
        );
    }

    public function test_hotel_mismatch_is_rejected(): void
    {
        $service = $this->serviceWithResponse(
            $this->response(hid: 999999)
        );

        $this->expectException(RuntimeException::class);

        $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            123456
        );
    }

    public function test_invalid_prebook_hash_is_rejected(): void
    {
        $service = $this->serviceWithResponse(
            $this->response(hash: 'h-WRONG_HASH_TYPE')
        );

        $this->expectException(RuntimeException::class);

        $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            123456
        );
    }

    public function test_price_change_is_preserved_for_review(): void
    {
        $service = $this->serviceWithResponse(
            $this->response(priceChanged: true)
        );

        $result = $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            123456
        );

        $this->assertTrue($result['price_changed']);
    }

    public function test_missing_payment_options_is_rejected(): void
    {
        $response = $this->response();

        unset(
            $response['data']['hotels'][0]['rates'][0]['payment_options']
        );

        $service = $this->serviceWithResponse($response);

        $this->expectException(RuntimeException::class);

        $service->prebook(
            'h-PRIVATE_HOTELPAGE_HASH',
            123456
        );
    }
}
