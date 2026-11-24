<?php

namespace App\Services\Hotels;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

class RateHawkSearchService
{
    public function __construct(
        private readonly RateHawkClient $client
    ) {
    }

    /**
     * Supplier-facing geographic hotel search.
     *
     * Channel authorization, company-specific pricing,
     * and public result formatting belong to the
     * application layer, not this supplier service.
     */
    public function searchByGeo(array $params): array
    {
        $base = $this->basePayload($params);

        $latitude = $params['latitude'] ?? null;
        $longitude = $params['longitude'] ?? null;
        $radius = $params['radius'] ?? 10000;

        if (
            ! is_numeric($latitude) ||
            ! is_numeric($longitude) ||
            (float) $latitude < -90 ||
            (float) $latitude > 90 ||
            (float) $longitude < -180 ||
            (float) $longitude > 180 ||
            ! is_numeric($radius) ||
            (float) $radius <= 0
        ) {
            throw new InvalidArgumentException(
                'Invalid geographic search parameters.'
            );
        }

        // Historical payload key order: location before currency.
        $payload = $this->withoutCurrency($base) + [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'radius' => (int) $radius,
            'currency' => $base['currency'],
        ];

        return $this->send('/api/b2b/v3/search/serp/geo/', $payload);
    }

    /**
     * Supplier-facing region search (POST /api/b2b/v3/search/serp/region/).
     * region_id comes from RateHawk multicomplete data.regions[].id.
     */
    public function searchByRegion(array $params): array
    {
        $base = $this->basePayload($params);
        $regionId = $params['region_id'] ?? null;

        if (! is_int($regionId) || $regionId < 1) {
            throw new InvalidArgumentException('Invalid region id.');
        }

        $payload = $this->withoutCurrency($base) + [
            'region_id' => $regionId,
            'currency' => $base['currency'],
        ];

        return $this->send('/api/b2b/v3/search/serp/region/', $payload);
    }

    private function basePayload(array $params): array
    {
        $checkin = (string) ($params['checkin'] ?? '');
        $checkout = (string) ($params['checkout'] ?? '');

        if (
            ! $this->isValidDate($checkin) ||
            ! $this->isValidDate($checkout) ||
            $checkout <= $checkin
        ) {
            throw new InvalidArgumentException(
                'Invalid check-in or check-out dates.'
            );
        }

        $guests = $params['guests'] ?? [
            [
                'adults' => 2,
                'children' => [],
            ],
        ];

        if (! is_array($guests) || $guests === []) {
            throw new InvalidArgumentException(
                'At least one room is required.'
            );
        }

        foreach ($guests as $room) {
            if (
                ! is_array($room) ||
                ! isset($room['adults']) ||
                ! is_int($room['adults']) ||
                $room['adults'] < 1 ||
                ! isset($room['children']) ||
                ! is_array($room['children'])
            ) {
                throw new InvalidArgumentException(
                    'Invalid guests configuration.'
                );
            }

            foreach ($room['children'] as $age) {
                if (! is_int($age) || $age < 0 || $age > 17) {
                    throw new InvalidArgumentException(
                        'Invalid child age.'
                    );
                }
            }
        }

        return [
            'checkin' => $checkin,
            'checkout' => $checkout,
            'residency' => strtolower(
                (string) ($params['residency'] ?? 'eg')
            ),
            'language' => strtolower(
                (string) ($params['language'] ?? 'en')
            ),
            'guests' => $guests,
            'currency' => strtoupper(
                (string) ($params['currency'] ?? 'USD')
            ),
        ];
    }

    private function withoutCurrency(array $base): array
    {
        unset($base['currency']);

        return $base;
    }

    private function send(string $path, array $payload): array
    {
        $response = $this->client->post(
            $path,
            $payload
        );

        if (($response['status'] ?? null) !== 'ok') {
            throw new RuntimeException(
                'RateHawk search was not successful.'
            );
        }

        $hotels = $response['data']['hotels'] ?? [];

        if (! is_array($hotels)) {
            throw new RuntimeException(
                'Unexpected RateHawk hotels response.'
            );
        }

        return [
            'supplier' => 'ratehawk',
            'environment' => config(
                'services.ratehawk.environment',
                'sandbox'
            ),
            'search' => $payload,
            'total' => count($hotels),
            'hotels' => array_map(
                fn (array $hotel): array => [
                    'id' => $hotel['id'] ?? null,
                    'hid' => $hotel['hid'] ?? null,
                    'rates' => $hotel['rates'] ?? [],
                ],
                $hotels
            ),
        ];
    }

    private function isValidDate(string $date): bool
    {
        try {
            return Carbon::createFromFormat(
                '!Y-m-d',
                $date
            )->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
    }
}
