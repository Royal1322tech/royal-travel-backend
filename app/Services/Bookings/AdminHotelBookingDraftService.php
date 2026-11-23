<?php
declare(strict_types=1);
namespace App\Services\Bookings;
use Illuminate\Support\Facades\Log;
use App\Modules\Identity\Models\Company;
use App\Services\Hotels\RateHawkPriceResolver;
use App\Services\Hotels\RateHawkRateNormalizer;
use App\Services\Pricing\HotelCurrencyQuoteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Illuminate\Validation\ValidationException;

use Symfony\Component\HttpKernel\Exception\HttpException;



final class AdminHotelBookingDraftService

{

    public function create(int $actorId, string $token, string $sessionId, array $data): array

    {

        $prebook = Cache::get('admin_hotel_prebook:' . hash('sha256', $token));

        if (!is_array($prebook)) {

            throw new HttpException(410, 'Prebook expired. Please recheck the room.');

        }

        $sessionHash = hash('sha256', $sessionId);

        if (!hash_equals((string) ($prebook['session'] ?? ''), $sessionHash)) {

            throw new HttpException(403, 'Prebook belongs to another session.');

        }

        $environment = (string) config('services.ratehawk.environment', 'sandbox');

        if (($prebook['environment'] ?? null) !== $environment) {

            throw new HttpException(410, 'Supplier environment changed.');

        }

        $searchHash = $prebook['search_token_hash'] ?? null;

        if (!is_string($searchHash) || preg_match('/^[a-f0-9]{64}$/D', $searchHash) !== 1) {

            throw new HttpException(410, 'Invalid search reference.');

        }

        $snapshot = Cache::get('admin_hotel_rooms:' . $searchHash);

        if (!is_array($snapshot) || ($snapshot['environment'] ?? null) !== $environment

            || ($snapshot['rates_source'] ?? null) !== 'hotelpage'

            || !hash_equals((string) ($snapshot['session'] ?? ''), $sessionHash)

            || (int) ($snapshot['hid'] ?? 0) < 1

            || (int) $snapshot['hid'] !== (int) ($prebook['hid'] ?? 0)) {

            throw new HttpException(410, 'Original room search expired or changed.');

        }

        $rateIndex = $prebook['rate_index'] ?? null;

        if (!is_int($rateIndex) || !is_array($snapshot['rates'][$rateIndex] ?? null)

            || !is_array($prebook['rate'] ?? null)) {

            throw new HttpException(409, 'Selected supplier rate is unavailable.');

        }

        $search = $snapshot['search'] ?? null;

        $occupancies = is_array($search) ? ($search['guests'] ?? null) : null;

        if (!is_array($occupancies) || count($occupancies) < 1 || count($occupancies) > 8

            || !isset($search['checkin'], $search['checkout'])) {

            throw new HttpException(409, 'Invalid search occupancy or dates.');

        }

        $submittedRooms = $data['rooms'];

        if (array_keys($submittedRooms) !== range(0, count($occupancies) - 1)) {

            throw ValidationException::withMessages(['rooms' => 'Room count or ordering does not match the search.']);

        }

        $guests = [];

        foreach ($occupancies as $roomIndex => $occupancy) {

            if (!is_array($occupancy) || !is_int($occupancy['adults'] ?? null) && !is_numeric($occupancy['adults'] ?? null)

                || !is_array($occupancy['children'] ?? null)) {

                throw new HttpException(409, 'Invalid room occupancy.');

            }

            $adults = (int) $occupancy['adults'];

            $children = count($occupancy['children']);

            if ($adults < 1 || $adults > 20 || $adults + $children > 20) {

                throw new HttpException(409, 'Unsupported occupancy.');

            }

            $submitted = $submittedRooms[$roomIndex]['guests'] ?? [];

            if (!is_array($submitted) || array_keys($submitted) !== range(0, $adults + $children - 1)) {

                throw ValidationException::withMessages(['rooms' => 'Guest count must match each selected room.']);

            }

            foreach ($submitted as $index => $guest) {

                $guests[] = [

                    'first_name' => trim($guest['first_name']),

                    'last_name' => trim($guest['last_name']),

                    'guest_type' => $index < $adults ? 'adult' : 'child',

                    'is_lead' => $roomIndex === 0 && $index === 0,

                ];

            }

        }

        $companyId = $data['company_id'] ?? null;

        if ($companyId !== null) {

            $company = Company::query()->whereKey($companyId)->firstOrFail();

            if ($company->status !== 'approved') {

                throw ValidationException::withMessages(['company_id' => 'Company must be approved.']);

            }

        }

        $channel = $companyId === null ? 'b2c' : 'b2b';

        $normalized = app(RateHawkRateNormalizer::class)->normalize($prebook['rate']);

        $supplierPrice = app(RateHawkPriceResolver::class)->resolve($normalized);

        if (!is_array($supplierPrice) || ($supplierPrice['pricing_verified'] ?? false) !== true) {
            $supplier = $normalized['supplier'] ?? [];
            $payments = is_array($supplier['payment_options'] ?? null)
                ? $supplier['payment_options']
                : [];
            $payment = count($payments) === 1 && is_array($payments[0])
                ? $payments[0]
                : [];
            $daily = $supplier['daily_prices'] ?? null;
            $vat = $payment['vat_data'] ?? null;

            // Diagnostic metadata only: no guest details, supplier references or prices.
            Log::warning('Admin hotel draft supplier price verification failed', [
                'reason' => is_array($supplierPrice) ? ($supplierPrice['reason'] ?? null) : 'supplier_data_missing',
                'supplier_present' => is_array($normalized['supplier'] ?? null),
                'payment_options_count' => count($payments),
                'payment_type' => $payment['type'] ?? null,
                'supplier_currency' => $payment['supplier_currency'] ?? null,
                'supplier_show_currency' => $payment['supplier_show_currency'] ?? null,
                'daily_prices_count' => is_array($daily) ? count($daily) : null,
                'vat_included' => is_array($vat) ? ($vat['included'] ?? null) : null,
                'vat_applied' => is_array($vat) ? ($vat['applied'] ?? null) : null,
                'vat_data_present' => is_array($vat),
                'tax_data_empty' => ($payment['tax_data'] ?? null) === [],
            ]);

            throw new HttpException(409, 'Supplier total is not verified.');
        }

        $quote = app(HotelCurrencyQuoteService::class)->quoteVerifiedUsd((string) $supplierPrice['amount'], $channel, 'USD');

        $currency = strtoupper((string) ($quote['currency'] ?? ''));

        $total = (string) ($quote['customer_selling_price'] ?? '');

        if ($currency !== 'USD' || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $total) || (float) $total <= 0) {

            throw new HttpException(409, 'Invalid verified selling price.');

        }

        $offerRef = $prebook['rate']['book_hash'] ?? $prebook['rate']['supplier']['book_hash'] ?? null;

        if ($offerRef !== null && (!is_string($offerRef) || strlen($offerRef) > 255)) {

            throw new HttpException(409, 'Invalid supplier reference.');

        }

        $mode = $data['customer_mode'];

        if ($mode === 'new' && empty($data['customer']['email']) && empty($data['customer']['phone'])) {

            throw ValidationException::withMessages(['customer.email' => 'Customer email or phone is required.']);

        }

        $scope = 'admin.hotel.draft.' . $actorId;

        $keyHash = hash('sha256', $token); // One draft per prebook token, even across page reloads.

        $requestHash = hash('sha256', json_encode([$token, $companyId, $mode, $data['customer_id'] ?? null,

            $mode === 'new' ? ($data['customer'] ?? null) : null, $submittedRooms,

            $data['contact_email'], $data['contact_phone']], JSON_THROW_ON_ERROR));



        return DB::transaction(function () use ($actorId, $data, $scope, $keyHash, $requestHash, $companyId,

            $channel, $search, $occupancies, $guests, $currency, $total, $offerRef, $mode) {

            DB::table('rt_idempotency_keys')->insertOrIgnore([

                'id' => (string) Str::uuid(), 'actor_user_id' => $actorId,

                'scope' => $scope, 'key_hash' => $keyHash, 'request_hash' => $requestHash,

                'state' => 'processing', 'expires_at' => now()->addDay(), 'created_at' => now(),

            ]);

            $idem = DB::table('rt_idempotency_keys')->where('scope', $scope)

                ->where('key_hash', $keyHash)->lockForUpdate()->first();

            if (!$idem || !hash_equals((string) $idem->request_hash, $requestHash)) {

                throw new HttpException(409, 'Draft key was reused with different details.');

            }

            if ($idem->state === 'completed' && $idem->response_body !== null) {

                return json_decode($idem->response_body, true, 512, JSON_THROW_ON_ERROR);

            }

            if ($idem->state !== 'processing') {

                throw new HttpException(409, 'Draft cannot be retried with this key.');

            }

            $directory = app(CustomerDirectoryService::class);

            $customer = $mode === 'existing'

                ? $directory->findForBooking($data['customer_id'], $companyId)

                : $directory->create($companyId, $actorId, $data['customer']);

            $customerId = (string) $customer->getKey();

            $now = now();

            $orderId = (string) Str::uuid();

            $bookingId = (string) Str::uuid();

            $orderNo = 'ORD-' . strtoupper(Str::random(16));

            $bookingNo = 'HTL-' . strtoupper(Str::random(16));

            DB::table('rt_orders')->insert([

                'id' => $orderId, 'order_no' => $orderNo, 'channel' => $channel,

                'company_id' => $companyId, 'customer_id' => $customerId,

                'customer_user_id' => null, 'created_by' => $actorId,

                'status' => 'draft', 'currency' => $currency, 'created_at' => $now, 'updated_at' => $now,

            ]);

            DB::table('rt_bookings')->insert([

                'id' => $bookingId, 'order_id' => $orderId, 'booking_no' => $bookingNo,

                'service_type' => 'hotel', 'channel' => $channel, 'company_id' => $companyId,

                'customer_id' => $customerId, 'customer_user_id' => null,

                'status' => 'draft', 'currency' => $currency, 'approved_total' => null,

                'version' => 1, 'created_at' => $now, 'updated_at' => $now,

            ]);

            DB::table('rt_hotel_booking_details')->insert([

                'booking_id' => $bookingId, 'hotel_id' => null,

                'check_in' => $search['checkin'], 'check_out' => $search['checkout'],

                'rooms_count' => count($occupancies), 'supplier_offer_ref' => $offerRef,

            ]);

            foreach ($guests as $guest) {

                DB::table('rt_booking_guests')->insert([

                    'id' => (string) Str::uuid(), 'booking_id' => $bookingId,

                    'first_name' => $guest['first_name'], 'last_name' => $guest['last_name'],

                    'guest_type' => $guest['guest_type'], 'date_of_birth' => null,

                    'nationality_code' => null, 'is_lead' => $guest['is_lead'],

                ]);

            }

            DB::table('rt_booking_price_snapshots')->insert([

                'id' => (string) Str::uuid(), 'booking_id' => $bookingId,

                'version' => 1, 'currency' => $currency, 'supplier_cost' => null,

                'admin_markup' => null, 'company_markup' => null,

                'discount_amount' => 0, 'fee_amount' => 0, 'tax_amount' => 0,

                'customer_total' => $total, 'created_at' => $now,

            ]);

            $response = ['customer_id' => $customerId, 'order_id' => $orderId,

                'order_no' => $orderNo, 'booking_id' => $bookingId, 'booking_no' => $bookingNo,

                'status' => 'draft', 'currency' => $currency, 'customer_total' => $total,

                'payment_collected' => false, 'supplier_booking_created' => false];

            DB::table('rt_idempotency_keys')->where('id', $idem->id)->update([

                'state' => 'completed', 'response_code' => 201,

                'response_body' => json_encode($response, JSON_THROW_ON_ERROR),

            ]);

            return $response;

        }, 3);

    }

}
