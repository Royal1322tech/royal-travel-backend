<?php

namespace App\Services\Hotels;

final class RateHawkPriceResolver
{
    /**
     * Resolve a USD payment amount for internal pricing review.
     *
     * This does not certify the amount as the final
     * supplier cost or customer-payable total.
     */
    public function resolve(array $normalized): ?array
    {
        $payments = $normalized['supplier']['payment_options'] ?? [];

        if (!is_array($payments)) {
            return null;
        }

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $currency = strtoupper(
                (string) ($payment['supplier_currency'] ?? '')
            );

            $amount = $payment['supplier_amount'] ?? null;

            if (
                $currency !== 'USD' ||
                !is_string($amount) && !is_int($amount) && !is_float($amount) ||
                !is_numeric($amount)
            ) {
                continue;
            }

            $value = (float) $amount;

            if (!is_finite($value) || $value <= 0) {
                continue;
            }

            return [
                'amount' => (string) $amount,
                'currency' => 'USD',
                'payment_type' => $payment['type'] ?? null,
                'vat_included' => $payment['vat_data']['included'] ?? null,
                'vat_applied' => $payment['vat_data']['applied'] ?? null,
                'has_additional_tax_data' =>
                    !empty($payment['tax_data']),
                'pricing_verified' => false,
            ];
        }

        return null;
    }
}
