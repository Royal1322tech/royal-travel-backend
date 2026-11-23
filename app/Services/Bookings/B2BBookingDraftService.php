<?php

namespace App\Services\Bookings;

use Illuminate\Support\Facades\Cache;
use App\Services\Bookings\CustomerDirectoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class B2BBookingDraftService
{
    public function create(int $userId, string $companyId, array $data, string $idempotencyKey): array
    {
        // A customer is a beneficiary, never the authenticated employee.
        if (empty($data['customer_id']) === empty($data['customer'])) {
            throw ValidationException::withMessages(['customer_id' => 'Select an existing customer or provide a new customer.']);
        }
        $reviewToken = $data['review_token'];
        $cacheKey = 'b2b_ratehawk_review:' . hash('sha256', $reviewToken);
        $review = Cache::get($cacheKey);
        if (!is_array($review)) {
            throw new HttpException(410, 'Review expired. Please prebook again.');
        }

        if (($review['environment'] ?? null) !== (string) config('services.ratehawk.environment', 'sandbox')) {
            throw new HttpException(410, 'Supplier environment changed.');
        }
        if (!isset($review['user_id'], $review['company_id'])
            || !hash_equals((string) $review['user_id'], (string) $userId)
            || !hash_equals(strtolower((string) $review['company_id']), strtolower($companyId))) {
            throw new HttpException(403, 'Review belongs to another account or company.');
        }

        $search = $review['search'] ?? null;
        $price = $review['price'] ?? null;
        if (!is_array($search) || !is_array($price)
            || ($price['verified'] ?? false) !== true
            || !isset($price['selling_price'], $price['currency'], $search['checkin'], $search['checkout'], $search['guests'])) {
            throw new HttpException(409, 'Incomplete review. Please select and prebook the room again.');
        }

        $rooms = $search['guests'];
        if (!is_array($rooms) || count($rooms) < 1 || count($rooms) > 8) {
            throw new HttpException(409, 'Invalid occupancy in review.');
        }
        $expectedAdults = 0;
        $expectedChildren = 0;
        foreach ($rooms as $room) {
            if (!is_array($room) || !isset($room['adults'], $room['children']) || !is_array($room['children'])) {
                throw new HttpException(409, 'Invalid room occupancy.');
            }
            $expectedAdults += (int) $room['adults'];
            $expectedChildren += count($room['children']);
        }
        $adults = 0;
        $children = 0;
        $leadCount = 0;
        foreach ($data['guests'] as $guest) {
            $adults += $guest['guest_type'] === 'adult' ? 1 : 0;
            $children += $guest['guest_type'] === 'child' ? 1 : 0;
            $leadCount += $guest['is_lead'] ? 1 : 0;
        }
        if ($adults !== $expectedAdults || $children !== $expectedChildren || $leadCount !== 1) {
            throw ValidationException::withMessages([
                'guests' => ['Guest counts must match the prebooked occupancy, with exactly one lead guest.'],
            ]);
        }

        $currency = strtoupper(trim((string) $price['currency']));
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new HttpException(409, 'Invalid review currency.');
        }
        $total = (string) $price['selling_price'];
        if (!is_numeric($total) || (float) $total <= 0 || (float) $total > 999999999) {
            throw new HttpException(409, 'Invalid review price.');
        }
        $offerRef = $review['rate']['book_hash']
            ?? $review['rate']['supplier']['book_hash']
            ?? null;
        if ($offerRef !== null && (!is_string($offerRef) || strlen($offerRef) > 255)) {
            throw new HttpException(409, 'Invalid supplier offer reference.');
        }

        $scope = 'b2b.hotel.booking.draft.' . strtolower($companyId) . '.' . $userId;
        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = hash('sha256', json_encode([
            'review_token' => $reviewToken,
            'guests' => $data['guests'],
            'customer_id' => $data['customer_id'] ?? null,
            'customer' => $data['customer'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($userId, $companyId, $data, $scope, $keyHash, $requestHash, $search, $price, $currency, $total, $offerRef, $rooms) {
            // A unique (scope, key_hash) constraint arbitrates concurrent identical requests.
            DB::table('rt_idempotency_keys')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'actor_user_id' => $userId,
                'scope' => $scope,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'state' => 'processing',
                'expires_at' => now()->addDay(),
                'created_at' => now(),
            ]);
            $idem = DB::table('rt_idempotency_keys')
                ->where('scope', $scope)->where('key_hash', $keyHash)
                ->lockForUpdate()->first();
            if (!$idem || !hash_equals((string) $idem->request_hash, $requestHash)) {
                throw new HttpException(409, 'Idempotency key reused with different request.');
            }
            if ($idem->state === 'completed' && $idem->response_body !== null) {
                return json_decode($idem->response_body, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($idem->state !== 'processing') {
                throw new HttpException(409, 'This request cannot be retried with the same key.');
            }

            // Resolve customer only after the idempotency row is locked; replay
            // must not create another customer record.
            $directory = app(CustomerDirectoryService::class);
            $customer = !empty($data['customer_id'])
                ? $directory->findForBooking($data['customer_id'], $companyId)
                : $directory->create($companyId, $userId, $data['customer']);
            $customerId = (string) $customer->getKey();

            $orderId = (string) Str::uuid();
            $bookingId = (string) Str::uuid();
            $now = now();
            $orderNo = 'ORD-' . strtoupper(Str::random(16));
            $bookingNo = 'HTL-' . strtoupper(Str::random(16));

            DB::table('rt_orders')->insert([
                'id' => $orderId, 'order_no' => $orderNo, 'channel' => 'b2b',
                'company_id' => $companyId, 'customer_id' => $customerId, 'customer_user_id' => null,
                'created_by' => $userId, 'status' => 'draft', 'currency' => $currency,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('rt_bookings')->insert([
                'id' => $bookingId, 'order_id' => $orderId, 'booking_no' => $bookingNo,
                'service_type' => 'hotel', 'channel' => 'b2b', 'company_id' => $companyId,
                'customer_id' => $customerId, 'customer_user_id' => null, 'status' => 'draft', 'currency' => $currency,
                'approved_total' => null, 'version' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('rt_hotel_booking_details')->insert([
                'booking_id' => $bookingId,
                // RateHawk hid is numeric, not the UUID of rt_hotels.
                'hotel_id' => null,
                'check_in' => $search['checkin'], 'check_out' => $search['checkout'],
                'rooms_count' => count($rooms), 'supplier_offer_ref' => $offerRef,
            ]);
            foreach ($data['guests'] as $guest) {
                DB::table('rt_booking_guests')->insert([
                    'id' => (string) Str::uuid(), 'booking_id' => $bookingId,
                    'first_name' => $guest['first_name'], 'last_name' => $guest['last_name'],
                    'guest_type' => $guest['guest_type'],
                    'date_of_birth' => $guest['date_of_birth'] ?? null,
                    'nationality_code' => $guest['nationality_code'] ?? null,
                    'is_lead' => $guest['is_lead'],
                ]);
            }
            DB::table('rt_booking_price_snapshots')->insert([
                'id' => (string) Str::uuid(), 'booking_id' => $bookingId,
                'version' => 1, 'currency' => $currency,
                // The current review only supplies an agency purchase price, not the raw supplier cost.
                'supplier_cost' => null, 'admin_markup' => null, 'company_markup' => null,
                'discount_amount' => 0, 'fee_amount' => 0, 'tax_amount' => 0,
                'customer_total' => $total, 'created_at' => $now,
            ]);
            $response = [
                'customer_id' => $customerId,
                'order_id' => $orderId, 'order_no' => $orderNo,
                'booking_id' => $bookingId, 'booking_no' => $bookingNo,
                'status' => 'draft', 'currency' => $currency, 'customer_total' => $total,
                'next_step' => 'payment_method',
                'payment_collected' => false, 'supplier_booking_created' => false,
            ];
            DB::table('rt_idempotency_keys')->where('id', $idem->id)->update([
                'state' => 'completed', 'response_code' => 201,
                'response_body' => json_encode($response, JSON_THROW_ON_ERROR),
            ]);
            return $response;
        }, 3);
    }
}
