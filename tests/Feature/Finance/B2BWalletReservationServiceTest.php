<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\B2BFinanceService;
use App\Services\Finance\B2BWalletReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\RoyalBookingFixtures;
use Tests\TestCase;

/**
 * Isolated integration test: builds its own company, wallet, user and bookings
 * inside a transaction that is always rolled back. Refuses to run unless the
 * connection is an isolated *_test PostgreSQL database.
 */
final class B2BWalletReservationServiceTest extends TestCase
{
    use RoyalBookingFixtures;

    public function test_wallet_reserve_release_capture_and_idempotency(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $companyId = $this->createCompanyWithWallet(50000, $userId);
            $supplierId = $this->rateHawkSupplierId();

            $service = app(B2BWalletReservationService::class);
            $finance = app(B2BFinanceService::class);
            $before = $finance->snapshot($companyId);

            $releaseBooking = $this->createApprovedB2BHotelBooking($companyId, $userId, $supplierId, '50.000000', '42.00');
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

            $captureBooking = $this->createApprovedB2BHotelBooking($companyId, $userId, $supplierId, '50.000000', '42.00');
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
        });
    }

    public function test_reserve_rejects_insufficient_wallet_without_side_effects(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $companyId = $this->createCompanyWithWallet(4999, $userId);
            $bookingId = $this->createApprovedB2BHotelBooking(
                $companyId, $userId, $this->rateHawkSupplierId(), '50.000000', '42.00'
            );
            try {
                app(B2BWalletReservationService::class)->reserve($bookingId);
                $this->fail('Expected reserve() to reject an insufficient wallet.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('wallet', $e->errors());
            }
            $account = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
            $this->assertSame(4999, (int) $account->wallet_cents);
            $this->assertSame(0, (int) $account->wallet_reserved_cents);
            $this->assertSame(0, DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->count());
        });
    }

    public function test_capture_requires_confirmed_booking(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $companyId = $this->createCompanyWithWallet(50000, $userId);
            $bookingId = $this->createApprovedB2BHotelBooking(
                $companyId, $userId, $this->rateHawkSupplierId(), '50.000000', '42.00'
            );
            $service = app(B2BWalletReservationService::class);
            $service->reserve($bookingId);
            try {
                $service->capture($bookingId);
                $this->fail('Expected capture() to refuse an unconfirmed booking.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('booking', $e->errors());
            }
            $account = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
            $this->assertSame(50000, (int) $account->wallet_cents);
            $this->assertSame(5000, (int) $account->wallet_reserved_cents);
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
        });
    }
}
