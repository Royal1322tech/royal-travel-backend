<?php

namespace App\Http\Controllers\Api\V1\B2B\Hotels;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkHotelpageService;
use App\Services\Hotels\RateHawkPrebookService;
use App\Services\Hotels\RateHawkRateNormalizer;
use App\Services\Hotels\RateHawkPriceResolver;
use App\Services\Pricing\FxRateUnavailableException;
use App\Services\Pricing\PricingNotConfiguredException;
use App\Services\Pricing\HotelCurrencyQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class HotelRoomsApiController extends Controller
{
    private function userId(Request $request): string
    {
        $user = $request->user();

        abort_unless($user !== null, 401, 'Unauthenticated.');

        return (string) $user->getAuthIdentifier();
    }

    private function key(string $type, string $token): string
    {
        abort_unless(
            preg_match('/^[a-f0-9]{48}$/D', $token) === 1,
            404,
            'Invalid token.'
        );

        return 'b2b_ratehawk_' . $type . ':'
            . hash('sha256', $token);
    }

    private function environment(): string
    {
        return (string) config(
            'services.ratehawk.environment',
            'sandbox'
        );
    }

    private function snapshot(
        Request $request,
        string $type,
        string $token
    ): array {
        $snapshot = Cache::get($this->key($type, $token));

        abort_unless(
            is_array($snapshot),
            410,
            'Session expired. Please search again.'
        );

        abort_unless(
            ($snapshot['environment'] ?? null)
                === $this->environment(),
            410,
            'Supplier environment changed.'
        );

        abort_unless(
            isset($snapshot['user_id'])
                && hash_equals(
                    (string) $snapshot['user_id'],
                    $this->userId($request)
                ),
            403,
            'This selection belongs to another account.'
        );

        abort_unless(
            isset($snapshot['company_id'])
                && is_string($snapshot['company_id'])
                && hash_equals(
                    $snapshot['company_id'],
                    $this->companyId($request)
                ),
            403,
            'This selection belongs to another company.'
        );

        return $snapshot;
    }

    private function quote(
        array $normalized,
        string $currency,
        string $companyId
    ): array {
        $unavailable = [
            'verified' => false,
            'selling_price' => null,
            'agency_purchase_price' => null,
            'currency' => $currency,
            'price_on_request' => true,
        ];

        try {
            $resolved = app(
                RateHawkPriceResolver::class
            )->resolve($normalized);

            if (
                !is_array($resolved)
                || ($resolved['pricing_verified'] ?? false) !== true
                || !isset($resolved['amount'])
            ) {
                // Internal diagnostic only: a reason code, never supplier amounts or hashes.
                Log::info('B2B hotel rate not verified', [
                    'reason' => is_array($resolved)
                        ? ($resolved['reason'] ?? 'unverified')
                        : 'supplier_data_missing',
                ]);

                return $unavailable;
            }

            $quote = app(
                HotelCurrencyQuoteService::class
            )->quoteVerifiedUsd(
                (string) $resolved['amount'],
                'b2b',
                $currency,
                $companyId
            );

            if (
                !isset(
                    $quote['customer_selling_price'],
                    $quote['agency_purchase_price'],
                    $quote['currency']
                )
            ) {
                return $unavailable;
            }

            return [
                'verified' => true,
                'selling_price' =>
                    $quote['customer_selling_price'],
                'agency_purchase_price' =>
                    $quote['agency_purchase_price'],
                'currency' => $quote['currency'],
                'price_on_request' => false,
                // Charged by the property at the stay, not included in the prices above.
                'taxes_at_property' => array_values(
                    $resolved['taxes_at_property'] ?? []
                ),
            ];

        } catch (Throwable $e) {
            Log::warning('B2B hotel pricing unavailable', [
                'reason' => match (true) {
                    $e instanceof PricingNotConfiguredException => 'pricing_policy_missing',
                    $e instanceof FxRateUnavailableException => 'fx_rate_unavailable',
                    default => 'pricing_error',
                },
                'exception' => get_class($e),
            ]);

            return $unavailable;
        }
    }

    public function rooms(
        Request $request,
        int $hid,
        RateHawkHotelpageService $hotelpage,
        RateHawkRateNormalizer $normalizer
    ): JsonResponse {
        $userId = $this->userId($request);
        $companyId = $this->companyId($request);

        abort_unless($hid > 0, 422, 'Invalid hotel ID.');

        $data = $request->validate([
            'checkin' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'checkout' => [
                'required',
                'date_format:Y-m-d',
                'after:checkin',
            ],
            'residency' => [
                'required',
                'string',
                'regex:/^[a-zA-Z]{2}$/',
            ],
            'language' => [
                'sometimes',
                Rule::in(['en', 'ar']),
            ],
            'currency' => [
                'sometimes',
                Rule::in(['USD', 'EGP', 'SAR']),
            ],
            'guests' => [
                'required',
                'array',
                'min:1',
                'max:8',
            ],
            'guests.*.adults' => [
                'required',
                'integer',
                'min:1',
                'max:8',
            ],
            'guests.*.children' => [
                'present',
                'array',
                'max:8',
            ],
            'guests.*.children.*' => [
                'integer',
                'min:0',
                'max:17',
            ],
        ]);

        $search = [
            'checkin' => $data['checkin'],
            'checkout' => $data['checkout'],
            'residency' => $data['residency'],
            'language' => $data['language'] ?? 'en',
            'currency' => 'USD',
            'guests' => $data['guests'],
        ];

        $displayCurrency = $data['currency'] ?? 'USD';

        try {
            $result = $hotelpage->getRates($search, $hid);
        } catch (Throwable $e) {
            Log::warning('B2B hotelpage unavailable', [
                'hid' => $hid,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Rooms temporarily unavailable.',
            ], 503);
        }

        if (
            !is_array($result)
            || !isset($result['rates'])
            || !is_array($result['rates'])
        ) {
            return response()->json([
                'message' => 'Invalid supplier room response.',
            ], 503);
        }

        $rates = [];
        $public = [];

        foreach ($result['rates'] as $rate) {
            if (!is_array($rate)) {
                continue;
            }

            $normalized = $normalizer->normalize($rate);

            $index = count($rates);
            $rates[] = $normalized;

            $hash = $normalized['supplier']['book_hash']
                ?? null;

            $bookable = is_string($hash)
                && str_starts_with($hash, 'h-');

            $public[] = [
                'rate_index' => $index,
                'room_name' =>
                    $normalized['room']['name'] ?? null,
                'bedding_type' =>
                    $normalized['room']['bedding_type'] ?? null,
                'meal_name' =>
                    $normalized['meal']['name']
                    ?? $normalized['meal']['code']
                    ?? null,
                'has_breakfast' =>
                    $normalized['meal']['has_breakfast']
                    ?? null,
                'price' => $this->quote(
                    $normalized,
                    $displayCurrency,
                    $companyId
                ),
                'selectable' => $bookable,
                'booking_enabled' => false,
            ];
        }

        $token = bin2hex(random_bytes(24));

        Cache::put(
            $this->key('rooms', $token),
            [
                'environment' => $this->environment(),
                'user_id' => $userId,
                'company_id' => $companyId,
                'hid' => $hid,
                'search' => $search,
                'currency' => $displayCurrency,
                'rates' => $rates,
            ],
            now()->addMinutes(15)
        );

        return response()->json([
            'hotel_hid' => $hid,
            'rooms_token' => $token,
            'expires_in_seconds' => 900,
            'total' => count($public),
            'rooms' => $public,
        ])->header('Cache-Control', 'no-store');
    }

    public function select(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'rooms_token' => [
                'required',
                'string',
                'regex:/^[a-f0-9]{48}$/D',
            ],
            'rate_index' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $snapshot = $this->snapshot(
            $request,
            'rooms',
            $data['rooms_token']
        );

        $rate = $snapshot['rates'][$data['rate_index']]
            ?? null;

        abort_unless(
            is_array($rate),
            404,
            'Selected rate not found.'
        );

        $hash = $rate['supplier']['book_hash'] ?? null;

        abort_unless(
            is_string($hash)
                && str_starts_with($hash, 'h-'),
            409,
            'Selected rate is not bookable.'
        );

        $price = $this->quote(
            $rate,
            $snapshot['currency'],
            $companyId
        );

        abort_unless(
            $price['verified'] === true,
            409,
            'Room price is not verified.'
        );

        $selectionToken = bin2hex(random_bytes(24));

        Cache::put(
            $this->key('selection', $selectionToken),
            [
                'environment' => $this->environment(),
                'user_id' => $userId,
                'company_id' => $companyId,
                'hid' => $snapshot['hid'],
                'search' => $snapshot['search'],
                'currency' => $snapshot['currency'],
                'rate' => $rate,
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'selection_token' => $selectionToken,
            'expires_in_seconds' => 600,
            'room_name' => $rate['room']['name'] ?? null,
            'price' => $price,
            'next_step' => 'prebook',
        ])->header('Cache-Control', 'no-store');
    }

    public function prebook(
        Request $request,
        RateHawkPrebookService $service,
        RateHawkRateNormalizer $normalizer
    ): JsonResponse {
        $userId = $this->userId($request);
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'selection_token' => [
                'required',
                'string',
                'regex:/^[a-f0-9]{48}$/D',
            ],
        ]);

        $selection = $this->snapshot(
            $request,
            'selection',
            $data['selection_token']
        );

        $hash = $selection['rate']['supplier']['book_hash']
            ?? null;

        abort_unless(
            is_string($hash)
                && str_starts_with($hash, 'h-'),
            409,
            'Invalid selected rate.'
        );

        try {
            $result = $service->prebook(
                $hash,
                (int) $selection['hid']
            );
        } catch (Throwable $e) {
            Log::warning('B2B prebook unavailable', [
                'exception' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Unable to recheck this room.',
            ], 503);
        }

        abort_unless(
            is_array($result)
                && ($result['environment'] ?? null)
                    === $this->environment(),
            410,
            'Supplier environment changed.'
        );

        if (
            !isset($result['rate'])
            || !is_array($result['rate'])
        ) {
            return response()->json([
                'message' => 'Invalid supplier prebook response.',
            ], 503);
        }

        $normalized = $normalizer->normalize(
            $result['rate']
        );

        $price = $this->quote(
            $normalized,
            $selection['currency'],
            $companyId
        );

        abort_unless(
            $price['verified'] === true,
            409,
            'Rechecked room price is not verified.'
        );

        $priceChanged = (bool) (
            $result['price_changed'] ?? false
        );

        $reviewToken = bin2hex(random_bytes(24));

        Cache::put(
            $this->key('review', $reviewToken),
            [
                'environment' => $this->environment(),
                'user_id' => $userId,
                'company_id' => $companyId,
                'hid' => $selection['hid'],
                'search' => $selection['search'],
                'rate' => $result['rate'],
                'price' => $price,
                'price_changed' => $priceChanged,
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'status' => 'rechecked',
            'review_token' => $reviewToken,
            'expires_in_seconds' => 600,
            'price_changed' => $priceChanged,
            'review_required' => true,
            'room_name' =>
                $normalized['room']['name'] ?? null,
            'price' => $price,
            'booking_enabled' => false,
            'message' => $priceChanged
                ? 'Price changed; review the new quote.'
                : 'Room rechecked; review required.',
        ])->header('Cache-Control', 'no-store');
    }


    private function companyId(Request $request): string
    {
        $companyId = $request->attributes->get('authenticated_company_id');

        abort_unless(
            is_string($companyId) && Str::isUuid($companyId),
            403,
            'Invalid authenticated company context.'
        );

        return strtolower($companyId);
    }
}
