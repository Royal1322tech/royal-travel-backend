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
use App\Services\Hotels\RateHawkPrebookService;

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




public function prebook(
    Request $request,
    string $token,
    RateHawkPrebookService $prebookService
) {
    // 1. Validate token.
    abort_unless(
        preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1,
        404
    );

    $validated = $request->validate([
        'rate_index' => ['required', 'integer', 'min:0'],
    ]);

    // 2. Retrieve server-side snapshot.
    $cacheKey = 'admin_hotel_rooms:' . hash('sha256', $token);
    $snapshot = Cache::get($cacheKey);

    abort_unless(
        is_array($snapshot),
        410,
        'Room search expired. Please search again.'
    );

    // 3. Verify session.
    abort_unless(
        hash_equals(
            (string) ($snapshot['session'] ?? ''),
            hash('sha256', $request->session()->getId())
        ),
        403
    );

    // 4. Verify environment.
    $environment = config(
        'services.ratehawk.environment',
        'sandbox'
    );

    abort_unless(
        ($snapshot['environment'] ?? null) === $environment,
        410,
        'Hotel search environment changed. Please search again.'
    );

    // 5. Require Hotelpage rates.
    abort_unless(
        ($snapshot['rates_source'] ?? null) === 'hotelpage',
        409,
        'Hotelpage rates must be loaded first.'
    );

    // 6. Validate hotel ID.
    $hid = $snapshot['hid'] ?? null;

    abort_unless(
        is_int($hid) ||
        (is_string($hid) && ctype_digit($hid)),
        410,
        'Invalid hotel search details.'
    );

    $hid = (int) $hid;

    abort_unless(
        $hid > 0,
        410,
        'Invalid hotel search details.'
    );

    // 7. Validate selected cached rate.
    $rateIndex = (int) $validated['rate_index'];
    $rates = $snapshot['rates'] ?? [];

    abort_unless(
        is_array($rates)
        && array_key_exists($rateIndex, $rates)
        && is_array($rates[$rateIndex]),
        404,
        'Selected room rate was not found.'
    );

    // Never accept supplier hashes from the request.
    $bookHash = $rates[$rateIndex]['supplier']['book_hash']
        ?? null;

    abort_unless(
        is_string($bookHash)
        && str_starts_with($bookHash, 'h-'),
        409,
        'Selected Hotelpage rate is not valid.'
    );

    // 8. Call RateHawk Prebook.
    try {
        $result = $prebookService->prebook(
            $bookHash,
            $hid
        );
    } catch (Throwable $exception) {
        Log::warning('RateHawk Prebook unavailable', [
            'hid' => $hid,
            'exception' => get_class($exception),
        ]);

        abort(
            503,
            'Unable to recheck this room. Please try again.'
        );
    }

    // 9. Verify response environment.
    abort_unless(
        ($result['environment'] ?? null) === $environment,
        410,
        'Supplier environment changed. Please search again.'
    );

    // 10. Store Prebook result on server only.
    $prebookToken = bin2hex(random_bytes(24));

    $prebookKey = 'admin_hotel_prebook:'
        . hash('sha256', $prebookToken);

    Cache::put(
        $prebookKey,
        [
            'session' => hash(
                'sha256',
                $request->session()->getId()
            ),
            'environment' => $environment,
            'hid' => $hid,
            'rate_index' => $rateIndex,
            'search_token_hash' => hash('sha256', $token),
            'price_changed' => $result['price_changed'],
            'rate' => $result['rate'],
        ],
        now()->addMinutes(10)
    );

    // 11. Return only safe information.
    return response()->json([
        'status' => 'rechecked',
        'message' => $result['price_changed']
            ? 'Room price changed. Review is required.'
            : 'Room availability recheck completed.',
        'rate_index' => $rateIndex,
        'price_changed' => $result['price_changed'],
        'review_required' => true,
        'review_url' => route('admin.hotels.rooms.review', ['token' => $prebookToken]),
        'booking_enabled' => false,
    ]);
}


    /**
     * Read-only review. Supplier rates and hashes never reach Blade.
     */
    public function review(Request $request, string $token)
    {
        abort_unless(preg_match('/^[a-f0-9]{48}$/', $token) === 1, 404);

        $prebook = Cache::get('admin_hotel_prebook:' . hash('sha256', $token));
        abort_unless(is_array($prebook), 410, 'Prebook expired. Please recheck the room.');

        $sessionHash = hash('sha256', $request->session()->getId());
        abort_unless(hash_equals((string) ($prebook['session'] ?? ''), $sessionHash), 403);

        $environment = config('services.ratehawk.environment', 'sandbox');
        abort_unless(($prebook['environment'] ?? null) === $environment, 410);

        $searchTokenHash = $prebook['search_token_hash'] ?? null;
        abort_unless(is_string($searchTokenHash) && preg_match('/^[a-f0-9]{64}$/', $searchTokenHash) === 1, 410);

        $snapshot = Cache::get('admin_hotel_rooms:' . $searchTokenHash);
        abort_unless(is_array($snapshot), 410, 'Original room search expired. Please search again.');
        abort_unless(hash_equals((string) ($snapshot['session'] ?? ''), $sessionHash), 403);
        abort_unless(($snapshot['environment'] ?? null) === $environment, 410);
        abort_unless(($snapshot['rates_source'] ?? null) === 'hotelpage', 409);
        abort_unless((int) ($snapshot['hid'] ?? 0) > 0 && (int) $snapshot['hid'] === ($prebook['hid'] ?? null), 410);

        $rateIndex = $prebook['rate_index'] ?? null;
        $rates = $snapshot['rates'] ?? null;
        abort_unless(is_int($rateIndex) && is_array($rates) && isset($rates[$rateIndex]) && is_array($rates[$rateIndex]), 410);
        abort_unless(is_array($prebook['rate'] ?? null), 410);


$prebookRate = $prebook['rate'];



$paymentTypes = data_get(
    $prebookRate,
    'payment_options.payment_types',
    []
);

$payment = is_array($paymentTypes)
    ? ($paymentTypes[0] ?? [])
    : [];

$cancellation = $payment['cancellation_penalties'] ?? [];
$policies = $cancellation['policies'] ?? [];

$policyChecks = [];

foreach (is_array($policies) ? $policies : [] as $policy) {
    if (!is_array($policy)) {
        continue;
    }

    $start = $policy['start_at'] ?? null;
    $end = $policy['end_at'] ?? null;

    $policyChecks[] = [
        'start_present' => is_string($start) && $start !== '',
        'start_parseable' => is_string($start)
            && strtotime($start) !== false,
        'end_present' => is_string($end) && $end !== '',
        'end_parseable' => is_string($end)
            && strtotime($end) !== false,
        'amount_charge_numeric' => is_scalar(
            $policy['amount_charge'] ?? null
        ) && is_numeric($policy['amount_charge']),
        'amount_show_numeric' => is_scalar(
            $policy['amount_show'] ?? null
        ) && is_numeric($policy['amount_show']),
    ];
}

$freeBefore = $cancellation['free_cancellation_before'] ?? null;

Log::info('RateHawk Prebook cancellation validation', [
    'free_before_present' => is_string($freeBefore)
        && $freeBefore !== '',
    'free_before_parseable' => is_string($freeBefore)
        && strtotime($freeBefore) !== false,
    'policies' => $policyChecks,
]);



$paymentTypes = data_get(
    $prebookRate,
    'payment_options.payment_types',
    []
);

$payment = is_array($paymentTypes)
    ? ($paymentTypes[0] ?? [])
    : [];

$cancellation = $payment['cancellation_penalties'] ?? [];
$policies = $cancellation['policies'] ?? [];

$policyStructure = [];

if (is_array($policies)) {
    foreach ($policies as $policy) {
        $policyStructure[] = [
            'fields' => is_array($policy)
                ? array_keys($policy)
                : [],
            'is_array' => is_array($policy),
        ];
    }
}

Log::info('RateHawk Prebook cancellation structure', [
    'payment_type' => $payment['type'] ?? null,
    'cancellation_fields' => is_array($cancellation)
        ? array_keys($cancellation)
        : [],
    'policy_count' => is_array($policies)
        ? count($policies)
        : null,
    'policy_structure' => $policyStructure,
    'free_cancellation_type' => get_debug_type(
        $cancellation['free_cancellation_before'] ?? null
    ),
]);


$dailyPrices = $prebookRate['daily_prices'] ?? [];
$paymentTypes = data_get(
    $prebookRate,
    'payment_options.payment_types',
    []
);

$payment = is_array($paymentTypes)
    ? ($paymentTypes[0] ?? [])
    : [];

$dailyPricesValid = is_array($dailyPrices)
    && count($dailyPrices) > 0;

$dailyTotal = '0.00';

if ($dailyPricesValid) {
    foreach ($dailyPrices as $price) {
        if (
            !is_scalar($price)
            || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string) $price)
        ) {
            $dailyPricesValid = false;
            break;
        }

        $dailyTotal = bcadd($dailyTotal, (string) $price, 2);
    }
}

$amount = $payment['amount'] ?? null;

$amountValid = is_scalar($amount)
    && preg_match('/^\d+(?:\.\d{1,2})?$/D', (string) $amount) === 1;

Log::info('RateHawk Prebook daily total verification', [
    'daily_prices_valid' => $dailyPricesValid,
    'daily_prices_count' => is_array($dailyPrices)
        ? count($dailyPrices)
        : null,
    'payment_amount_valid' => $amountValid,
    'daily_total_matches_payment_amount' =>
        $dailyPricesValid && $amountValid
            ? bccomp($dailyTotal, (string) $amount, 2) === 0
            : null,
    'currency' => $payment['currency_code'] ?? null,
]);


        $paymentTypes = data_get(
            $prebookRate,
            'payment_options.payment_types',
            []
        );


$dailyPrices = $prebookRate['daily_prices'] ?? null;
$deposit = $prebookRate['deposit'] ?? null;
$barRate = $prebookRate['bar_rate_price_data'] ?? null;

Log::info('RateHawk Prebook total structure', [
    'daily_prices_type' => get_debug_type($dailyPrices),
    'daily_prices_count' => is_array($dailyPrices)
        ? count($dailyPrices)
        : null,
    'deposit_type' => get_debug_type($deposit),
    'deposit_fields' => is_array($deposit)
        ? array_keys($deposit)
        : [],
    'bar_rate_type' => get_debug_type($barRate),
    'bar_rate_fields' => is_array($barRate)
        ? array_keys($barRate)
        : [],
]);


        $paymentTypes = is_array($paymentTypes) ? $paymentTypes : [];

        $paymentFields = [];
        $cancellationFields = [];

        foreach ($paymentTypes as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $paymentFields = array_merge(
                $paymentFields,
                array_keys($payment)
            );

            $cancellation = $payment['cancellation_penalties'] ?? [];

            if (is_array($cancellation)) {
                $cancellationFields = array_merge(
                    $cancellationFields,
                    array_keys($cancellation)
                );
            }
        }

        // Temporary local diagnostic: field names only, never values.
      
$payment = $paymentTypes[0] ?? [];

$taxData = $payment['tax_data'] ?? null;
$vatData = $payment['vat_data'] ?? null;
$cancellation = $payment['cancellation_penalties'] ?? [];


$payment = $paymentTypes[0] ?? [];
$vat = $payment['vat_data'] ?? [];
$vat = is_array($vat) ? $vat : [];

$amount = $payment['amount'] ?? null;
$showAmount = $payment['show_amount'] ?? null;

Log::info('RateHawk Prebook amount verification', [
    'payment_type' => $payment['type'] ?? null,
    'currency' => $payment['currency_code'] ?? null,
    'amount_numeric' => is_numeric($amount),
    'show_amount_numeric' => is_numeric($showAmount),
    'amount_matches_show_amount' =>
        is_numeric($amount) && is_numeric($showAmount)
            ? bccomp((string) $amount, (string) $showAmount, 2) === 0
            : null,
    'vat_included_type' => get_debug_type($vat['included'] ?? null),
    'vat_included' => is_bool($vat['included'] ?? null)
        ? $vat['included']
        : null,
    'vat_applied_type' => get_debug_type($vat['applied'] ?? null),
    'vat_applied' => is_bool($vat['applied'] ?? null)
        ? $vat['applied']
        : null,
    'vat_currency' => $vat['currency_code'] ?? null,
    'vat_amount_numeric' => is_numeric($vat['amount'] ?? null),
    'vat_value_numeric' => is_numeric($vat['value'] ?? null),
    'cancellation_policy_count' =>
        is_array($payment['cancellation_penalties']['policies'] ?? null)
            ? count($payment['cancellation_penalties']['policies'])
            : 0,
]);



        $selectedRate = $rates[$rateIndex];
        $room = $selectedRate['room'] ?? [];
        $meal = $selectedRate['meal'] ?? [];
        $search = $snapshot['search'] ?? [];
        $hotel = $snapshot['hotel'] ?? [];

        return view('admin.hotels.review', [
            'hotelName' => is_array($hotel) ? (string) ($hotel['name'] ?? 'Selected hotel') : 'Selected hotel',
            'roomName' => is_array($room) ? (string) ($room['name'] ?? 'Selected room') : 'Selected room',
            'mealName' => is_array($meal) ? (string) ($meal['name'] ?? $meal['code'] ?? 'Not specified') : 'Not specified',
            'checkin' => is_array($search) ? (string) ($search['checkin'] ?? '') : '',
            'checkout' => is_array($search) ? (string) ($search['checkout'] ?? '') : '',
            'guestRooms' => is_array($search['guests'] ?? null) ? count($search['guests']) : 0,
            'priceChanged' => $prebook['price_changed'] === true,
            'bookingEnabled' => false,
        ]);
    }

}
