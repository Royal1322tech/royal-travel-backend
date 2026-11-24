<?php

declare(strict_types=1);

namespace App\Services\Hotels;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Admin B2C RateHawk workflow — prepare and guarded booking-form phases.
 * Booking form calls supplier sandbox only; never finishes a booking or charges wallets.
 */
final class AdminRateHawkBookingConfirmationService
{
    private const OPERATION = 'ratehawk_hotel_booking';
    private const MAX_PREBOOK_AGE_SECONDS = 600;

    public function prepare(string $bookingId): array
    {
        return DB::transaction(function () use ($bookingId): array {
            $booking = DB::table('rt_bookings')->where('id', $bookingId)->lockForUpdate()->first();
            if (!$booking || $booking->channel !== 'b2c' || $booking->service_type !== 'hotel'
                || $booking->company_id !== null || $booking->currency !== 'USD') {
                throw new RuntimeException('Only USD Admin B2C hotel bookings are supported.');
            }

            // Check existing attempt before status to keep prepare idempotent.
            $existing = DB::table('rt_supplier_booking_attempts')
                ->where('booking_id', $bookingId)->where('operation', self::OPERATION)->first();
            if ($existing) {
                if ((string) $existing->supplier_id !== (string) $booking->supplier_id) {
                    throw new RuntimeException('Existing supplier attempt does not match booking supplier.');
                }
                return $this->snapshot($existing);
            }

            if ($booking->status !== 'draft') {
                throw new RuntimeException('Only draft bookings can be prepared.');
            }

            $supplier = DB::table('rt_suppliers')->where('id', $booking->supplier_id)
                ->where('code', 'RATEHAWK')->where('adapter_code', 'RATEHAWK')
                ->where('status', 'active')->first();
            if (!$supplier) {
                throw new RuntimeException('Active RateHawk supplier is required.');
            }

            $hotel = DB::table('rt_hotel_booking_details')->where('booking_id', $bookingId)->first();
            if (!$hotel || !is_string($hotel->supplier_offer_ref) || $hotel->supplier_offer_ref === ''
                || (int) $hotel->rooms_count < 1 || !$hotel->check_in || !$hotel->check_out) {
                throw new RuntimeException('Incomplete hotel booking details.');
            }

            $price = DB::table('rt_booking_price_snapshots')
                ->where('booking_id', $bookingId)->orderByDesc('version')->first();
            if (!$price || $price->currency !== 'USD' || $price->supplier_cost === null
                || !is_numeric($price->supplier_cost) || (float) $price->supplier_cost <= 0
                || $price->customer_total === null || !is_numeric($price->customer_total)
                || (float) $price->customer_total <= 0) {
                throw new RuntimeException('Verified supplier and customer prices are required.');
            }

            $contact = DB::table('rt_booking_supplier_contacts')->where('booking_id', $bookingId)->first();
            if (!$contact || !is_string($contact->email_encrypted) || $contact->email_encrypted === ''
                || !is_string($contact->phone_encrypted) || $contact->phone_encrypted === '') {
                throw new RuntimeException('Encrypted supplier contact details are required.');
            }

            $guestCount = DB::table('rt_booking_guests')->where('booking_id', $bookingId)->count();
            $roomCount = DB::table('rt_booking_guests')->where('booking_id', $bookingId)
                ->whereNotNull('room_index')->distinct()->count('room_index');
            if ($guestCount < 1 || $roomCount !== (int) $hotel->rooms_count) {
                throw new RuntimeException('Booking guests do not match room count.');
            }

            $attemptId = (string) Str::uuid();
            $partnerOrderId = (string) Str::uuid();
            DB::table('rt_supplier_booking_attempts')->insert([
                'id' => $attemptId,
                'booking_id' => $bookingId,
                'supplier_id' => $booking->supplier_id,
                'operation' => self::OPERATION,
                'attempt_no' => 1,
                'state' => 'prepared',
                'request_key' => $partnerOrderId,
                'started_at' => now(),
            ]);
            DB::table('rt_supplier_external_refs')->insert([
                'id' => (string) Str::uuid(),
                'attempt_id' => $attemptId,
                'supplier_id' => $booking->supplier_id,
                'reference_kind' => 'partner_order_id',
                'reference_value' => $partnerOrderId,
            ]);

            return [
                'booking_id' => $bookingId,
                'attempt_id' => $attemptId,
                'partner_order_id' => $partnerOrderId,
                'state' => 'prepared',
            ];
        }, 3);
    }

    /**
     * One-shot supplier form request. A timeout leaves form_unknown for manual
     * investigation: NEVER repeat createForm for this booking automatically.
     */
    public function createForm(string $bookingId, string $userIp): array
    {
        if (config('services.ratehawk.environment') !== 'sandbox'
            || config('services.ratehawk.sandbox_url') !== 'https://api-sandbox.ratehawk.com') {
            throw new RuntimeException('Sandbox environment is required for this test workflow.');
        }
        if (filter_var($userIp, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('Valid end-user IP is required.');
        }

        $context = DB::transaction(function () use ($bookingId): array {
            $booking = DB::table('rt_bookings')->where('id', $bookingId)->lockForUpdate()->first();
            if (!$booking || $booking->channel !== 'b2c' || $booking->service_type !== 'hotel'
                || $booking->company_id !== null || $booking->status !== 'draft'
                || $booking->currency !== 'USD') {
                throw new RuntimeException('Expected a draft USD B2C hotel booking.');
            }
            $attempt = DB::table('rt_supplier_booking_attempts')
                ->where('booking_id', $bookingId)->where('operation', self::OPERATION)
                ->lockForUpdate()->first();
            if (!$attempt || $attempt->state !== 'prepared'
                || (string) $attempt->supplier_id !== (string) $booking->supplier_id) {
                throw new RuntimeException('Booking form already started or not prepared. Do not retry blindly.');
            }
            $hotel = DB::table('rt_hotel_booking_details')->where('booking_id', $bookingId)->first();
            $hash = $hotel->supplier_offer_ref ?? null;
            if (!is_string($hash) || preg_match('/^p-\S{1,254}$/D', $hash) !== 1) {
                throw new RuntimeException('Valid prebook hash is required.');
            }
            $supplier = DB::table('rt_suppliers')->where('id', $booking->supplier_id)
                ->where('code', 'RATEHAWK')->where('adapter_code', 'RATEHAWK')
                ->where('status', 'active')->exists();
            if (!$supplier) {
                throw new RuntimeException('RateHawk supplier is not active.');
            }
            $offer = DB::table('rt_ratehawk_booking_offer_contexts')
                ->where('booking_id', $bookingId)->first();
            $price = DB::table('rt_booking_price_snapshots')
                ->where('booking_id', $bookingId)->orderByDesc('version')->first();
            if (!$offer || !$price || $offer->environment !== 'sandbox'
                || $offer->currency !== 'USD' || $price->currency !== 'USD'
                || (int) $offer->hid < 1 || $offer->price_changed === null
                || filter_var($offer->price_changed, FILTER_VALIDATE_BOOLEAN)
                || !is_string($offer->hotelpage_book_hash)
                || preg_match('/^h-\S+$/D', $offer->hotelpage_book_hash) !== 1
                || !is_string($offer->prebook_hash)
                || !hash_equals($offer->prebook_hash, $hash)
                || !is_string((string) $offer->supplier_cost)
                || !preg_match('/^\d+(?:\.\d{1,6})?$/D', (string) $offer->supplier_cost)
                || !preg_match('/^\d+(?:\.\d{1,6})?$/D', (string) $price->supplier_cost)
                || bccomp((string) $offer->supplier_cost, (string) $price->supplier_cost, 6) !== 0) {
                throw new RuntimeException('Missing or inconsistent verified RateHawk offer context.');
            }
            if (!$offer->prebook_verified_at) {
                throw new RuntimeException('Missing Prebook verification timestamp.');
            }
            $verifiedAt = CarbonImmutable::parse($offer->prebook_verified_at, 'UTC');
            $age = now('UTC')->getTimestamp() - $verifiedAt->getTimestamp();
            if ($age < 0 || $age >= self::MAX_PREBOOK_AGE_SECONDS) {
                throw new RuntimeException('Prebook is stale. Start a new room recheck and draft.');
            }
            if (DB::table('rt_ratehawk_booking_forms')->where('attempt_id', $attempt->id)->exists()) {
                throw new RuntimeException('Booking form already stored; do not resend.');
            }
            if (!Str::isUuid((string) $attempt->request_key)) {
                throw new RuntimeException('Invalid supplier partner order ID.');
            }
            // Commit the irreversible-send marker before performing supplier HTTP.
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt->id)
                ->update(['state' => 'form_unknown']);
            return ['attempt_id' => (string) $attempt->id,
                'partner_order_id' => (string) $attempt->request_key, 'book_hash' => $hash];
        }, 3);

        try {
            $form = app(RateHawkBookingService::class)->createForm(
                $context['partner_order_id'], $context['book_hash'], $userIp
            );
        } catch (Throwable $e) {
            DB::table('rt_supplier_booking_attempts')
                ->where('id', $context['attempt_id'])->where('state', 'form_unknown')
                ->update(['last_error_code' => substr(class_basename($e), 0, 120)]);
            throw new RuntimeException(
                'Supplier booking form outcome uncertain; investigate before any retry.', 0, $e
            );
        }

        // Encrypt before the transaction: no plaintext supplier response is stored in DB.
        // If encryption or DB persistence fails after HTTP, form_unknown remains and
        // requires reconciliation, never an automatic supplier retry.
        $encryptedForm = Crypt::encryptString(json_encode($form, JSON_THROW_ON_ERROR));
        DB::transaction(function () use ($context, $form, $encryptedForm): void {
            $attempt = DB::table('rt_supplier_booking_attempts')
                ->where('id', $context['attempt_id'])->lockForUpdate()->first();
            if (!$attempt || $attempt->state !== 'form_unknown') {
                throw new RuntimeException('Unexpected booking attempt state after supplier response.');
            }
            if (!isset($form['order_id']) || !is_scalar($form['order_id'])
                || (string) $form['order_id'] === '') {
                throw new RuntimeException('Supplier form has no order ID.');
            }
            DB::table('rt_supplier_external_refs')->insert([
                'id' => (string) Str::uuid(),
                'attempt_id' => $context['attempt_id'],
                'supplier_id' => $attempt->supplier_id,
                'reference_kind' => 'ratehawk_order_id',
                'reference_value' => (string) $form['order_id'],
            ]);
            DB::table('rt_ratehawk_booking_forms')->insert([
                'attempt_id' => (string) $attempt->id,
                'response_encrypted' => $encryptedForm,
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt->id)
                ->update(['state' => 'form_ready']);
        }, 3);

        // The form is now encrypted at rest; no finish or payment is invoked.
        return $form;
    }

    private function snapshot(object $attempt): array
    {
        return [
            'booking_id' => (string) $attempt->booking_id,
            'attempt_id' => (string) $attempt->id,
            'partner_order_id' => (string) $attempt->request_key,
            'state' => (string) $attempt->state,
        ];
    }
}
