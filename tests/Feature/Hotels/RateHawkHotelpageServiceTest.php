<?php

namespace Tests\Feature\Hotels;

use App\Services\Hotels\RateHawkHotelpageService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RateHawkHotelpageServiceTest extends TestCase
{
    private function search(): array
    {
        return [
            'checkin' => '2027-02-22',
            'checkout' => '2027-02-24',
            'residency' => 'gb',
            'language' => 'en',
            'currency' => 'USD',
            'guests' => [
                [
                    'adults' => 2,
                    'children' => [5],
                ],
            ],
        ];
    }

    private function service(): RateHawkHotelpageService
    {
        return new RateHawkHotelpageService(
            app(RateHawkClient::class)
        );
    }

    private function configureClient(): void
    {
        config()->set('services.ratehawk.environment', 'sandbox');
        config()->set('services.ratehawk.sandbox_base_url', 'https://sandbox.example.test');
        config()->set('services.ratehawk.key_id', 'test-key');
        config()->set('services.ratehawk.api_key', 'test-secret');
    }

    public function test_requests_hotelpage_and_preserves_book_hash(): void
    {
        $this->configureClient();

        Http::preventStrayRequests();

        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'error' => null,
                'data' => [
                    'hotels' => [
                        [
                            'id' => 'test_hotel',
                            'hid' => 10004834,
                            'rates' => [
                                [
                                    'room_name' => 'Deluxe Room',
                                    'book_hash' => 'h-private-test-hash',
                                    'match_hash' => 'm-private-test-hash',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service()->getRates(
            $this->search(),
            10004834
        );

        $this->assertSame(
            'h-private-test-hash',
            $result['rates'][0]['book_hash']
        );

        Http::assertSent(function ($request) {
            return str_ends_with(
                $request->url(),
                '/api/b2b/v3/search/hp/'
            )
                && $request['hid'] === 10004834
                && $request['checkin'] === '2027-02-22'
                && $request['checkout'] === '2027-02-24'
                && $request['guests'][0]['children'] === [5];
        });

        Http::assertSentCount(1);
    }

    public function test_rejects_invalid_hotel_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->getRates($this->search(), 0);
    }

    public function test_rejects_wrong_hotel_in_supplier_response(): void
    {
        $this->configureClient();

        Http::preventStrayRequests();

        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'data' => [
                    'hotels' => [
                        [
                            'id' => 'wrong_hotel',
                            'hid' => 999,
                            'rates' => [],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service()->getRates(
            $this->search(),
            10004834
        );
    }

    public function test_rejects_unsuccessful_supplier_response(): void
    {
        $this->configureClient();

        Http::preventStrayRequests();

        Http::fake([
            '*' => Http::response([
                'status' => 'error',
                'error' => 'invalid_params',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service()->getRates(
            $this->search(),
            10004834
        );
    }
}
