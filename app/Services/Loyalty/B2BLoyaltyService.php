<?php

namespace App\Services\Loyalty;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class B2BLoyaltyService
{
    public function balances(string $companyId): array
    {
        $totals = DB::table('rt_loyalty_entries')->where('company_id', $companyId)
            ->selectRaw("COALESCE(SUM(CASE WHEN balance_bucket='pending' THEN points ELSE 0 END),0) pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN balance_bucket='available' THEN points ELSE 0 END),0) available")
            ->first();
        return ['pending' => (int) $totals->pending, 'available' => (int) $totals->available];
    }

    // Points are calculated from the booking's server-approved total, never from a client-submitted price.
    // A rule is currency-specific: 1% equivalent = 1 point per 100 currency units.
    public function earn(string $bookingId): string
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = DB::table('rt_bookings')->where('id', $bookingId)->lockForUpdate()->first();
            if (!$booking || $booking->channel !== 'b2b' || $booking->service_type !== 'hotel' || !$booking->company_id) return 'not_eligible';
            if (DB::table('rt_loyalty_booking_awards')->where('booking_id', $bookingId)->exists()) return 'already_processed';
            if (!in_array($booking->status, ['confirmed', 'completed'], true) || !$booking->confirmed_at || !$booking->approved_total || (float) $booking->approved_total <= 0) return 'not_confirmed';
            $rule = DB::table('rt_loyalty_rules')->where('currency', $booking->currency)
                ->where('enabled', true)->where('effective_from', '<=', $booking->confirmed_at)
                ->where(function ($q) use ($booking) { $q->where('company_id', $booking->company_id)->orWhereNull('company_id'); })
                ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')->first();
            if (!$rule) return 'no_active_rule';
            // BCMath avoids floating-point monetary rounding. Floor fractional points.
            $points = (int) bcdiv(bcmul((string) $booking->approved_total, (string) $rule->points_per_100, 6), '100', 0);
            if ($points <= 0) return 'zero_points';
            if ($points > 1000000000) return 'points_limit';
            $now = now();
            DB::table('rt_loyalty_booking_awards')->insert([
                'booking_id' => $bookingId, 'company_id' => $booking->company_id, 'rule_id' => $rule->id,
                'currency' => $booking->currency, 'booking_total' => $booking->approved_total,
                'points' => $points, 'state' => 'pending', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->entry($booking->company_id, $bookingId, null, 'booking_earn', $points, 'pending', 'earn:'.$bookingId, 'Confirmed hotel booking');
            return 'earned_pending';
        }, 3);
    }

    public function settle(string $bookingId): string
    {
        return DB::transaction(function () use ($bookingId) {
            $award = DB::table('rt_loyalty_booking_awards')->where('booking_id', $bookingId)->lockForUpdate()->first();
            if (!$award || $award->state !== 'pending') return 'nothing_pending';
            $booking = DB::table('rt_bookings')->where('id', $bookingId)->first();
            if (!$booking) return 'missing_booking';
            if (in_array($booking->status, ['cancelled', 'canceled', 'refunded'], true)) {
                $this->entry($award->company_id, $bookingId, null, 'booking_void', -$award->points, 'pending', 'void:'.$bookingId, 'Booking cancelled or refunded');
                DB::table('rt_loyalty_booking_awards')->where('booking_id', $bookingId)->update(['state' => 'void', 'updated_at' => now()]);
                return 'voided';
            }
            $stay = DB::table('rt_hotel_booking_details')->where('booking_id', $bookingId)->first();
            if (!$stay || !in_array($booking->status, ['confirmed', 'completed'], true) || $stay->check_out > now()->toDateString()) return 'not_ready';
            $this->entry($award->company_id, $bookingId, null, 'booking_release', -$award->points, 'pending', 'release-pending:'.$bookingId, 'Stay completed');
            $this->entry($award->company_id, $bookingId, null, 'booking_release', $award->points, 'available', 'release-available:'.$bookingId, 'Stay completed');
            DB::table('rt_loyalty_booking_awards')->where('booking_id', $bookingId)->update(['state' => 'available', 'updated_at' => now()]);
            return 'released';
        }, 3);
    }

    public function manual(string $companyId, int $actorId, int $signedPoints, string $reason): void
    {
        if ($signedPoints === 0 || abs($signedPoints) > 1000000) throw ValidationException::withMessages(['points' => 'Invalid points amount.']);
        DB::transaction(function () use ($companyId, $actorId, $signedPoints, $reason) {
            // Lock the company to serialize all manual adjustments for this company.
            $company = DB::table('rt_companies')->where('id', $companyId)->lockForUpdate()->first();
            if (!$company) throw ValidationException::withMessages(['company_id' => 'Company not found.']);
            if ($signedPoints < 0 && $this->balances($companyId)['available'] < abs($signedPoints)) {
                throw ValidationException::withMessages(['points' => 'Insufficient available points.']);
            }
            $this->entry($companyId, null, $actorId, $signedPoints > 0 ? 'manual_credit' : 'manual_debit', $signedPoints, 'available', 'manual:'.Str::uuid(), $reason);
        }, 3);
    }

    private function entry(string $companyId, ?string $bookingId, ?int $actorId, string $type, int $points, string $bucket, string $key, string $note): void
    {
        DB::table('rt_loyalty_entries')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'booking_id' => $bookingId,
            'actor_user_id' => $actorId, 'entry_type' => $type, 'points' => $points,
            'balance_bucket' => $bucket, 'dedupe_key' => $key, 'note' => $note, 'created_at' => now(),
        ]);
    }
}
