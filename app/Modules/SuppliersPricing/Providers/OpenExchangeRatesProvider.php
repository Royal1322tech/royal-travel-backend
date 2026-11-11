<?php

namespace App\Modules\SuppliersPricing\Providers;

use App\Modules\SuppliersPricing\Contracts\FxRateProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenExchangeRatesProvider implements FxRateProviderInterface
{
    public function fetchLatestRates(
        string $baseCurrency,
        array $quoteCurrencies
    ): array {
        $baseCurrency = strtoupper(trim($baseCurrency));

        if ($baseCurrency !== 'USD') {
            throw new RuntimeException(
                'Open Exchange Rates provider currently supports USD base only.'
            );
        }

        $appId = config('services.open_exchange_rates.app_id');

        if (!is_string($appId) || trim($appId) === '') {
            throw new RuntimeException(
                'Open Exchange Rates API credentials are not configured.'
            );
        }

        $response = Http::timeout(
            (int) config('services.open_exchange_rates.timeout', 10)
        )->acceptJson()->get(
            config('services.open_exchange_rates.url'),
            ['app_id' => $appId]
        );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Unable to retrieve exchange rates from provider.'
            );
        }

        $data = $response->json();

        if (
            !is_array($data)
            || !isset($data['timestamp'], $data['rates'])
            || !is_numeric($data['timestamp'])
            || !is_array($data['rates'])
            || ($data['base'] ?? null) !== 'USD'
        ) {
            throw new RuntimeException(
                'Invalid exchange rate provider response.'
            );
        }

        $timestamp = filter_var(
            $data['timestamp'],
            FILTER_VALIDATE_INT
        );

        if (
            $timestamp === false
            || $timestamp <= 0
            || $timestamp > time() + 300
        ) {
            throw new RuntimeException(
                'Invalid exchange rate timestamp.'
            );
        }

        $rates = [];

        foreach ($quoteCurrencies as $currency) {
            $currency = strtoupper(trim($currency));

            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new RuntimeException(
                    'Invalid requested currency.'
                );
            }

            if (!array_key_exists($currency, $data['rates'])) {
                throw new RuntimeException(
                    'Requested exchange rate is unavailable.'
                );
            }

            $rate = $data['rates'][$currency];

            if (
                !is_int($rate)
                && !is_float($rate)
                && !is_string($rate)
            ) {
                throw new RuntimeException(
                    'Invalid exchange rate value.'
                );
            }

            if (
                !is_numeric($rate)
                || !is_finite((float) $rate)
                || (float) $rate <= 0
            ) {
                throw new RuntimeException(
                    'Invalid exchange rate value.'
                );
            }

            $rates[$currency] = (string) $rate;
        }

        return [
            'base_currency' => 'USD',
            'rates' => $rates,
            'source' => 'open_exchange_rates',
            'effective_at' => gmdate('Y-m-d H:i:s', $timestamp),
        ];
    }
}
