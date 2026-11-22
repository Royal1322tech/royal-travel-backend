<?php

declare(strict_types=1);

namespace App\Services\Hotels;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SupplierBookingAttempt;
use App\Modules\Bookings\Models\SupplierExternalReference;
use App\Services\Finance\B2BWalletReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Durable, conservative RateHawk B2B hotel booking workflow.
 * One supplier attempt per booking; uncertain supplier outcomes require reconciliation.
 * Do not call supplier HTTP inside a database transaction.
 */
final class RateHawkBookingOrchestrator
{
    private const OPERATION = 'ratehawk_hotel_booking';

    public function __construct(
        private readonly RateHawkBookingService $rateHawk,
        private readonly B2BWalletReservationService $wallet,
    ) {}

    /**
     * Reserve company funds and persist a stable supplier identifier.
     * Must be called only after server-side price/occupancy/tenant checks.
     */
    public function prepare(string $bookingId): array
    {
        return DB::transaction(function () use ($bookingId): array {
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $this->assertB2BHotel($booking);

            $existing = $this->attempt($bookingId);
            if ($existing !== null) {
                return $this->snapshot($existing);
            }
            if ($booking->status !== 'draft') {
                throw new RuntimeException('Only draft bookings can be prepared.');
            }
            if (!$booking->supplier_id || $booking->approved_total === null || (float) $booking->approved_total <= 0) {
                throw new RuntimeException('Booking supplier and approved total are required.');
            }

            $this->wallet->reserve($bookingId);
            $partnerOrderId = (string) Str::uuid();
            $attempt = new SupplierBookingAttempt();
            $attempt->id = (string) Str::uuid();
            $attempt->booking_id = $bookingId;
            $attempt->supplier_id = $booking->supplier_id;
            $attempt->operation = self::OPERATION;
            $attempt->attempt_no = 1;
            $attempt->state = 'prepared';
            $attempt->request_key = $partnerOrderId;
            $attempt->started_at = now();
            $attempt->save();
            $this->storeReference($attempt, 'partner_order_id', $partnerOrderId);
            return $this->snapshot($attempt);
        });
    }

    /**
     * The transition to form_unknown is committed BEFORE HTTP, so a timeout
     * cannot cause an automatic replay of the form call.
     */
    public function createForm(string $bookingId, string $prebookHash, string $userIp): array
    {
        $attempt = $this->claim($bookingId, 'prepared', 'form_unknown');
        try {
            $form = $this->rateHawk->createForm((string) $attempt->request_key, $prebookHash, $userIp);
        } catch (Throwable $e) {
            $this->recordError($attempt->id, $e);
            throw new RuntimeException('Booking form outcome uncertain. Reconcile before retrying.', 0, $e);
        }

        DB::transaction(function () use ($attempt, $form): void {
            $locked = SupplierBookingAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'form_unknown') {
                throw new RuntimeException('Booking attempt state changed unexpectedly.');
            }
            $this->storeReference($locked, 'ratehawk_order_id', (string) $form['order_id']);
            $locked->state = 'form_ready';
            $locked->save();
        });
        return $form;
    }

    /**
     * The caller must supply the exact form returned by createForm(), and
     * original occupancy/guest details. The form is not stored by this class.
     * If this process crashes after form creation, manual recovery is required.
     */
    public function startBooking(
        string $bookingId,
        array $bookingForm,
        array $rooms,
        string $contactEmail,
        string $contactPhone,
        ?string $hotelComment = null,
    ): array {
        // Reject invalid local input before the irreversible supplier-send marker.
        $this->validateFinishInput($bookingId, $bookingForm, $rooms, $contactEmail, $contactPhone, $hotelComment);
        $attempt = $this->claim($bookingId, 'form_ready', 'finish_unknown');
        try {
            $result = $this->rateHawk->startBooking(
                (string) $attempt->request_key,
                $bookingForm,
                $rooms,
                $contactEmail,
                $contactPhone,
                $hotelComment,
            );
        } catch (Throwable $e) {
            $this->recordError($attempt->id, $e);
            throw new RuntimeException('Booking finish outcome uncertain. Check status; never resubmit blindly.', 0, $e);
        }
        return $result;
    }

    /**
     * Safe for repeated calls; only status lookup is repeated, never finish.
     */
    public function reconcile(string $bookingId): array
    {
        $attempt = DB::transaction(function () use ($bookingId): SupplierBookingAttempt {
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $this->assertB2BHotel($booking);
            $attempt = $this->attempt($bookingId);
            if ($attempt === null || !in_array($attempt->state, ['finish_unknown', 'processing', 'confirmed'], true)) {
                throw new RuntimeException('Booking is not awaiting supplier finish reconciliation.');
            }
            return $attempt;
        });

        if ($attempt->state === 'confirmed') {
            return ['status' => 'ok', 'booking_id' => $bookingId];
        }
        try {
            $status = $this->rateHawk->checkStatus((string) $attempt->request_key);
        } catch (Throwable $e) {
            $this->recordError($attempt->id, $e);
            return ['status' => 'unknown', 'booking_id' => $bookingId];
        }

        if ($status['status'] === 'ok') {
            DB::transaction(function () use ($bookingId, $attempt): void {
                $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
                $locked = SupplierBookingAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($locked->state === 'confirmed') {
                    return;
                }
                if (!in_array($locked->state, ['finish_unknown', 'processing'], true)) {
                    throw new RuntimeException('Unexpected attempt state during confirmation.');
                }
                if (!in_array($booking->status, ['draft', 'pending_supplier'], true)) {
                    throw new RuntimeException('Unexpected booking status during confirmation.');
                }
                $booking->status = 'confirmed';
                $booking->confirmed_at = now();
                $booking->save();
                $this->wallet->capture($bookingId);
                $locked->state = 'confirmed';
                $locked->finished_at = now();
                $locked->save();
            });
            return ['status' => 'ok', 'booking_id' => $bookingId];
        }

        if ($status['status'] === 'processing' || $status['status'] === '3ds') {
            DB::transaction(function () use ($attempt): void {
                $locked = SupplierBookingAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if (in_array($locked->state, ['finish_unknown', 'processing'], true)) {
                    $locked->state = 'processing';
                    $locked->save();
                }
            });
            return ['status' => $status['status'], 'booking_id' => $bookingId];
        }

        // 'error' may be definitive only after supplier-specific classification.
        // Keep funds held and do not mark failed or release automatically.
        $this->recordError($attempt->id, new RuntimeException('Supplier reported error; manual classification required.'));
        return ['status' => 'supplier_error_review', 'booking_id' => $bookingId];
    }

    /**
     * Local preflight only. Supplier may still reject valid input; after claim,
     * every exception is conservatively treated as an unknown outcome.
     */
    private function validateFinishInput(
        string $bookingId,
        array $form,
        array $rooms,
        string $email,
        string $phone,
        ?string $comment,
    ): void {
        $attempt = $this->attempt($bookingId);
        if ($attempt === null || $attempt->state !== 'form_ready') {
            throw new RuntimeException('Booking form is not ready.');
        }
        if (($form['partner_order_id'] ?? null) !== $attempt->request_key) {
            throw new RuntimeException('Booking form partner ID mismatch.');
        }
        $orderId = $form['order_id'] ?? null;
        if (!is_int($orderId) || $orderId <= 0 ||
            !SupplierExternalReference::query()->where('attempt_id', $attempt->id)
                ->where('reference_kind', 'ratehawk_order_id')
                ->where('reference_value', (string) $orderId)->exists()) {
            throw new RuntimeException('Booking form order ID mismatch.');
        }
        $options = $form['payment_types'] ?? null;
        $validDeposit = false;
        if (is_array($options)) {
            foreach ($options as $option) {
                if (is_array($option) && ($option['type'] ?? null) === 'deposit'
                    && ($option['is_need_credit_card_data'] ?? false) === false
                    && is_string($option['amount'] ?? null)
                    && preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', $option['amount']) === 1
                    && (float) $option['amount'] > 0
                    && is_string($option['currency_code'] ?? null)
                    && preg_match('/^[A-Z]{3}$/D', $option['currency_code']) === 1) {
                    $validDeposit = true;
                    break;
                }
            }
        }
        if (!$validDeposit) {
            throw new RuntimeException('Valid deposit payment option is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($phone) < 5 || strlen($phone) > 35) {
            throw new RuntimeException('Invalid booking contact details.');
        }
        if ($comment !== null && (mb_strlen($comment) < 1 || mb_strlen($comment) > 256)) {
            throw new RuntimeException('Invalid hotel comment.');
        }
        if (!array_is_list($rooms) || $rooms === []) {
            throw new RuntimeException('Rooms must be a nonempty list.');
        }
        $requiresGender = ($form['is_gender_specification_required'] ?? false) === true;
        foreach ($rooms as $room) {
            if (!is_array($room) || !isset($room['guests']) || !is_array($room['guests'])
                || !array_is_list($room['guests']) || $room['guests'] === []) {
                throw new RuntimeException('Each room must contain guests.');
            }
            foreach ($room['guests'] as $guest) {
                if (!is_array($guest)) {
                    throw new RuntimeException('Invalid guest.');
                }
                foreach (['first_name', 'last_name'] as $field) {
                    $name = $guest[$field] ?? null;
                    if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 50
                        || preg_match('/^[\p{L}\p{M} \'\-,\.\x{2019}]+$/uD', $name) !== 1) {
                        throw new RuntimeException('Invalid guest name.');
                    }
                }
                if (isset($guest['is_child']) && !is_bool($guest['is_child'])) {
                    throw new RuntimeException('Invalid child flag.');
                }
                if (($guest['is_child'] ?? false) === true
                    && (!is_int($guest['age'] ?? null) || $guest['age'] < 0 || $guest['age'] > 17)) {
                    throw new RuntimeException('Invalid child age.');
                }
                if ($requiresGender && !isset($guest['gender'])) {
                    throw new RuntimeException('Guest gender is required.');
                }
                if (isset($guest['gender']) && !in_array($guest['gender'], ['male', 'female', 'unknown'], true)) {
                    throw new RuntimeException('Invalid guest gender.');
                }
                foreach (array_keys($guest) as $field) {
                    if (!in_array($field, ['first_name', 'last_name', 'is_child', 'age', 'gender'], true)) {
                        throw new RuntimeException('Unexpected guest field.');
                    }
                }
            }
        }
    }

    private function claim(string $bookingId, string $expected, string $next): SupplierBookingAttempt
    {
        return DB::transaction(function () use ($bookingId, $expected, $next): SupplierBookingAttempt {
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $this->assertB2BHotel($booking);
            $attempt = $this->attempt($bookingId);
            if ($attempt === null || $attempt->state !== $expected) {
                throw new RuntimeException('Supplier operation already started or is not ready.');
            }
            $attempt->state = $next;
            $attempt->save();
            return $attempt;
        });
    }

    private function attempt(string $bookingId): ?SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $bookingId)
            ->where('operation', self::OPERATION)
            ->orderBy('attempt_no')
            ->first();
    }

    private function storeReference(SupplierBookingAttempt $attempt, string $kind, string $value): void
    {
        $reference = new SupplierExternalReference();
        $reference->id = (string) Str::uuid();
        $reference->attempt_id = $attempt->id;
        $reference->supplier_id = $attempt->supplier_id;
        $reference->reference_kind = $kind;
        $reference->reference_value = $value;
        $reference->save();
    }

    private function snapshot(SupplierBookingAttempt $attempt): array
    {
        return [
            'booking_id' => (string) $attempt->booking_id,
            'attempt_id' => (string) $attempt->id,
            'partner_order_id' => (string) $attempt->request_key,
            'state' => (string) $attempt->state,
        ];
    }

    private function recordError(string $attemptId, Throwable $error): void
    {
        DB::transaction(function () use ($attemptId, $error): void {
            $attempt = SupplierBookingAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
            if ($attempt !== null && $attempt->state !== 'confirmed') {
                $attempt->last_error_code = substr(class_basename($error), 0, 120);
                $attempt->save();
            }
        });
    }

    private function assertB2BHotel(Booking $booking): void
    {
        if ($booking->channel !== 'b2b' || $booking->service_type !== 'hotel'
            || !$booking->company_id || $booking->currency !== 'USD') {
            throw new RuntimeException('Only USD B2B company hotel bookings are supported.');
        }
    }
}
