<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * B2B USD wallet holds for hotel bookings.
 * Call reserve only after trusted price/prebook validation.
 * Call capture/release only from trusted supplier orchestration.
 */
final class B2BWalletReservationService
{
    private const MAX_CENTS = 900000000000000;

    public function reserve(string $bookingId): array
    {
        return DB::transaction(function () use ($bookingId): array {
            $booking = $this->booking($bookingId);
            $companyId = $this->validateBooking($booking);
            $this->lockCompany($companyId);
            $account = $this->walletAccount($companyId);
            $amountCents = $this->dollarsToCents((string) $booking->approved_total);

            $existing = $this->reservation($bookingId);
            if ($existing !== null) {
                $this->assertReservationMatches($existing, $companyId, $amountCents);
                if ($existing->state === 'held') {
                    return $this->result('held', $bookingId, $amountCents, true);
                }
                throw ValidationException::withMessages([
                    'booking' => 'A wallet reservation for this booking has already been finalized.',
                ]);
            }

            if ($booking->status !== 'draft') {
                throw ValidationException::withMessages([
                    'booking' => 'Only a validated draft booking can reserve wallet funds.',
                ]);
            }

            $wallet = (int) $account->wallet_cents;
            $reserved = (int) $account->wallet_reserved_cents;
            if ($wallet < 0 || $reserved < 0 || $reserved > $wallet || $amountCents > $wallet - $reserved) {
                throw ValidationException::withMessages(['wallet' => 'Insufficient available wallet balance.']);
            }

            $balanceId = $this->balanceAccountId($companyId);
            DB::table('rt_financial_reservations')->insert([
                'id' => (string) Str::uuid(),
                'booking_id' => $bookingId,
                'balance_account_id' => $balanceId,
                'credit_account_id' => null,
                'amount' => $this->centsToDollars($amountCents),
                'currency' => 'USD',
                'state' => 'held',
                'expires_at' => null,
                'created_at' => now(),
                'released_at' => null,
            ]);
            DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->update([
                'wallet_reserved_cents' => $reserved + $amountCents,
                'updated_at' => now(),
            ]);
            return $this->result('held', $bookingId, $amountCents, false);
        }, 3);
    }

    public function capture(string $bookingId): array
    {
        return DB::transaction(function () use ($bookingId): array {
            $booking = $this->booking($bookingId);
            $companyId = $this->validateBooking($booking);
            $this->lockCompany($companyId);
            $account = $this->walletAccount($companyId);
            $reservation = $this->reservation($bookingId);
            if ($reservation === null) {
                throw ValidationException::withMessages(['booking' => 'No wallet reservation exists.']);
            }
            $amountCents = $this->dollarsToCents((string) $booking->approved_total);
            $this->assertReservationMatches($reservation, $companyId, $amountCents);
            if ($reservation->state === 'captured') {
                return $this->result('captured', $bookingId, $amountCents, true);
            }
            if ($reservation->state !== 'held') {
                throw ValidationException::withMessages(['booking' => 'Reservation is not held.']);
            }

            // This service must be called only after verified supplier confirmation.
            // A draft booking is not proof of supplier confirmation.
            if ($booking->status !== 'confirmed' || $booking->confirmed_at === null) {
                throw ValidationException::withMessages([
                    'booking' => 'Supplier confirmation must be persisted before capture.',
                ]);
            }

            $wallet = (int) $account->wallet_cents;
            $reserved = (int) $account->wallet_reserved_cents;
            if ($wallet < $amountCents || $reserved < $amountCents) {
                throw new LogicException('Wallet reservation/account balance inconsistency.');
            }
            $newWallet = $wallet - $amountCents;
            $newReserved = $reserved - $amountCents;
            $reference = 'booking:' . $bookingId . ':wallet:capture';
            $exists = DB::table('rt_b2b_finance_entries')
                ->where('company_id', $companyId)->where('reference', $reference)->exists();
            if ($exists) {
                throw new LogicException('Capture ledger exists while reservation is still held.');
            }

            DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->update([
                'wallet_cents' => $newWallet,
                'wallet_reserved_cents' => $newReserved,
                'updated_at' => now(),
            ]);
            DB::table('rt_financial_reservations')->where('id', $reservation->id)->update([
                'state' => 'captured',
            ]);
            DB::table('rt_b2b_finance_entries')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'type' => 'wallet_booking_capture',
                'amount_cents' => $amountCents,
                'wallet_after_cents' => $newWallet,
                'limit_after_cents' => (int) $account->credit_limit_cents,
                'used_after_cents' => (int) $account->credit_used_cents,
                'frozen_after' => (bool) $account->credit_frozen,
                'reference' => $reference,
                'reason' => 'Wallet payment for confirmed hotel booking ' . $bookingId,
                'actor_id' => (int) DB::table('rt_orders')->where('id', $booking->order_id)->value('created_by'),
                'created_at' => now(),
            ]);
            return $this->result('captured', $bookingId, $amountCents, false);
        }, 3);
    }

    public function release(string $bookingId): array
    {
        return DB::transaction(function () use ($bookingId): array {
            $booking = $this->booking($bookingId);
            $companyId = $this->validateBooking($booking);
            $this->lockCompany($companyId);
            $account = $this->walletAccount($companyId);
            $reservation = $this->reservation($bookingId);
            if ($reservation === null) {
                throw ValidationException::withMessages(['booking' => 'No wallet reservation exists.']);
            }
            $amountCents = $this->dollarsToCents((string) $booking->approved_total);
            $this->assertReservationMatches($reservation, $companyId, $amountCents);
            if ($reservation->state === 'released') {
                return $this->result('released', $bookingId, $amountCents, true);
            }
            if ($reservation->state !== 'held') {
                throw ValidationException::withMessages(['booking' => 'Captured funds cannot be released as a hold.']);
            }
            if (!in_array($booking->status, ['failed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Release requires a persisted definitive failed/cancelled booking status.',
                ]);
            }
            $reserved = (int) $account->wallet_reserved_cents;
            if ($reserved < $amountCents) {
                throw new LogicException('Reserved wallet balance is inconsistent.');
            }
            DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->update([
                'wallet_reserved_cents' => $reserved - $amountCents,
                'updated_at' => now(),
            ]);
            DB::table('rt_financial_reservations')->where('id', $reservation->id)->update([
                'state' => 'released',
                'released_at' => now(),
            ]);
            return $this->result('released', $bookingId, $amountCents, false);
        }, 3);
    }

    private function booking(string $bookingId): object
    {
        return DB::table('rt_bookings')->where('id', $bookingId)->first()
            ?? throw ValidationException::withMessages(['booking' => 'Booking not found.']);
    }

    private function validateBooking(object $booking): string
    {
        if ($booking->channel !== 'b2b' || $booking->service_type !== 'hotel'
            || !$booking->company_id || trim((string) $booking->currency) !== 'USD'
            || $booking->approved_total === null) {
            throw ValidationException::withMessages(['booking' => 'Not an approved USD B2B hotel booking.']);
        }
        $order = DB::table('rt_orders')->where('id', $booking->order_id)->first();
        if (!$order || $order->company_id !== $booking->company_id
            || $order->channel !== 'b2b' || trim((string) $order->currency) !== 'USD') {
            throw ValidationException::withMessages(['booking' => 'Booking/order company or channel mismatch.']);
        }
        return (string) $booking->company_id;
    }

    private function lockCompany(string $companyId): void
    {
        DB::table('rt_companies')->where('id', $companyId)->lockForUpdate()->firstOrFail();
    }

    private function walletAccount(string $companyId): object
    {
        return DB::table('rt_b2b_finance_accounts')
            ->where('company_id', $companyId)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['wallet' => 'Company wallet account not found.']);
    }

    private function balanceAccountId(string $companyId): string
    {
        $row = DB::table('rt_balance_accounts')
            ->where('company_id', $companyId)->where('currency', 'USD')->first();
        if ($row !== null) {
            if ($row->status !== 'active') {
                throw ValidationException::withMessages(['wallet' => 'Balance account is not active.']);
            }
            return (string) $row->id;
        }
        $id = (string) Str::uuid();
        DB::table('rt_balance_accounts')->insert([
            'id' => $id, 'company_id' => $companyId, 'currency' => 'USD', 'status' => 'active',
        ]);
        return $id;
    }

    private function reservation(string $bookingId): ?object
    {
        $rows = DB::table('rt_financial_reservations')
            ->where('booking_id', $bookingId)->lockForUpdate()->get();
        if ($rows->count() > 1) {
            throw new LogicException('Multiple financial reservations exist for this booking.');
        }
        return $rows->first();
    }

    private function assertReservationMatches(object $reservation, string $companyId, int $amountCents): void
    {
        if ($reservation->balance_account_id === null || $reservation->credit_account_id !== null
            || trim((string) $reservation->currency) !== 'USD'
            || $this->dollarsToCents((string) $reservation->amount) !== $amountCents) {
            throw new LogicException('Wallet reservation does not match the booking amount/type.');
        }
        $balance = DB::table('rt_balance_accounts')->where('id', $reservation->balance_account_id)->first();
        if (!$balance || $balance->company_id !== $companyId || trim((string) $balance->currency) !== 'USD') {
            throw new LogicException('Wallet reservation belongs to another company.');
        }
    }

    private function dollarsToCents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,6}))?$/', $amount, $m)) {
            throw ValidationException::withMessages(['amount' => 'Invalid USD amount.']);
        }
        $fraction = str_pad($m[2] ?? '', 6, '0');
        if (substr($fraction, 2) !== '0000') {
            throw ValidationException::withMessages(['amount' => 'USD price has fractions smaller than one cent.']);
        }
        $whole = $m[1];
        if (strlen($whole) > 12 || (int) $whole > intdiv(self::MAX_CENTS, 100)) {
            throw ValidationException::withMessages(['amount' => 'USD amount exceeds wallet limit.']);
        }
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);
        if ($cents <= 0 || $cents > self::MAX_CENTS) {
            throw ValidationException::withMessages(['amount' => 'USD amount out of range.']);
        }
        return $cents;
    }

    private function centsToDollars(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function result(string $state, string $bookingId, int $amountCents, bool $idempotent): array
    {
        return ['booking_id' => $bookingId, 'state' => $state,
            'amount_cents' => $amountCents, 'idempotent' => $idempotent];
    }
}
