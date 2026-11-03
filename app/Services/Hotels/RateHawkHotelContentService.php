<?php

namespace App\Services\Hotels;

use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class RateHawkHotelContentService
{
    public function __construct(
        private readonly RateHawkClient $client
    ) {
    }

    /**
     * Retrieve static hotel information from RateHawk.
     *
     * The result is cached to reduce supplier API requests.
     */
    public function getHotel(
        int|string $hid,
        string $language = 'en'
    ): array {
        $hid = trim((string) $hid);
        $language = strtolower(trim($language));

        if ($hid === '' || ! ctype_digit($hid)) {
            throw new InvalidArgumentException(
                'A valid numeric hotel HID is required.'
            );
        }

        if (! in_array($language, ['en', 'ar'], true)) {
            throw new InvalidArgumentException(
                'Unsupported hotel content language.'
            );
        }

        $environment = config(
            'services.ratehawk.environment',
            'sandbox'
        );

        $cacheKey = sprintf(
            'ratehawk:hotel-content:%s:%s:%s',
            $environment,
            $language,
            $hid
        );

        return Cache::remember(
            $cacheKey,
            now()->addHours(24),
            function () use ($hid, $language): array {
                $response = $this->client->post(
                    '/api/b2b/v3/hotel/info/',
                    [
                        'hid' => $hid,
                        'language' => $language,
                    ]
                );

                if (($response['status'] ?? null) !== 'ok') {
                    throw new RuntimeException(
                        'RateHawk hotel content request failed.'
                    );
                }

                $hotel = $response['data'] ?? null;

                if (! is_array($hotel) || $hotel === []) {
                    throw new RuntimeException(
                        'RateHawk returned empty hotel content.'
                    );
                }

                return $hotel;
            }
        );
    }
}
