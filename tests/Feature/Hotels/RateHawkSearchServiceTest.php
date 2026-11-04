<?php

namespace Tests\Feature\Hotels;

use App\Services\Hotels\RateHawkSearchService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RateHawkSearchServiceTest extends TestCase
{
    private function params(): array
    {
        return [
            'checkin' => '2027-03-10',
            'checkout' => '2027-03-12',
            'latitude' => 25.2048,
            'longitude' => 55.2708,
            'radius' => 5000,
            'guests' => [
                ['adults' => 2, 'children' => [8]],
            ],
        ];
    }

    private function serviceWithResponse(array $response): RateHawkSearchService
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')
            ->once()
            ->with(
                '/api/b2b/v3/search/serp/geo/',
                Mockery::on(fn ($payload) =>
                    $payload['checkin'] === '2027-03-10'
                    && $payload['checkout'] === '2027-03-12'
                    && $payload['currency'] === 'USD'
                    && $payload['guests'][0]['children'] === [8]
                )
            )
            ->andReturn($response);

        return new RateHawkSearchService($client);
    }

    public function test_successful_search_returns_hotels(): void
    {
        $service = $this->serviceWithResponse([
            'status' => 'ok',
            'data' => [
                'hotels' => [
                    [
                        'id' => 'hotel-1',
                        'hid' => 123,
                        'rates' => [['room_name' => 'Deluxe']],
                        'internal_cost' => 100,
                    ],
                ],
            ],
        ]);

        $result = $service->searchByGeo($this->params());

        $this->assertSame(1, $result['total']);
        $this->assertSame('ratehawk', $result['supplier']);
        $this->assertSame('hotel-1', $result['hotels'][0]['id']);
        $this->assertArrayNotHasKey('internal_cost', $result['hotels'][0]);
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $params = $this->params();
        $params['checkout'] = $params['checkin'];

        $this->expectException(InvalidArgumentException::class);

        (new RateHawkSearchService(
            Mockery::mock(RateHawkClient::class)
        ))->searchByGeo($params);
    }

    public function test_invalid_child_age_is_rejected(): void
    {
        $params = $this->params();
        $params['guests'][0]['children'] = [18];

        $this->expectException(InvalidArgumentException::class);

        (new RateHawkSearchService(
            Mockery::mock(RateHawkClient::class)
        ))->searchByGeo($params);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $params = $this->params();
        $params['latitude'] = 120;

        $this->expectException(InvalidArgumentException::class);

        (new RateHawkSearchService(
            Mockery::mock(RateHawkClient::class)
        ))->searchByGeo($params);
    }

    public function test_supplier_error_status_is_rejected(): void
    {
        $service = $this->serviceWithResponse([
            'status' => 'error',
            'data' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RateHawk search was not successful');

        $service->searchByGeo($this->params());
    }

    public function test_invalid_hotels_response_is_rejected(): void
    {
        $service = $this->serviceWithResponse([
            'status' => 'ok',
            'data' => ['hotels' => 'invalid'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected RateHawk hotels response');

        $service->searchByGeo($this->params());
    }
}
