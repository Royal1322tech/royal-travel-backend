<?php

namespace App\Console\Commands;

use App\Services\Loyalty\B2BLoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SyncB2BLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:sync {--limit=500 : Maximum bookings per run}';
    protected $description = 'Safely earn, release, or void B2B hotel loyalty points';

    public function handle(B2BLoyaltyService $loyalty): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $rules = DB::table('rt_loyalty_rules')->where('enabled', true)->count();
        $earned = 0; $released = 0; $voided = 0;
        if ($rules > 0) {
            // No historic backfill: earn() checks confirmed_at against rule.effective_from.
            $ids = DB::table('rt_bookings as b')->where('b.channel', 'b2b')->where('b.service_type', 'hotel')
                ->whereIn('b.status', ['confirmed', 'completed'])->whereNotNull('b.confirmed_at')
                ->whereNotNull('b.approved_total')->where('b.approved_total', '>', 0)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('rt_loyalty_booking_awards as a')->whereColumn('a.booking_id', 'b.id'))
                ->orderBy('b.confirmed_at')->limit($limit)->pluck('b.id');
            foreach ($ids as $id) if ($loyalty->earn($id) === 'earned_pending') $earned++;
        }
        $pending = DB::table('rt_loyalty_booking_awards as a')->join('rt_bookings as b', 'b.id', '=', 'a.booking_id')
            ->leftJoin('rt_hotel_booking_details as h', 'h.booking_id', '=', 'b.id')
            ->where('a.state', 'pending')
            ->where(fn ($q) => $q->whereIn('b.status', ['cancelled','canceled','refunded'])
                ->orWhere(fn ($q) => $q->whereIn('b.status', ['confirmed','completed'])->where('h.check_out', '<=', now()->toDateString())))
            ->orderBy('a.created_at')->limit($limit)->pluck('a.booking_id');
        foreach ($pending as $id) {
            $result = $loyalty->settle($id);
            if ($result === 'released') $released++;
            if ($result === 'voided') $voided++;
        }
        $this->info("Earned pending: {$earned}; Released: {$released}; Voided: {$voided}");
        return self::SUCCESS;
    }
}
