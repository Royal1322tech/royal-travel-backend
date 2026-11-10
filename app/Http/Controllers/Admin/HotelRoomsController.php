<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hotels\RateHawkHotelpageService;
use App\Services\Hotels\RateHawkRateNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class HotelRoomsController extends Controller
{
    public function show(
        Request $request,
        string $token,
        RateHawkHotelpageService $hotelpageService,
        RateHawkRateNormalizer $rateNormalizer
    ) {
        // 1. Validate the room search token.
        abort_unless(
            preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1,
            404
        );

        $cacheKey = 'admin_hotel_rooms:' . hash('sha256', $token);

        // 2. Retrieve the server-side snapshot.
        $snapshot = Cache::get($cacheKey);

        abort_unless(
            is_array($snapshot),
            410,
            'Room search expired. Please search again.'
        );

        // 3. Ensure the snapshot belongs to this session.
        abort_unless(
            hash_equals(
                (string) ($snapshot['session'] ?? ''),
                hash('sha256', $request->session()->getId())
            ),
            403
        );

        // 4. Verify the RateHawk environment.
        $currentEnvironment = config(
            'services.ratehawk.environment',
            'sandbox'
        );

        abort_unless(
            ($snapshot['environment'] ?? null) === $currentEnvironment,
            410,
            'Hotel search environment changed. Please search again.'
        );

        // 5. Validate the hotel ID and original search details.
        abort_unless(
            isset($snapshot['hid'])
            && is_numeric($snapshot['hid'])
            && (int) $snapshot['hid'] > 0
            && is_array($snapshot['search'] ?? null)
            && !empty($snapshot['search']['guests'])
            && is_array($snapshot['search']['guests']),
            410,
            'Hotel search details are incomplete. Please search again.'
        );

        // 6. Load Hotelpage rates for the selected hotel only.
        if (($snapshot['rates_source'] ?? null) !== 'hotelpage') {
            try {
                $result = $hotelpageService->getRates(
                    $snapshot['search'],
                    (int) $snapshot['hid']
                );

                // Verify the supplier response environment.
                if (
                    ($result['environment'] ?? null)
                    !== $currentEnvironment
                ) {
                    abort(
                        410,
                        'Hotel search environment changed. Please search again.'
                    );
                }

                $normalizedRates = [];

                foreach (($result['rates'] ?? []) as $rate) {
                    if (!is_array($rate)) {
                        continue;
                    }

                    // Hotelpage booking hashes must begin with h-.
                    $bookHash = $rate['book_hash'] ?? null;

                    if (
                        !is_string($bookHash)
                        || !str_starts_with($bookHash, 'h-')
                    ) {
                        continue;
                    }

                    $normalizedRates[] = $rateNormalizer->normalize($rate);
                }

                // Never fall back to old SERP rates.
                if ($normalizedRates === []) {
                    abort(
                        410,
                        'No valid Hotelpage rates are available. Please search again.'
                    );
                }

                // Store supplier details only on the server.
                $snapshot['rates'] = $normalizedRates;
                $snapshot['rates_source'] = 'hotelpage';

                Cache::put(
                    $cacheKey,
                    $snapshot,
                    now()->addMinutes(15)
                );

            } catch (HttpExceptionInterface $exception) {
                // Preserve intentional HTTP errors such as 410.
                throw $exception;

            } catch (Throwable $exception) {
                Log::warning('RateHawk Hotelpage unavailable', [
                    'hid' => $snapshot['hid'],
                    'exception' => get_class($exception),
                ]);

                abort(
                    503,
                    'Unable to refresh hotel room availability. Please try again.'
                );
            }
        }

        // 7. Prepare view-safe room details.
        // Never expose supplier hashes or internal prices.
        $rooms = [];

        foreach (($snapshot['rates'] ?? []) as $rateIndex => $rate) {
            if (!is_array($rate)) {
                continue;
            }

            $room = $rate['room'] ?? [];
            $meal = $rate['meal'] ?? [];
            $availability = $rate['availability'] ?? [];

            $rooms[] = [
                'rate_index' => $rateIndex,
                'name' => $room['name'] ?? 'Room details pending',
                'main_name' => $room['main_name'] ?? null,
                'bedding_type' => $room['bedding_type'] ?? null,
                'bathroom' => is_string($room['bathroom'] ?? null)
                    ? $room['bathroom']
                    : null,
                'meal' => $meal['name']
                    ?? $meal['code']
                    ?? 'Meal details pending',
                'breakfast' => $meal['has_breakfast'] ?? null,
                'allotment' => $availability['allotment'] ?? null,
                'package' => $rate['supplier']['is_package'] ?? null,
            ];
        }

        // 8. Return only safe data to Blade.
        return view('admin.hotels.rooms', [
            'hotel' => $snapshot['hotel'],
            'search' => $snapshot['search'],
            'rooms' => $rooms,
            'token' => $token,
        ]);
    }

    public function select(Request $request, string $token)
    {
        // 1. Validate the room search token.
        abort_unless(
            preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1,
            404
        );

        // 2. Validate the selected rate index.
        $validated = $request->validate([
            'rate_index' => ['required', 'integer', 'min:0'],
        ]);

        // 3. Retrieve the server-side snapshot.
        $snapshot = Cache::get(
            'admin_hotel_rooms:' . hash('sha256', $token)
        );

        abort_unless(
            is_array($snapshot),
            410,
            'Room search expired. Please search again.'
        );

        // 4. Verify the original session.
        abort_unless(
            hash_equals(
                (string) ($snapshot['session'] ?? ''),
                hash('sha256', $request->session()->getId())
            ),
            403
        );

        // 5. Verify the RateHawk environment.
        abort_unless(
            ($snapshot['environment'] ?? null) === config(
                'services.ratehawk.environment',
                'sandbox'
            ),
            410,
            'Hotel search environment changed. Please search again.'
        );

        // 6. Only Hotelpage rates can be selected.
        abort_unless(
            ($snapshot['rates_source'] ?? null) === 'hotelpage',
            409,
            'Hotelpage rates must be loaded before selecting a room.'
        );

        // 7. Validate the selected rate against cached rates.
        $rateIndex = (int) $validated['rate_index'];
        $rates = $snapshot['rates'] ?? [];

        abort_unless(
            is_array($rates)
            && array_key_exists($rateIndex, $rates)
            && is_array($rates[$rateIndex]),
            404,
            'Selected room rate was not found.'
        );

        // 8. Verify the internal Hotelpage booking hash.
        $bookHash = $rates[$rateIndex]['supplier']['book_hash'] ?? null;

        abort_unless(
            is_string($bookHash)
            && str_starts_with($bookHash, 'h-'),
            409,
            'Selected Hotelpage rate is not valid.'
        );

        // 9. Selection only. No Prebook or Booking yet.
        return response()->json([
            'status' => 'selected',
            'message' => 'Room selection validated. Recheck is not yet available.',
            'rate_index' => $rateIndex,
            'booking_enabled' => false,
        ]);
    }
}
