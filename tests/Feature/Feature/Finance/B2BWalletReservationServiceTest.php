<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\B2BFinanceService;
use App\Services\Finance\B2BWalletReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Local-only integration test. Uses existing company wallet and user, and rolls
 * back every insert/update. Never run against production or in parallel.
 */
final class B2BWalletReservationServiceTest extends TestCase
{
    public function test_wallet_reserve_release_capture_and_idempotency(): void
    {
        if (app()->environment('production')) {
            $this->fail('Never run this test in production.');
        }

        $companyId = env('WALLET_TEST_COMPANY_ID');
        if (! $companyId) {
            $this->markTestSkipped('Set WALLET_TEST_COMPANY_ID for a local test company with at least $100 available.');
        }

        $wallet = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
        $company = DB::table('rt_companies')->where('id', $companyId)->first();
        $userId = DB::table('users')->whereNotNull('id')->orderBy('id')->value('id');
        if (! $company || ! $wallet || ! $userId) {
            $this->markTestSkipped('Company, wallet or test user missing.');
        }
        if (trim((string) $wallet->currency) !== 'USD' ||
            (int) $wallet->wallet_cents - (int) $wallet->wallet_reserved_cents < 10000) {
            $this->markTestSkipped('USD wallet must have at least $100 available.');
        }

        DB::beginTransaction();
        try {
            $service = app(B2BWalletReservationService::class);
            $finance = app(B2BFinanceService::class);
            $before = $finance->snapshot($companyId);

            $releaseBooking = $this->createBooking($companyId, (int) $userId, '50.000000');
            $first = $service->reserve($releaseBooking);
            $this->assertSame('held', $first['state']);
            $this->assertFalse($first['idempotent']);
            $this->assertTrue($service->reserve($releaseBooking)['idempotent']);
            $held = $finance->snapshot($companyId);
            $this->assertSame($before['wallet_cents'], $held['wallet_cents']);
            $this->assertSame($before['wallet_reserved_cents'] + 5000, $held['wallet_reserved_cents']);
            $this->assertSame($before['wallet_available_cents'] - 5000, $held['wallet_available_cents']);

            DB::table('rt_bookings')->where('id', $releaseBooking)->update(['status' => 'failed']);
            $this->assertSame('released', $service->release($releaseBooking)['state']);
            $this->assertTrue($service->release($releaseBooking)['idempotent']);
            $this->assertSame($before['wallet_reserved_cents'], $finance->snapshot($companyId)['wallet_reserved_cents']);

            $captureBooking = $this->createBooking($companyId, (int) $userId, '50.000000');
            $service->reserve($captureBooking);
            // Simulates persisted confirmation ONLY to test wallet accounting.
            // This does not prove supplier confirmation or authorize live capture.
            DB::table('rt_bookings')->where('id', $captureBooking)->update([
                'status' => 'confirmed', 'confirmed_at' => now(),
            ]);
            $this->assertSame('captured', $service->capture($captureBooking)['state']);
            $this->assertTrue($service->capture($captureBooking)['idempotent']);
            $after = $finance->snapshot($companyId);
            $this->assertSame($before['wallet_cents'] - 5000, $after['wallet_cents']);
            $this->assertSame($before['wallet_reserved_cents'], $after['wallet_reserved_cents']);
            $this->assertSame(1, DB::table('rt_b2b_finance_entries')
                ->where('company_id', $companyId)
                ->where('reference', 'booking:' . $captureBooking . ':wallet:capture')->count());
        } finally {
            DB::rollBack();
        }
    }

    private function createBooking(string $companyId, int $userId, string $amount): string
    {
        $orderId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        DB::table('rt_orders')->insert([
            'id' => $orderId, 'order_no' => 'WTEST-' . Str::random(16),
            'channel' => 'b2b', 'company_id' => $companyId,
            'customer_user_id' => $userId, 'created_by' => $userId,
            'status' => 'draft', 'currency' => 'USD',
        ]);
        DB::table('rt_bookings')->insert([
            'id' => $bookingId, 'order_id' => $orderId,
            'booking_no' => 'WTEST-' . Str::random(16),
            'service_type' => 'hotel', 'channel' => 'b2b',
            'company_id' => $companyId, 'customer_user_id' => $userId,
            'status' => 'draft', 'currency' => 'USD',
            'approved_total' => $amount,
        ]);
        return $bookingId;
    }
}
