<?php

declare(strict_types=1);

namespace App\Services\Hotels;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Admin B2C sandbox-only supplier Finish workflow.
 * No wallet/payment capture. No controller/route is registered intentionally.
 * Call only after explicit operator approval; NEVER retry Finish after uncertainty.
 */
final class AdminRateHawkBookingFinishService
{
    private const OPERATION = 'ratehawk_hotel_booking';
    private const MAX_PREBOOK_AGE_SECONDS = 600;

    public function __construct(private readonly RateHawkBookingService $rateHawk) {}

    /** Read-only preflight. Returns no personal data or supplier hashes. */
    public function preview(string $bookingId): array
    {
        $data = $this->loadAndValidate($bookingId);
        return [
            'booking_id' => $bookingId,
            'attempt_id' => (string) $data['attempt']->id,
            'state' => (string) $data['attempt']->state,
            'currency' => 'USD',
            'supplier_cost' => (string) $data['price']->supplier_cost,
            'room_count' => count($data['rooms']),
            'guest_count' => array_sum(array_map(static fn (array $room): int => count($room['guests']), $data['rooms'])),
            'sandbox' => true,
            'finish_enabled' => config('services.ratehawk.allow_sandbox_finish') === true,
        ];
    }

    /** Sends ONE supplier Finish request; never called from a public route. */
    public function finish(string $bookingId): array
    {
        $this->assertSandbox();
        if (config('services.ratehawk.allow_sandbox_finish') !== true) {
            throw new RuntimeException('Sandbox Finish is disabled. Explicit approval and configuration required.');
        }

        // Revalidate under lock immediately before committing the irreversible-send marker.
        $data = DB::transaction(function () use ($bookingId): array {
            $data = $this->loadAndValidate($bookingId, true);
            DB::table('rt_supplier_booking_attempts')->where('id', $data['attempt']->id)
                ->where('state', 'form_ready')->update(['state' => 'finish_unknown']);
            return $data;
        }, 3);

        try {
            $this->rateHawk->startBooking(
                (string) $data['attempt']->request_key,
                $data['form'],
                $data['rooms'],
                $data['email'],
                $data['phone'],
            );
        } catch (Throwable $e) {
            DB::table('rt_supplier_booking_attempts')->where('id', $data['attempt']->id)
                ->where('state', 'finish_unknown')
                ->update(['last_error_code' => substr(class_basename($e), 0, 120)]);
            throw new RuntimeException('Finish outcome UNKNOWN. Do not retry; use reconcile().', 0, $e);
        }
        return ['booking_id' => $bookingId, 'state' => 'finish_unknown', 'next' => 'reconcile'];
    }

    /** Read-only supplier status request, followed by guarded local state update. */
    public function reconcile(string $bookingId): array
    {
        $this->assertSandbox();
        $context = DB::transaction(function () use ($bookingId): array {
            $booking = DB::table('rt_bookings')->where('id', $bookingId)->lockForUpdate()->first();
            $this->assertB2C($booking);
            $attempt = DB::table('rt_supplier_booking_attempts')
                ->where('booking_id', $bookingId)->where('operation', self::OPERATION)
                ->lockForUpdate()->first();
            if (!$attempt || !in_array($attempt->state, ['finish_unknown', 'processing', 'confirmed'], true)) {
                throw new RuntimeException('Booking is not awaiting Finish reconciliation.');
            }
            if ($attempt->state === 'confirmed') {
                return ['confirmed' => true];
            }
            return ['confirmed' => false, 'attempt_id' => (string) $attempt->id,
                'partner_order_id' => (string) $attempt->request_key];
        });
        if ($context['confirmed']) {
            return ['booking_id' => $bookingId, 'status' => 'ok'];
        }
        try {
            $status = $this->rateHawk->checkStatus($context['partner_order_id']);
        } catch (Throwable $e) {
            return ['booking_id' => $bookingId, 'status' => 'unknown'];
        }
        if ($status['status'] === 'ok') {
            DB::transaction(function () use ($bookingId, $context): void {
                $booking = DB::table('rt_bookings')->where('id', $bookingId)->lockForUpdate()->first();
                $this->assertB2C($booking);
                $attempt = DB::table('rt_supplier_booking_attempts')->where('id', $context['attempt_id'])
                    ->lockForUpdate()->first();
                if ($attempt->state === 'confirmed') { return; }
                if (!in_array($attempt->state, ['finish_unknown', 'processing'], true)
                    || !in_array($booking->status, ['draft', 'pending_supplier'], true)) {
                    throw new RuntimeException('Unexpected booking state during reconciliation.');
                }
                DB::table('rt_bookings')->where('id', $bookingId)
                    ->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                DB::table('rt_supplier_booking_attempts')->where('id', $attempt->id)
                    ->update(['state' => 'confirmed', 'finished_at' => now(), 'last_error_code' => null]);
            }, 3);
            return ['booking_id' => $bookingId, 'status' => 'ok'];
        }
        if (in_array($status['status'], ['processing', '3ds'], true)) {
            DB::table('rt_supplier_booking_attempts')->where('id', $context['attempt_id'])
                ->whereIn('state', ['finish_unknown', 'processing'])->update(['state' => 'processing']);
            return ['booking_id' => $bookingId, 'status' => $status['status']];
        }
        // Error classification is supplier-specific. Do not release funds or retry.
        return ['booking_id' => $bookingId, 'status' => 'supplier_error_review',
            'supplier_error' => is_string($status['error'] ?? null) ? $status['error'] : null];
    }

    private function loadAndValidate(string $bookingId, bool $lock = false): array
    {
        $this->assertSandbox();
        $bookingQuery = DB::table('rt_bookings')->where('id', $bookingId);
        $booking = ($lock ? $bookingQuery->lockForUpdate() : $bookingQuery)->first();
        $this->assertB2C($booking);
        if ($booking->status !== 'draft') {
            throw new RuntimeException('Expected draft booking.');
        }
        $attemptQuery = DB::table('rt_supplier_booking_attempts')
            ->where('booking_id', $bookingId)->where('operation', self::OPERATION);
        $attempt = ($lock ? $attemptQuery->lockForUpdate() : $attemptQuery)->first();
        if (!$attempt || $attempt->state !== 'form_ready'
            || (string) $attempt->supplier_id !== (string) $booking->supplier_id) {
            throw new RuntimeException('Booking form is not ready or Finish was already claimed.');
        }
        $supplier = DB::table('rt_suppliers')->where('id', $booking->supplier_id)
            ->where('code', 'RATEHAWK')->where('adapter_code', 'RATEHAWK')
            ->where('status', 'active')->exists();
        if (!$supplier) { throw new RuntimeException('RateHawk supplier is not active.'); }
        $offer = DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->first();
        $hotel = DB::table('rt_hotel_booking_details')->where('booking_id', $bookingId)->first();
        $price = DB::table('rt_booking_price_snapshots')
            ->where('booking_id', $bookingId)->orderByDesc('version')->first();
        $stored = DB::table('rt_ratehawk_booking_forms')->where('attempt_id', $attempt->id)->first();
        if (!$offer || !$hotel || !$price || !$stored || $offer->environment !== 'sandbox'
            || $offer->currency !== 'USD' || $price->currency !== 'USD'
            || filter_var($offer->price_changed, FILTER_VALIDATE_BOOLEAN)
            || !hash_equals((string) $offer->prebook_hash, (string) $hotel->supplier_offer_ref)
            || !preg_match('/^p-\S+$/D', (string) $offer->prebook_hash)) {
            throw new RuntimeException('Inconsistent RateHawk offer, booking form, or price.');
        }
        $age = now('UTC')->getTimestamp() - CarbonImmutable::parse($offer->prebook_verified_at, 'UTC')->getTimestamp();
        if ($age < 0 || $age >= self::MAX_PREBOOK_AGE_SECONDS) {
            throw new RuntimeException('Prebook is stale. Do not Finish; investigate existing form before new draft.');
        }
        foreach ([(string) $offer->supplier_cost, (string) $price->supplier_cost] as $amount) {
            if (!preg_match('/^\d+(?:\.\d{1,6})?$/D', $amount) || bccomp($amount, '0', 6) <= 0) {
                throw new RuntimeException('Invalid supplier cost.');
            }
        }
        if (bccomp((string) $offer->supplier_cost, (string) $price->supplier_cost, 6) !== 0) {
            throw new RuntimeException('Supplier price changed since Prebook.');
        }
        $form = json_decode(Crypt::decryptString($stored->response_encrypted), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($form) || ($form['partner_order_id'] ?? null) !== (string) $attempt->request_key
            || !isset($form['order_id']) || !is_scalar($form['order_id'])
            || !DB::table('rt_supplier_external_refs')->where('attempt_id', $attempt->id)
                ->where('reference_kind', 'ratehawk_order_id')
                ->where('reference_value', (string) $form['order_id'])->exists()) {
            throw new RuntimeException('Stored booking form does not match supplier references.');
        }
        // RateHawkBookingService selects the FIRST eligible deposit; match precisely.
        $deposit = null;
        foreach (($form['payment_types'] ?? []) as $option) {
            if (is_array($option) && ($option['type'] ?? null) === 'deposit'
                && ($option['is_need_credit_card_data'] ?? false) === false
                && is_numeric($option['amount'] ?? null)
                && is_string($option['currency_code'] ?? null)
                && preg_match('/^[A-Z]{3}$/D', $option['currency_code'])) {
                $deposit = $option; break;
            }
        }
        if (!$deposit || $deposit['currency_code'] !== 'USD'
            || !preg_match('/^\d+(?:\.\d{1,6})?$/D', (string) $deposit['amount'])
            || bccomp((string) $deposit['amount'], (string) $price->supplier_cost, 6) !== 0) {
            throw new RuntimeException('Booking form deposit differs from verified supplier cost.');
        }
        $contact = DB::table('rt_booking_supplier_contacts')->where('booking_id', $bookingId)->first();
        if (!$contact) { throw new RuntimeException('Missing encrypted contact.'); }
        $email = Crypt::decryptString($contact->email_encrypted);
        $phone = Crypt::decryptString($contact->phone_encrypted);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($phone) < 5 || strlen($phone) > 35) {
            throw new RuntimeException('Invalid contact details.');
        }
        $guests = DB::table('rt_booking_guests')->where('booking_id', $bookingId)
            ->orderBy('room_index')->orderByDesc('is_lead')->orderBy('id')->get();
        $rooms = [];
        foreach ($guests as $guest) {
            $index = (int) $guest->room_index;
            if ($index < 0 || $index >= (int) $hotel->rooms_count) {
                throw new RuntimeException('Guest room index mismatch.');
            }
            $g = ['first_name' => (string) $guest->first_name, 'last_name' => (string) $guest->last_name,
                'is_child' => $guest->guest_type === 'child'];
            if ($g['is_child']) { $g['age'] = (int) $guest->age; }
            if ($guest->gender !== null) { $g['gender'] = (string) $guest->gender; }
            $rooms[$index]['guests'][] = $g;
        }
        if (count($rooms) !== (int) $hotel->rooms_count || array_keys($rooms) !== range(0, count($rooms) - 1)) {
            throw new RuntimeException('Room occupancy mismatch.');
        }
        if (($form['is_gender_specification_required'] ?? false) === true) {
            foreach ($rooms as $room) {
                foreach ($room['guests'] as $guest) {
                    if (!isset($guest['gender'])) { throw new RuntimeException('Supplier requires guest gender.'); }
                }
            }
        }
        return compact('attempt', 'form', 'rooms', 'email', 'phone', 'price');
    }

    private function assertSandbox(): void
    {
        if (config('services.ratehawk.environment') !== 'sandbox'
            || config('services.ratehawk.sandbox_url') !== 'https://api-sandbox.ratehawk.com') {
            throw new RuntimeException('Sandbox-only booking confirmation service.');
        }
    }

    private function assertB2C(?object $booking): void
    {
        if (!$booking || $booking->channel !== 'b2c' || $booking->service_type !== 'hotel'
            || $booking->company_id !== null || $booking->currency !== 'USD') {
            throw new RuntimeException('Only USD B2C hotel bookings are supported.');
        }
    }
}
