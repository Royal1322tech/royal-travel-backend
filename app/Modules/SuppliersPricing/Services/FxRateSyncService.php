<?php

namespace App\Modules\SuppliersPricing\Services;

use App\Modules\SuppliersPricing\Contracts\FxRateProviderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FxRateSyncService
{
    public function __construct(
        private readonly FxRateProviderInterface $provider
    ) {
    }

    public function sync(
        string $baseCurrency = 'USD',
        array $quoteCurrencies = ['EGP', 'SAR']
    ): int {
        $baseCurrency = strtoupper(trim($baseCurrency));

        $quoteCurrencies = array_values(array_unique(
            array_map(
                fn ($currency) => strtoupper(trim($currency)),
                $quoteCurrencies
            )
        ));

        if (
            !preg_match('/^[A-Z]{3}$/', $baseCurrency)
            || empty($quoteCurrencies)
        ) {
            throw new RuntimeException('Invalid currency selection.');
        }

        foreach ($quoteCurrencies as $currency) {
            if (
                !preg_match('/^[A-Z]{3}$/', $currency)
                || $currency === $baseCurrency
            ) {
                throw new RuntimeException('Invalid quote currency.');
            }
        }

        $currencies = array_merge(
            [$baseCurrency],
            $quoteCurrencies
        );

        $enabledCount = DB::table('rt_currencies')
            ->whereIn('code', $currencies)
            ->where('is_enabled', true)
            ->count();

        if ($enabledCount !== count($currencies)) {
            throw new RuntimeException(
                'One or more currencies are disabled or unavailable.'
            );
        }

        $result = $this->provider->fetchLatestRates(
            $baseCurrency,
            $quoteCurrencies
        );

        if (($result['base_currency'] ?? null) !== $baseCurrency) {
            throw new RuntimeException('Provider base currency mismatch.');
        }

        $effectiveAt = CarbonImmutable::parse(
            $result['effective_at']
        )->utc();

        $now = CarbonImmutable::now('UTC');

        if (
            $effectiveAt->isFuture()
            || $effectiveAt->lt($now->subHours(24))
        ) {
            throw new RuntimeException(
                'Exchange rates are stale or dated in the future.'
            );
        }

        $expiresAt = $effectiveAt->addHours(24);

        $rows = [];

        foreach ($quoteCurrencies as $currency) {
            $rate = $result['rates'][$currency] ?? null;

            if (
                !is_string($rate)
                || !preg_match(
                    '/^\d{1,12}(\.\d{1,12})?$/',
                    $rate
                )
                || bccomp($rate, '0', 12) <= 0
            ) {
                throw new RuntimeException(
                    'Invalid exchange rate for ' . $currency
                );
            }

            $rows[] = [
                'base_currency' => $baseCurrency,
                'quote_currency' => $currency,
                'rate_type' => 'reference',
                'rate' => $rate,
                'source' => $result['source'],
                'effective_at' => $effectiveAt,
                'expires_at' => $expiresAt,
                'recorded_at' => $now,
            ];
        }

        return DB::transaction(function () use ($rows) {
            $inserted = 0;

            foreach ($rows as $row) {
                $exists = DB::table('rt_fx_rates')
                    ->where('base_currency', $row['base_currency'])
                    ->where('quote_currency', $row['quote_currency'])
                    ->where('rate_type', 'reference')
                    ->where('source', $row['source'])
                    ->where('effective_at', $row['effective_at'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('rt_fx_rates')->insert($row);
                $inserted++;
            }

            return $inserted;
        });
    }
}
