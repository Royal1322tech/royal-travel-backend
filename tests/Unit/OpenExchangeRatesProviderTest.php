<?php

namespace Tests\Unit;

use App\Modules\SuppliersPricing\Providers\OpenExchangeRatesProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenExchangeRatesProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.open_exchange_rates.app_id',
            'fake-test-key'
        );

        config()->set(
            'services.open_exchange_rates.url',
            'https://openexchangerates.org/api/latest.json'
        );

        Http::preventStrayRequests();
    }

    public function test_fetches_valid_exchange_rates(): void
    {
        Http::fake([
            'openexchangerates.org/*' => Http::response([
                'base' => 'USD',
                'timestamp' => time() - 60,
                'rates' => [
                    'EGP' => 50.25,
                    'SAR' => 3.75,
                ],
            ], 200),
        ]);

        $result = (new OpenExchangeRatesProvider())
            ->fetchLatestRates('USD', ['EGP', 'SAR']);

        $this->assertSame('USD', $result['base_currency']);
        $this->assertSame('50.25', $result['rates']['EGP']);
        $this->assertSame('3.75', $result['rates']['SAR']);
        $this->assertSame(
            'open_exchange_rates',
            $result['source']
        );

        $this->assertNotEmpty($result['effective_at']);
        Http::assertSentCount(1);
    }

    public function test_rejects_non_usd_base_currency(): void
    {
        $this->expectException(RuntimeException::class);

        (new OpenExchangeRatesProvider())
            ->fetchLatestRates('EUR', ['EGP']);
    }

    public function test_rejects_negative_exchange_rate(): void
    {
        Http::fake([
            '*' => Http::response([
                'base' => 'USD',
                'timestamp' => time() - 60,
                'rates' => ['EGP' => -50],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        (new OpenExchangeRatesProvider())
            ->fetchLatestRates('USD', ['EGP']);
    }

    public function test_rejects_missing_currency(): void
    {
        Http::fake([
            '*' => Http::response([
                'base' => 'USD',
                'timestamp' => time() - 60,
                'rates' => ['SAR' => 3.75],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        (new OpenExchangeRatesProvider())
            ->fetchLatestRates('USD', ['EGP']);
    }

    public function test_rejects_invalid_provider_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'unexpected' => 'data',
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        (new OpenExchangeRatesProvider())
            ->fetchLatestRates('USD', ['EGP']);
    }

    public function test_rejects_provider_http_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => true,
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);

        (new OpenExchangeRatesProvider())
            ->fetchLatestRates('USD', ['EGP']);
    }
}