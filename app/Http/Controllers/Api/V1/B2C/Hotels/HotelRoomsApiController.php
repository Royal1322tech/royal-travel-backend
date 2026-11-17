<?php

namespace App\Http\Controllers\Api\V1\B2C\Hotels;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkHotelpageService;
use App\Services\Hotels\RateHawkPrebookService;
use App\Services\Hotels\RateHawkRateNormalizer;
use App\Services\Hotels\RateHawkPriceResolver;
use App\Services\Pricing\HotelCurrencyQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

final class HotelRoomsApiController extends Controller
{
    private function enabled(): void
    {
        abort_unless(config('services.ratehawk.b2c_enabled', false) === true, 403, 'B2C supplier distribution is not enabled.');
    }

    private function key(string $type, string $token): string
    {
        abort_unless(preg_match('/^[a-f0-9]{48}$/D', $token) === 1, 404);
        return 'b2c_ratehawk_' . $type . ':' . hash('sha256', $token);
    }

    private function environment(): string
    {
        return (string) config('services.ratehawk.environment', 'sandbox');
    }

    private function snapshot(string $token): array
    {
        $snapshot = Cache::get($this->key('rooms', $token));
        abort_unless(is_array($snapshot), 410, 'Room search expired. Search again.');
        abort_unless(($snapshot['environment'] ?? null) === $this->environment(), 410, 'Supplier environment changed.');
        return $snapshot;
    }

    private function quote(array $normalized, string $currency): array
    {
        $resolved = app(RateHawkPriceResolver::class)->resolve($normalized);
        if (!is_array($resolved) || ($resolved['pricing_verified'] ?? false) !== true) {
            return ['verified' => false, 'selling_price' => null, 'currency' => $currency, 'price_on_request' => true];
        }
        try {
            $quote = app(HotelCurrencyQuoteService::class)->quoteVerifiedUsd((string) $resolved['amount'], 'b2c', $currency);
            return [
                'verified' => true,
                'selling_price' => $quote['customer_selling_price'],
                'currency' => $quote['currency'],
                'price_on_request' => false,
            ];
        } catch (Throwable $e) {
            Log::warning('B2C hotel pricing unavailable', ['exception' => get_class($e)]);
            return ['verified' => false, 'selling_price' => null, 'currency' => $currency, 'price_on_request' => true];
        }
    }

    public function rooms(Request $request, int $hid, RateHawkHotelpageService $hotelpage, RateHawkRateNormalizer $normalizer): JsonResponse
    {
        $this->enabled();
        abort_unless($hid > 0, 422, 'Invalid hotel ID.');
        $data = $request->validate([
            'checkin' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'checkout' => ['required', 'date_format:Y-m-d', 'after:checkin'],
            'residency' => ['required', 'string', 'regex:/^[a-zA-Z]{2}$/'],
            'language' => ['sometimes', Rule::in(['en', 'ar'])],
            'currency' => ['sometimes', Rule::in(['USD', 'EGP', 'SAR'])],
            'guests' => ['required', 'array', 'min:1', 'max:8'],
            'guests.*.adults' => ['required', 'integer', 'min:1', 'max:8'],
            'guests.*.children' => ['required', 'array', 'max:8'],
            'guests.*.children.*' => ['integer', 'min:0', 'max:17'],
        ]);
        $search = [
            'checkin' => $data['checkin'], 'checkout' => $data['checkout'],
            'residency' => $data['residency'], 'language' => $data['language'] ?? 'en',
            'currency' => 'USD', 'guests' => $data['guests'],
        ];
        $displayCurrency = $data['currency'] ?? 'USD';
        try {
            $result = $hotelpage->getRates($search, $hid);
        } catch (Throwable $e) {
            Log::warning('B2C hotelpage unavailable', ['hid' => $hid, 'exception' => get_class($e)]);
            return response()->json(['message' => 'Rooms temporarily unavailable.'], 503);
        }
        $rates = [];
        $public = [];
        foreach ($result['rates'] as $rate) {
            if (!is_array($rate)) continue;
            $normalized = $normalizer->normalize($rate);
            $index = count($rates);
            $rates[] = $normalized;
            $public[] = [
                'rate_index' => $index,
                'room_name' => $normalized['room']['name'] ?? null,
                'bedding_type' => $normalized['room']['bedding_type'] ?? null,
                'meal_name' => $normalized['meal']['name'] ?? $normalized['meal']['code'] ?? null,
                'has_breakfast' => $normalized['meal']['has_breakfast'] ?? null,
                'price' => $this->quote($normalized, $displayCurrency),
                'booking_enabled' => false,
            ];
        }
        $token = bin2hex(random_bytes(24));
        Cache::put($this->key('rooms', $token), [
            'environment' => $this->environment(), 'hid' => $hid,
            'search' => $search, 'currency' => $displayCurrency, 'rates' => $rates,
        ], now()->addMinutes(15));
        return response()->json([
            'hotel_hid' => $hid, 'rooms_token' => $token,
            'expires_in_seconds' => 900, 'total' => count($public), 'rooms' => $public,
        ])->header('Cache-Control', 'no-store');
    }

    public function select(Request $request): JsonResponse
    {
        $this->enabled();
        $data = $request->validate([
            'rooms_token' => ['required', 'string', 'regex:/^[a-f0-9]{48}$/D'],
            'rate_index' => ['required', 'integer', 'min:0'],
        ]);
        $snapshot = $this->snapshot($data['rooms_token']);
        $rate = $snapshot['rates'][$data['rate_index']] ?? null;
        abort_unless(is_array($rate), 404, 'Selected rate not found.');
        $hash = $rate['supplier']['book_hash'] ?? null;
        abort_unless(is_string($hash) && str_starts_with($hash, 'h-'), 409, 'Selected rate is not bookable.');
        $selectionToken = bin2hex(random_bytes(24));
        Cache::put($this->key('selection', $selectionToken), [
            'environment' => $this->environment(), 'hid' => $snapshot['hid'],
            'currency' => $snapshot['currency'], 'rate' => $rate,
        ], now()->addMinutes(10));
        return response()->json([
            'selection_token' => $selectionToken, 'expires_in_seconds' => 600,
            'room_name' => $rate['room']['name'] ?? null,
            'price' => $this->quote($rate, $snapshot['currency']),
            'next_step' => 'prebook',
        ])->header('Cache-Control', 'no-store');
    }

    public function prebook(Request $request, RateHawkPrebookService $service, RateHawkRateNormalizer $normalizer): JsonResponse
    {
        $this->enabled();
        $data = $request->validate([
            'selection_token' => ['required', 'string', 'regex:/^[a-f0-9]{48}$/D'],
        ]);
        $selection = Cache::get($this->key('selection', $data['selection_token']));
        abort_unless(is_array($selection), 410, 'Selection expired.');
        abort_unless(($selection['environment'] ?? null) === $this->environment(), 410, 'Supplier environment changed.');
        $hash = $selection['rate']['supplier']['book_hash'] ?? null;
        abort_unless(is_string($hash) && str_starts_with($hash, 'h-'), 409, 'Invalid selected rate.');
        try {
            $result = $service->prebook($hash, (int) $selection['hid']);
        } catch (Throwable $e) {
            Log::warning('B2C prebook unavailable', ['exception' => get_class($e)]);
            return response()->json(['message' => 'Unable to recheck this room.'], 503);
        }
        abort_unless(($result['environment'] ?? null) === $this->environment(), 410);
        $normalized = $normalizer->normalize($result['rate']);
        $price = $this->quote($normalized, $selection['currency']);
        $reviewToken = bin2hex(random_bytes(24));
        Cache::put($this->key('review', $reviewToken), [
            'environment' => $this->environment(), 'hid' => $selection['hid'],
            'rate' => $result['rate'], 'price' => $price,
            'price_changed' => (bool) $result['price_changed'],
        ], now()->addMinutes(10));
        return response()->json([
            'status' => 'rechecked', 'review_token' => $reviewToken,
            'expires_in_seconds' => 600, 'price_changed' => (bool) $result['price_changed'],
            'review_required' => true, 'room_name' => $normalized['room']['name'] ?? null,
            'price' => $price, 'booking_enabled' => false,
            'message' => $result['price_changed'] ? 'Price changed; review the new quote.' : 'Room rechecked; review required.',
        ])->header('Cache-Control', 'no-store');
    }
}
