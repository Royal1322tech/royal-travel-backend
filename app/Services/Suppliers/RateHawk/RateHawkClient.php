<?php

namespace App\Services\Suppliers\RateHawk;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;




class RateHawkClient
{
    /**
     * Resolve the RateHawk API base URL.
     */
    private function baseUrl(): string
    {
        $environment = config(
            'services.ratehawk.environment',
            'sandbox'
        );

        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new RuntimeException(
                'Invalid RateHawk environment.'
            );
        }

        $configKey = $environment === 'sandbox'
            ? 'sandbox_url'
            : 'production_url';

        $baseUrl = config('services.ratehawk.' . $configKey);

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException(
                'RateHawk API base URL is not configured.'
            );
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Resolve the CA certificate bundle for HTTPS verification.
     */
    private function sslVerifyOption(): string|bool
    {
        $caBundle = ini_get('curl.cainfo');

        if (is_string($caBundle) && $caBundle !== '') {
            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new RuntimeException(
                    'PHP CA certificate bundle is missing or unreadable.'
                );
            }

            return $caBundle;
        }

        // Use the operating system's default CA trust store.
        return true;
    }

    /**
     * Send a JSON POST request to RateHawk.
     *
     * @throws RuntimeException
     */

public function post(
    string $path,
    array $payload,
    int $timeout = 30
): array {
    $keyId = config('services.ratehawk.key_id');
    $apiKey = config('services.ratehawk.api_key');

    if (
        ! is_string($keyId) ||
        $keyId === '' ||
        ! is_string($apiKey) ||
        $apiKey === ''
    ) {
        throw new RuntimeException(
            'RateHawk API credentials are not configured.'
        );
    }

    if (
        ! str_starts_with($path, '/api/b2b/v3/') ||
        str_contains($path, '://') ||
        str_contains($path, '..')
    ) {
        throw new RuntimeException(
            'Invalid RateHawk API path.'
        );
    }

    if ($timeout < 1 || $timeout > 120) {
        throw new RuntimeException(
            'Invalid RateHawk request timeout.'
        );
    }

    $response = Http::withOptions([
            'verify' => $this->sslVerifyOption(),
        ])
        ->withBasicAuth($keyId, $apiKey)
        ->acceptJson()
        ->asJson()
        ->connectTimeout(10)
        ->timeout($timeout)
        ->post(
            $this->baseUrl() . $path,
            $payload
        );

    if (! $response->successful()) {
        throw new RuntimeException(
            'RateHawk API returned HTTP ' . $response->status()
        );
    }

    $data = $response->json();

    if (! is_array($data)) {
        throw new RuntimeException(
            'Invalid RateHawk JSON response.'
        );
    }

    return $data;
}

}
