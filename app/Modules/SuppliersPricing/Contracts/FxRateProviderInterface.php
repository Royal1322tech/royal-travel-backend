<?php

namespace App\Modules\SuppliersPricing\Contracts;

interface FxRateProviderInterface
{
    /**
     * Fetch reference exchange rates for the given currencies.
     *
     * @param array<int, string> $quoteCurrencies
     *
     * @return array{
     *     base_currency: string,
     *     rates: array<string, string>,
     *     source: string,
     *     effective_at: string
     * }
     */
    public function fetchLatestRates(
        string $baseCurrency,
        array $quoteCurrencies
    ): array;
}
