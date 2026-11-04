<?php

namespace Tests\Feature;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RateHawkClientTest extends TestCase
{
    private function configureClient(): void
    {
        config([
            'services.ratehawk.environment' => 'sandbox',
            'services.ratehawk.sandbox_url' => 'https://sandbox.example.test',
            'services.ratehawk.key_id' => 'test-id',
            'services.ratehawk.api_key' => 'test-secret',
        ]);
    }

    public function test_successful_api_response(): void
    {
        $this->configureClient();

        Http::preventStrayRequests();
        Http::fake([
            'sandbox.example.test/*' => Http::response([
                'data' => ['hotels' => []],
            ], 200),
        ]);

        $result = app(RateHawkClient::class)->post(
            '/api/b2b/v3/search/serp/hotels/',
            ['test' => true]
        );

        $this->assertSame(['hotels' => []], $result['data']);

        Http::assertSentCount(1);
    }

    public function test_missing_credentials_are_rejected(): void
    {
        $this->configureClient();
        config(['services.ratehawk.api_key' => null]);

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('credentials are not configured');

        app(RateHawkClient::class)->post('/api/b2b/v3/test/', []);
    }

    public function test_unsafe_api_path_is_rejected(): void
    {
        $this->configureClient();

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid RateHawk API path');

        app(RateHawkClient::class)->post('/api/b2b/v3/../secret', []);
    }

    public function test_http_401_is_rejected(): void
    {
        $this->assertHttpErrorIsRejected(401);
    }

    public function test_http_500_is_rejected(): void
    {
        $this->assertHttpErrorIsRejected(500);
    }

    private function assertHttpErrorIsRejected(int $status): void
    {
        $this->configureClient();

        Http::fake([
            '*' => Http::response(['error' => 'failure'], $status),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("HTTP {$status}");

        app(RateHawkClient::class)->post('/api/b2b/v3/test/', []);
    }

    public function test_invalid_json_is_rejected(): void
    {
        $this->configureClient();

        Http::fake([
            '*' => Http::response('not-json', 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid RateHawk JSON response');

        app(RateHawkClient::class)->post('/api/b2b/v3/test/', []);
    }

    public function test_connection_failure_is_reported(): void
    {
        $this->configureClient();

        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        $this->expectException(ConnectionException::class);

        app(RateHawkClient::class)->post('/api/b2b/v3/test/', []);
    }
}
