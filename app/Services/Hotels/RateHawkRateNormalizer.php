<?php

namespace App\Services\Hotels;

final class RateHawkRateNormalizer
{
    /**
     * Normalize one RateHawk SERP rate for INTERNAL application use only.
     * Never return this complete structure from a B2B/B2C API.
     */
    public function normalize(array $rate): array
    {
        $paymentTypes = $rate['payment_options']['payment_types'] ?? [];
        $paymentTypes = is_array($paymentTypes) ? $paymentTypes : [];

        $payments = [];
        foreach ($paymentTypes as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $cancellation = $payment['cancellation_penalties'] ?? [];
            $cancellation = is_array($cancellation) ? $cancellation : [];

            $payments[] = [
                'type' => $payment['type'] ?? null,
                'by' => $payment['by'] ?? null,
                'requires_card' => $payment['is_need_credit_card_data'] ?? null,
                'requires_cvc' => $payment['is_need_cvc'] ?? null,
                'supplier_amount' => $payment['amount'] ?? null,
                'supplier_currency' => $payment['currency_code'] ?? null,
                'supplier_show_amount' => $payment['show_amount'] ?? null,
                'supplier_show_currency' => $payment['show_currency_code'] ?? null,
                'recommended_price' => $payment['recommended_price'] ?? null,
                'commission_info' => $payment['commission_info'] ?? null,
                'vat_data' => $payment['vat_data'] ?? null,
                'tax_data' => $payment['tax_data'] ?? null,
                'perks' => $payment['perks'] ?? null,
                'cancellation' => [
                    'free_cancellation_before' => $cancellation['free_cancellation_before'] ?? null,
                    'policies' => is_array($cancellation['policies'] ?? null)
                        ? $cancellation['policies']
                        : [],
                ],
            ];
        }

        $roomData = is_array($rate['room_data_trans'] ?? null)
            ? $rate['room_data_trans']
            : [];
        $mealData = is_array($rate['meal_data'] ?? null)
            ? $rate['meal_data']
            : [];

        return [
            'room' => [
                'name' => $rate['room_name'] ?? null,
                'main_name' => $roomData['main_name'] ?? null,
                'main_type' => $roomData['main_room_type'] ?? null,
                'bedding_type' => $roomData['bedding_type'] ?? null,
                'beds' => $roomData['beds'] ?? null,
                'bathroom' => $roomData['bathroom'] ?? null,
                'attributes' => is_array($rate['rg_ext'] ?? null) ? $rate['rg_ext'] : [],
                'amenities' => is_array($rate['amenities_data'] ?? null)
                    ? $rate['amenities_data']
                    : [],
            ],
            'meal' => [
                'code' => $rate['meal'] ?? null,
                'name' => $mealData['value'] ?? null,
                'has_breakfast' => $mealData['has_breakfast'] ?? null,
                'no_child_meal' => $mealData['no_child_meal'] ?? null,
            ],
            'availability' => [
                'allotment' => $rate['allotment'] ?? null,
                'any_residency' => $rate['any_residency'] ?? null,
            ],
            'supplier' => [
                'book_hash' => $rate['book_hash'] ?? null,
                'match_hash' => $rate['match_hash'] ?? null,
                'search_hash' => $rate['search_hash'] ?? null,
                'daily_prices' => is_array($rate['daily_prices'] ?? null)
                    ? $rate['daily_prices']
                    : [],
                'payment_options' => $payments,
                'no_show' => is_array($rate['no_show'] ?? null) ? $rate['no_show'] : null,
                'deposit' => $rate['deposit'] ?? null,
                'is_package' => $rate['is_package'] ?? null,
            ],
        ];
    }

    /**
     * Safe, NON-PRICE public projection for initial room preview.
     * Do not expose supplier identifiers, raw payment data or hashes.
     */
    public function publicPreview(array $normalized): array
    {
        return [
            'room_name' => $normalized['room']['name'] ?? null,
            'meal_name' => $normalized['meal']['name'] ?? $normalized['meal']['code'] ?? null,
            'has_breakfast' => $normalized['meal']['has_breakfast'] ?? null,
            'bedding_type' => $normalized['room']['bedding_type'] ?? null,
            'selling_price' => null,
            'booking_enabled' => false,
        ];
    }
}
