<?php

declare(strict_types=1);

namespace Tests\Feature\Hotels;

use App\Services\Hotels\RateHawkDestinationService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Destination autocomplete service. RateHawkClient is ALWAYS mocked: these
 * tests use the documented multicomplete shape (data.regions[] = {id, name,
 * type, country_code}) and do not prove the live supplier response.
 */
final class RateHawkDestinationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.ratehawk.environment' => 'sandbox',
            'services.ratehawk.destination_scope' => 'all',
        ]);
    }

    private function supplierResponse(): array
    {
        return [
            'status' => 'ok',
            'error' => null,
            'data' => [
                'hotels' => [['id' => 'some_hotel', 'hid' => 123, 'name' => 'Some Hotel', 'region_id' => 6053839]],
                'regions' => [
                    ['id' => 6053839, 'name' => 'Dubai', 'type' => 'City', 'country_code' => 'AE'],
                    ['id' => 197, 'name' => 'United Arab Emirates', 'type' => 'Country', 'country_code' => 'AE'],
                    ['id' => 6308838, 'name' => 'Dubai Marina', 'type' => 'Neighborhood', 'country_code' => 'ae'],
                    ['id' => 9001, 'name' => 'Dubai International Airport', 'type' => 'Airport', 'country_code' => 'AE'],
                    ['id' => 2, 'name' => 'Asia', 'type' => 'Continent', 'country_code' => null],
                    ['id' => 'not-a-number', 'name' => 'Broken', 'type' => 'City', 'country_code' => 'AE'],
                    ['id' => 6053839, 'name' => 'Dubai duplicate', 'type' => 'City', 'country_code' => 'AE'],
                ],
            ],
        ];
    }

    /** @return RateHawkClient&MockInterface */
    private function expectSupplier(string $query, string $language, mixed $response, int $times = 1): MockInterface
    {
        $client = Mockery::mock(RateHawkClient::class);
        $expectation = $client->shouldReceive('post')->times($times)
            ->with('/api/b2b/v3/search/multicomplete/', ['query' => $query, 'language' => $language], 10);
        $response instanceof \Throwable ? $expectation->andThrow($response) : $expectation->andReturn($response);
        $this->app->instance(RateHawkClient::class, $client);

        return $client;
    }

    private function forbidSupplier(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');
        $this->app->instance(RateHawkClient::class, $client);
    }

    private function service(): RateHawkDestinationService
    {
        return app(RateHawkDestinationService::class);
    }

    public function test_maps_documented_region_fields_to_stable_contract(): void
    {
        $this->expectSupplier('dub', 'en', $this->supplierResponse());

        $result = $this->service()->autocomplete('dub', 'en', 10);

        $this->assertTrue($result['success']);
        $this->assertSame('all', $result['scope']);
        $this->assertSame([
            ['id' => 'region:6053839', 'type' => 'city', 'name' => 'Dubai', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'parent' => null],
            ['id' => 'region:197', 'type' => 'country', 'name' => 'United Arab Emirates', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'parent' => null],
            ['id' => 'region:6308838', 'type' => 'neighborhood', 'name' => 'Dubai Marina', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'parent' => null],
            ['id' => 'region:9001', 'type' => 'airport', 'name' => 'Dubai International Airport', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'parent' => null],
        ], $result['destinations']);
    }

    public function test_never_returns_supplier_hotels_or_extra_fields(): void
    {
        $this->expectSupplier('dub', 'en', $this->supplierResponse());
        $result = $this->service()->autocomplete('dub');
        foreach ($result['destinations'] as $destination) {
            $this->assertSame(['id', 'type', 'name', 'country', 'country_code', 'parent'], array_keys($destination));
            $this->assertStringStartsWith('region:', $destination['id']);
        }
    }

    public function test_arabic_language_is_sent_and_country_is_localized(): void
    {
        $this->expectSupplier('دبي', 'ar', ['status' => 'ok', 'data' => ['regions' => [
            ['id' => 6053839, 'name' => 'دبي', 'type' => 'City', 'country_code' => 'AE'],
        ]]]);

        $result = $this->service()->autocomplete('دبي', 'ar');

        $this->assertSame('دبي', $result['destinations'][0]['name']);
        $this->assertSame('الإمارات العربية المتحدة', $result['destinations'][0]['country']);
    }

    public function test_limit_truncates_results(): void
    {
        $this->expectSupplier('dub', 'en', $this->supplierResponse());
        $this->assertCount(2, $this->service()->autocomplete('dub', 'en', 2)['destinations']);
    }

    public function test_minimum_and_maximum_query_length_are_enforced_without_supplier_call(): void
    {
        $this->forbidSupplier();
        foreach (['d', ' d ', '', str_repeat('a', 101)] as $query) {
            try {
                $this->service()->autocomplete($query);
                $this->fail('Expected rejection for query of length ' . mb_strlen($query));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->forbidSupplier();
        $this->expectException(InvalidArgumentException::class);
        $this->service()->autocomplete('dubai', 'fr');
    }

    public function test_null_regions_as_in_sandbox_returns_empty_success(): void
    {
        $this->expectSupplier('dubai', 'en', ['status' => 'ok', 'error' => null, 'data' => ['hotels' => [], 'regions' => null]]);
        $result = $this->service()->autocomplete('dubai');
        $this->assertTrue($result['success']);
        $this->assertSame([], $result['destinations']);
    }

    public function test_empty_regions_returns_empty_success(): void
    {
        $this->expectSupplier('zzqx', 'en', ['status' => 'ok', 'data' => ['regions' => []]]);
        $this->assertSame([], $this->service()->autocomplete('zzqx')['destinations']);
    }

    public function test_supplier_timeout_returns_generic_failure_and_logs_without_secrets(): void
    {
        config(['services.ratehawk.key_id' => 'SECRET-KEY-ID', 'services.ratehawk.api_key' => 'SECRET-API-KEY']);
        Log::spy();
        $this->expectSupplier('dubai', 'en', new RuntimeException('cURL error 28: timeout for SECRET-API-KEY'));

        $result = $this->service()->autocomplete('dubai');

        $this->assertFalse($result['success']);
        $this->assertSame('DESTINATION_SEARCH_UNAVAILABLE', $result['code']);
        $this->assertSame([], $result['destinations']);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode([$message, $context]);
            return !str_contains($encoded, 'SECRET') && $context === ['exception' => RuntimeException::class];
        });
    }

    public function test_supplier_error_status_returns_generic_failure(): void
    {
        $this->expectSupplier('dubai', 'en', ['status' => 'error', 'error' => 'invalid_params', 'data' => null]);
        $result = $this->service()->autocomplete('dubai');
        $this->assertFalse($result['success']);
        $this->assertSame('DESTINATION_SEARCH_UNAVAILABLE', $result['code']);
    }

    public function test_successful_results_are_cached_per_query_and_language(): void
    {
        $this->expectSupplier('dub', 'en', $this->supplierResponse());
        $first = $this->service()->autocomplete('dub');
        // Same normalized query (case and whitespace) must not reach the supplier again.
        $second = $this->service()->autocomplete('  DUB ');
        $this->assertSame($first['destinations'], $second['destinations']);
    }

    public function test_different_language_is_a_separate_cache_entry(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()
            ->with('/api/b2b/v3/search/multicomplete/', ['query' => 'dub', 'language' => 'en'], 10)
            ->andReturn($this->supplierResponse());
        $client->shouldReceive('post')->once()
            ->with('/api/b2b/v3/search/multicomplete/', ['query' => 'dub', 'language' => 'ar'], 10)
            ->andReturn($this->supplierResponse());
        $this->app->instance(RateHawkClient::class, $client);

        $this->service()->autocomplete('dub', 'en');
        $this->service()->autocomplete('dub', 'ar');
        $this->service()->autocomplete('dub', 'en');
        $this->service()->autocomplete('dub', 'ar');
    }

    public function test_failures_are_briefly_cached_to_avoid_hammering_the_supplier(): void
    {
        $this->expectSupplier('dubai', 'en', new RuntimeException('HTTP 503'));
        $this->assertFalse($this->service()->autocomplete('dubai')['success']);
        $this->assertFalse($this->service()->autocomplete('dubai')['success']);
    }

    public function test_sandbox_known_scope_returns_only_sandbox_destinations_without_supplier_call(): void
    {
        config(['services.ratehawk.destination_scope' => 'sandbox_known']);
        $this->forbidSupplier();

        $dubai = $this->service()->autocomplete('dub');
        $this->assertTrue($dubai['success']);
        $this->assertSame('sandbox_known', $dubai['scope']);
        $this->assertSame([['id' => 'dubai', 'type' => 'city', 'name' => 'Dubai', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'parent' => null]], $dubai['destinations']);

        $this->assertSame(['los-angeles'], array_column($this->service()->autocomplete('angel')['destinations'], 'id'));
        $this->assertSame(['paris'], array_column($this->service()->autocomplete('باريس', 'ar')['destinations'], 'id'));
        $this->assertSame('باريس', $this->service()->autocomplete('par', 'ar')['destinations'][0]['name']);
        // Cairo is a legacy destination but has no sandbox inventory.
        $this->assertSame([], $this->service()->autocomplete('cairo')['destinations']);
    }

    public function test_unknown_scope_value_falls_back_to_all(): void
    {
        config(['services.ratehawk.destination_scope' => 'everything']);
        $this->assertSame('all', RateHawkDestinationService::scope());
    }

    public function test_resolves_region_tokens_and_legacy_slugs(): void
    {
        $service = $this->service();
        $this->assertSame(['mode' => 'region', 'region_id' => 6053839], $service->resolve('region:6053839'));
        $this->assertSame(['mode' => 'geo', 'latitude' => 25.2048, 'longitude' => 55.2708, 'radius' => 10000], $service->resolve('dubai'));
        $this->assertSame(['mode' => 'geo', 'latitude' => 30.0444, 'longitude' => 31.2357, 'radius' => 10000], $service->resolve('Cairo'));
        $this->assertSame(['mode' => 'geo', 'latitude' => 24.7136, 'longitude' => 46.6753, 'radius' => 10000], $service->resolve('riyadh'));
        foreach (['sharm-el-sheikh', 'sharm_el_sheikh', 'los-angeles', 'los_angeles', 'alexandria', 'hurghada', 'paris'] as $slug) {
            $this->assertSame('geo', $service->resolve($slug)['mode'] ?? null, $slug);
        }
        foreach (['region:0', 'region:-1', 'region:abc', 'region:1234567890123', 'atlantis', '', 'dubai;drop', '../etc'] as $bad) {
            $this->assertNull($service->resolve($bad), $bad);
        }
    }
}
