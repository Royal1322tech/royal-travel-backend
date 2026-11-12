<?php

namespace App\Services\Hotels;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use InvalidArgumentException;
use RuntimeException;

final class RateHawkPrebookService
{
    public function __construct(
        private readonly RateHawkClient $client
    ) {
    }

    public function prebook(
        string $bookHash,
        int $expectedHid
    ): array {
        if (
            ! str_starts_with($bookHash, 'h-') ||
            strlen($bookHash) <= 2 ||
            strlen($bookHash) > 256 ||
            preg_match('/\s/', $bookHash)
        ) {
            throw new InvalidArgumentException(
                'Invalid RateHawk hotelpage book hash.'
            );
        }

        if ($expectedHid <= 0) {
            throw new InvalidArgumentException(
                'Invalid RateHawk hotel ID.'
            );
        }

        $response = $this->client->post(
            '/api/b2b/v3/hotel/prebook/',
            [
                'hash' => $bookHash,
                'price_increase_percent' => 0,
            ],
            60
        );

        if (($response['status'] ?? null) !== 'ok') {
            throw new RuntimeException(
                'RateHawk prebook request failed.'
            );
        }

        $hotels = $response['data']['hotels'] ?? null;

        if (
            ! is_array($hotels) ||
            count($hotels) !== 1 ||
            ! is_array($hotels[0])
        ) {
            throw new RuntimeException(
                'Unexpected RateHawk prebook hotels.'
            );
        }

        $hotel = $hotels[0];

        if (
            ! isset($hotel['hid']) ||
            ! is_int($hotel['hid']) ||
            $hotel['hid'] !== $expectedHid
        ) {
            throw new RuntimeException(
                'RateHawk prebook hotel mismatch.'
            );
        }

        $rates = $hotel['rates'] ?? null;

        if (
            ! is_array($rates) ||
            count($rates) !== 1 ||
            ! is_array($rates[0])
        ) {
            throw new RuntimeException(
                'Unexpected RateHawk prebook rates.'
            );
        }

        $rate = $rates[0];
        $prebookHash = $rate['book_hash'] ?? null;

        if (
            ! is_string($prebookHash) ||
            ! str_starts_with($prebookHash, 'p-') ||
            strlen($prebookHash) <= 2 ||
            strlen($prebookHash) > 256 ||
            preg_match('/\s/', $prebookHash)
        ) {
            throw new RuntimeException(
                'Invalid RateHawk prebook response hash.'
            );
        }

        $changes = $response['data']['changes'] ?? null;

        if (
            ! is_array($changes) ||
            ! is_bool($changes['price_changed'] ?? null)
        ) {
            throw new RuntimeException(
                'Invalid RateHawk prebook change information.'
            );
        }

        if (
            ! is_array($rate['payment_options'] ?? null) ||
            ! is_array(
                $rate['payment_options']['payment_types'] ?? null
            ) ||
            $rate['payment_options']['payment_types'] === []
        ) {
            throw new RuntimeException(
                'RateHawk prebook payment options are missing.'
            );
        }

        return [
            'supplier' => 'ratehawk',
            'environment' => config(
                'services.ratehawk.environment',
                'sandbox'
            ),
            'hotel' => [
                'id' => $hotel['id'] ?? null,
                'hid' => $hotel['hid'],
            ],
            'price_changed' => $changes['price_changed'],
            'rate' => $rate,
        ];
    }
}
