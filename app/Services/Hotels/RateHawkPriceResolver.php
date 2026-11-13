<?php

namespace App\Services\Hotels;

final class RateHawkPriceResolver
{
    /**
     * Certifies only a narrow, unambiguous USD supplier payment case.
     * Unsupported tax or payment structures remain Price on request.
     */
    public function resolve(array $normalized): ?array
    {
        $supplier = $normalized['supplier'] ?? null;
        if (!is_array($supplier)) {
            return null;
        }
        $payments = $supplier['payment_options'] ?? null;
        if (!is_array($payments) || count($payments) !== 1 || !is_array($payments[0])) {
            return null;
        }
        $payment = $payments[0];
        $amount = $payment['supplier_amount'] ?? null;
        if (($payment['supplier_currency'] ?? null) !== 'USD' || !self::positiveDecimal($amount)) {
            return null;
        }
        $result = [
            'amount' => (string) $amount,
            'currency' => 'USD',
            'payment_type' => $payment['type'] ?? null,
            'pricing_verified' => false,
        ];
        // A deposit payment type is a supplier balance settlement method,
        // but certification still requires independent reconciliation below.
        if (($payment['type'] ?? null) !== 'deposit') {
            return $result;
        }
        if (($payment['supplier_show_currency'] ?? null) !== 'USD'
            || !self::positiveDecimal($payment['supplier_show_amount'] ?? null)
            || bccomp((string) $amount, (string) $payment['supplier_show_amount'], 2) !== 0) {
            return $result;
        }
        $daily = $supplier['daily_prices'] ?? null;
        if (!is_array($daily) || $daily === [] || !array_is_list($daily)) {
            return $result;
        }
        $sum = '0.00';
        foreach ($daily as $price) {
            if (!self::nonNegativeDecimal($price)) {
                return $result;
            }
            $sum = bcadd($sum, (string) $price, 2);
        }
        if (bccomp($sum, (string) $amount, 2) !== 0) {
            return $result;
        }
        $vat = $payment['vat_data'] ?? null;
        if (!is_array($vat) || ($vat['included'] ?? null) !== false || ($vat['applied'] ?? null) !== false) {
            return $result;
        }
        // VAT may be reported as a separate charge even when flags are false.
        // Do not certify if a nonzero or unparseable VAT amount is present.
        foreach (['amount', 'value'] as $field) {
            if (array_key_exists($field, $vat)
                && (!self::nonNegativeDecimal($vat[$field]) || bccomp((string) $vat[$field], '0', 2) !== 0)) {
                return $result;
            }
        }
        if (($payment['tax_data'] ?? null) !== []) {
            return $result;
        }
        $result['pricing_verified'] = true;
        return $result;
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
