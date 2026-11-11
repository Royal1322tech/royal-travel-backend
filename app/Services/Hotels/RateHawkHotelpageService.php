<?php

namespace App\Services\Hotels;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use InvalidArgumentException;
use RuntimeException;

final class RateHawkHotelpageService
{
    public function __construct(
        private readonly RateHawkClient $client
    ) {
    }

    public function getRates(array $search, int $hid): array
    {
        if ($hid <= 0) {
            throw new InvalidArgumentException(
                'Invalid RateHawk hotel ID.'
            );
        }

        foreach (['checkin', 'checkout', 'residency', 'guests'] as $field) {
            if (! array_key_exists($field, $search)) {
                throw new InvalidArgumentException(
                    "Missing hotelpage search field: {$field}."
                );
            }
        }

        if (
            ! is_string($search['checkin']) ||
            ! is_string($search['checkout']) ||
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $search['checkin']) ||
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $search['checkout']) ||
            $search['checkout'] <= $search['checkin']
        ) {
            throw new InvalidArgumentException(
                'Invalid hotelpage dates.'
            );
        }

        if (
            ! is_string($search['residency']) ||
            ! preg_match('/^[a-zA-Z]{2}$/', $search['residency']) ||
            ! is_array($search['guests']) ||
            $search['guests'] === []
        ) {
            throw new InvalidArgumentException(
                'Invalid hotelpage search parameters.'
            );
        }

        foreach ($search['guests'] as $room) {
            if (
                ! is_array($room) ||
                ! isset($room['adults']) ||
                ! is_int($room['adults']) ||
                $room['adults'] < 1 ||
                ! isset($room['children']) ||
                ! is_array($room['children'])
            ) {
                throw new InvalidArgumentException(
                    'Invalid hotelpage guests.'
                );
            }

            foreach ($room['children'] as $age) {
                if (! is_int($age) || $age < 0 || $age > 17) {
                    throw new InvalidArgumentException(
                        'Invalid hotelpage child age.'
                    );
                }
            }
        }

        $payload = [
            'checkin' => $search['checkin'],
            'checkout' => $search['checkout'],
            'residency' => strtolower($search['residency']),
            'language' => strtolower(
                (string) ($search['language'] ?? 'en')
            ),
            'guests' => $search['guests'],
            'hid' => $hid,
            'currency' => strtoupper(
                (string) ($search['currency'] ?? 'USD')
            ),
        ];

        $response = $this->client->post(
            '/api/b2b/v3/search/hp/',
            $payload
        );

        if (($response['status'] ?? null) !== 'ok') {
            throw new RuntimeException(
                'RateHawk hotelpage request failed.'
            );
        }

        $hotels = $response['data']['hotels'] ?? null;

        if (! is_array($hotels) || $hotels === []) {
            throw new RuntimeException(
                'RateHawk hotelpage returned no hotels.'
            );
        }

        $hotel = $hotels[0];

        if (
            ! is_array($hotel) ||
            (int) ($hotel['hid'] ?? 0) !== $hid ||
            ! is_array($hotel['rates'] ?? null)
        ) {
            throw new RuntimeException(
                'Unexpected RateHawk hotelpage response.'
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
            'rates' => $hotel['rates'],
        ];
    }
}
