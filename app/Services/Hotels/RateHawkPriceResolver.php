<?php

namespace App\Services\Hotels;

/**
 * Certifies a RateHawk supplier total only when every amount reconciles.
 *
 * Verified means: one USD deposit payment whose amount equals the shown amount and the sum of the
 * daily prices, VAT that is either absent or already inside that amount, and taxes that are either
 * included by the supplier or explicitly payable at the property (returned separately, never added
 * to or hidden in the supplier total). Anything else stays Price on request.
 *
 * `reason` is an internal diagnostic code (null when verified). Never return it, or any supplier
 * amount, from a public API.
 */
final class RateHawkPriceResolver
{
    public const REASONS = [
        'payment_options_ambiguous',
        'supplier_currency_not_usd',
        'supplier_amount_invalid',
        'payment_type_not_deposit',
        'show_amount_mismatch',
        'daily_prices_missing',
        'daily_prices_invalid',
        'daily_prices_mismatch',
        'vat_data_missing',
        'vat_not_reconciled',
        'tax_structure_unsupported',
    ];

    public function resolve(array $normalized): ?array
    {
        $supplier = $normalized['supplier'] ?? null;
        if (!is_array($supplier)) {
            return null;
        }
        $payments = $supplier['payment_options'] ?? null;
        if (!is_array($payments) || $payments === [] || !array_is_list($payments)) {
            return null;
        }

        // One payment option, or exactly one deposit option among several (the B2B settlement method).
        if (count($payments) === 1) {
            $payment = $payments[0];
        } else {
            $deposits = array_values(array_filter(
                $payments,
                static fn ($option) => is_array($option) && ($option['type'] ?? null) === 'deposit'
            ));
            if (count($deposits) !== 1) {
                return self::unverified(null, null, 'payment_options_ambiguous');
            }
            $payment = $deposits[0];
        }
        if (!is_array($payment)) {
            return null;
        }

        $amount = $payment['supplier_amount'] ?? null;
        $type = $payment['type'] ?? null;
        if (($payment['supplier_currency'] ?? null) !== 'USD') {
            return self::unverified(null, $type, 'supplier_currency_not_usd');
        }
        if (!self::positiveDecimal($amount)) {
            return self::unverified(null, $type, 'supplier_amount_invalid');
        }
        $amount = (string) $amount;

        if ($type !== 'deposit') {
            return self::unverified($amount, $type, 'payment_type_not_deposit');
        }
        if (($payment['supplier_show_currency'] ?? null) !== 'USD'
            || !self::positiveDecimal($payment['supplier_show_amount'] ?? null)
            || bccomp($amount, (string) $payment['supplier_show_amount'], 2) !== 0) {
            return self::unverified($amount, $type, 'show_amount_mismatch');
        }

        $daily = $supplier['daily_prices'] ?? null;
        if (!is_array($daily) || $daily === [] || !array_is_list($daily)) {
            return self::unverified($amount, $type, 'daily_prices_missing');
        }
        $sum = '0.00';
        foreach ($daily as $price) {
            if (!self::nonNegativeDecimal($price)) {
                return self::unverified($amount, $type, 'daily_prices_invalid');
            }
            $sum = bcadd($sum, (string) $price, 2);
        }
        if (bccomp($sum, $amount, 2) !== 0) {
            return self::unverified($amount, $type, 'daily_prices_mismatch');
        }

        $vatReason = self::vatReason($payment['vat_data'] ?? null, $amount);
        if ($vatReason !== null) {
            return self::unverified($amount, $type, $vatReason);
        }

        $propertyTaxes = self::propertyTaxes($payment['tax_data'] ?? null);
        if ($propertyTaxes === null) {
            return self::unverified($amount, $type, 'tax_structure_unsupported');
        }

        return [
            'amount' => $amount,
            'currency' => 'USD',
            'payment_type' => $type,
            'pricing_verified' => true,
            'reason' => null,
            'taxes_at_property' => $propertyTaxes,
        ];
    }

    private static function unverified(?string $amount, mixed $type, string $reason): array
    {
        return [
            'amount' => $amount,
            'currency' => 'USD',
            'payment_type' => $type,
            'pricing_verified' => false,
            'reason' => $reason,
            'taxes_at_property' => [],
        ];
    }

    /**
     * Null when VAT reconciles with the supplier total:
     * - not included and not applied, with no nonzero amount (no VAT), or
     * - included in the total, with a parseable amount no larger than the total.
     * VAT applied on top of the total, or any other combination, does not reconcile.
     */
    private static function vatReason(mixed $vat, string $total): ?string
    {
        if (!is_array($vat)) {
            return 'vat_data_missing';
        }
        $included = $vat['included'] ?? null;
        $applied = $vat['applied'] ?? null;
        if (!is_bool($included) || !is_bool($applied)) {
            return 'vat_not_reconciled';
        }
        foreach (['amount', 'value'] as $field) {
            if (array_key_exists($field, $vat) && $vat[$field] !== null && !self::nonNegativeDecimal($vat[$field])) {
                return 'vat_not_reconciled';
            }
        }
        $vatAmount = (string) ($vat['amount'] ?? $vat['value'] ?? '0');

        if ($included === false && $applied === false) {
            return bccomp($vatAmount, '0', 2) === 0 ? null : 'vat_not_reconciled';
        }
        if ($included === true) {
            return bccomp($vatAmount, $total, 2) <= 0 ? null : 'vat_not_reconciled';
        }

        return 'vat_not_reconciled';
    }

    /**
     * Taxes payable at the property (not included by the supplier), grouped by currency, or null when
     * the structure can't be reconciled. Accepted shapes: [] / {} / {"taxes": []} / {"taxes": [...]}
     * where every tax has a boolean included_by_supplier, a decimal amount and a 3-letter currency.
     * Included taxes are already inside the supplier total and are not listed.
     *
     * @return list<array{amount: string, currency: string}>|null
     */
    private static function propertyTaxes(mixed $taxData): ?array
    {
        // Missing tax data is unknown, not "no taxes".
        if ($taxData === []) {
            return [];
        }
        if (!is_array($taxData) || array_diff(array_keys($taxData), ['taxes']) !== []) {
            return null;
        }
        $taxes = $taxData['taxes'] ?? [];
        if (!is_array($taxes) || !array_is_list($taxes)) {
            return null;
        }

        $byCurrency = [];
        foreach ($taxes as $tax) {
            if (!is_array($tax) || !is_bool($tax['included_by_supplier'] ?? null)) {
                return null;
            }
            $amount = $tax['amount'] ?? null;
            $currency = $tax['currency_code'] ?? null;
            if (!self::nonNegativeDecimal($amount) || !is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
                return null;
            }
            if ($tax['included_by_supplier'] === true) {
                continue;
            }
            $byCurrency[$currency] = bcadd($byCurrency[$currency] ?? '0.00', (string) $amount, 2);
        }

        $list = [];
        foreach ($byCurrency as $currency => $amount) {
            if (bccomp($amount, '0', 2) > 0) {
                $list[] = ['amount' => $amount, 'currency' => $currency];
            }
        }

        return $list;
    }

    private static function positiveDecimal(mixed $value): bool
    {
        return self::nonNegativeDecimal($value) && bccomp((string) $value, '0', 2) > 0;
    }

    private static function nonNegativeDecimal(mixed $value): bool
    {
        return (is_string($value) || is_int($value))
            && preg_match('/^\d+(?:\.\d{1,2})?$/D', (string) $value) === 1;
    }
}
