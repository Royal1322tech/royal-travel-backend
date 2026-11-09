<?php

namespace App\Services\Hotels;

/** Strict allowlist: never return raw supplier content or booking identifiers. */
final class RateHawkPublicHotelProjector
{
    public function preview(int $index, array $rooms, array $content): array
    {
        $image = $content['images'][0] ?? ($content['images_ext'][0]['url'] ?? null);
        if (is_string($image)) {
            $image = str_replace('{size}', '640x400', $image);
            // Only HTTPS image URLs may be returned.
            if (!str_starts_with($image, 'https://')) $image = null;
        } else {
            $image = null;
        }

        return [
            'result_index' => $index, // position only, NOT a hotel ID
            'name' => is_string($content['name'] ?? null) ? $content['name'] : null,
            'address' => is_string($content['address'] ?? null) ? $content['address'] : null,
            'stars' => is_numeric($content['star_rating'] ?? null)
                ? (float) $content['star_rating'] : null,
            'image' => $image,
            'rooms' => $rooms,
            'pricing_status' => 'unverified',
            'booking_enabled' => false,
        ];
    }
}
