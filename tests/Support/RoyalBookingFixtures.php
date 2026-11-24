<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-contained booking/wallet fixtures for integration tests.
 *
 * Every fixture is created inside a transaction opened by inRollbackTransaction()
 * and rolled back afterwards, so tests never depend on (or leave behind) data.
 * The guard FAILS (never skips) unless the connection is an isolated PostgreSQL
 * test database, so these tests can never write to staging or production.
 */
trait RoyalBookingFixtures
{
    protected function assertIsolatedTestDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        if (!app()->environment('testing')
            || DB::connection()->getDriverName() !== 'pgsql'
            || preg_match('/(^|_)test$/D', $database) !== 1) {
            $this->fail('Refusing to run: these tests require APP_ENV=testing and an isolated *_test PostgreSQL database.');
        }
    }

    /** Runs $callback inside a transaction that is ALWAYS rolled back. */
    protected function inRollbackTransaction(callable $callback): void
    {
        $this->assertIsolatedTestDatabase();
        DB::beginTransaction();
        try {
            $callback();
        } finally {
            DB::rollBack();
        }
    }

    protected function createTestUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => 'Fixture User',
            'email' => 'fixture-' . Str::lower(Str::random(20)) . '@example.test',
            'password' => bcrypt(Str::random(32)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Approved USD company with a B2B finance account. */
    protected function createCompanyWithWallet(int $walletCents, int $userId): string
    {
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'Fixture Travel Co ' . Str::random(8),
            'status' => 'approved',
            'account_currency' => 'USD',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rt_b2b_finance_accounts')->insert([
            'company_id' => $companyId,
            'currency' => 'USD',
            'wallet_cents' => $walletCents,
            'wallet_reserved_cents' => 0,
            'credit_limit_cents' => 0,
            'credit_used_cents' => 0,
            'credit_frozen' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $companyId;
    }

    /** The active RATEHAWK supplier, created inside the rolled-back transaction if absent. */
    protected function rateHawkSupplierId(): string
    {
        $existing = DB::table('rt_suppliers')->where('code', 'RATEHAWK')->first();
        if ($existing !== null) {
            DB::table('rt_suppliers')->where('id', $existing->id)
                ->update(['adapter_code' => 'RATEHAWK', 'status' => 'active']);
            return (string) $existing->id;
        }
        $id = (string) Str::uuid();
        DB::table('rt_suppliers')->insert([
            'id' => $id, 'code' => 'RATEHAWK', 'name' => 'RateHawk',
            'status' => 'active', 'adapter_code' => 'RATEHAWK',
        ]);
        return $id;
    }

    /**
     * A B2B hotel booking exactly as the price-approval step must leave it:
     * hotel details plus an APPROVED price snapshot whose customer_total equals
     * approved_total (enforced by rt_booking_price_consistency) and whose
     * supplier_cost is the verified supplier deposit.
     */
    protected function createApprovedB2BHotelBooking(
        string $companyId,
        int $userId,
        string $supplierId,
        string $approvedTotal,
        ?string $supplierCost,
    ): string {
        $orderId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        DB::table('rt_orders')->insert([
            'id' => $orderId, 'order_no' => 'FIXT-' . Str::upper(Str::random(16)),
            'channel' => 'b2b', 'company_id' => $companyId,
            'customer_user_id' => $userId, 'created_by' => $userId,
            'status' => 'draft', 'currency' => 'USD',
        ]);
        DB::table('rt_bookings')->insert([
            'id' => $bookingId, 'order_id' => $orderId,
            'booking_no' => 'FIXT-' . Str::upper(Str::random(16)),
            'service_type' => 'hotel', 'channel' => 'b2b',
            'company_id' => $companyId, 'customer_user_id' => $userId,
            'supplier_id' => $supplierId, 'status' => 'draft',
            'currency' => 'USD', 'approved_total' => $approvedTotal,
        ]);
        DB::table('rt_hotel_booking_details')->insert([
            'booking_id' => $bookingId, 'hotel_id' => null,
            'check_in' => now()->addDays(30)->toDateString(),
            'check_out' => now()->addDays(32)->toDateString(),
            'rooms_count' => 1, 'supplier_offer_ref' => 'p-fixture-prebook-hash',
        ]);
        // A null supplier cost models a booking that never received a verified snapshot.
        if ($supplierCost !== null) {
            DB::table('rt_booking_price_snapshots')->insert([
                'id' => (string) Str::uuid(), 'booking_id' => $bookingId,
                'version' => 1, 'currency' => 'USD',
                'supplier_cost' => $supplierCost,
                'discount_amount' => 0, 'fee_amount' => 0, 'tax_amount' => 0,
                'customer_total' => $approvedTotal,
                'approved_by' => $userId, 'approved_at' => now(),
                'created_at' => now(),
            ]);
        }
        return $bookingId;
    }

    /** Asserts the deferred integrity triggers accept the fixture state. */
    protected function assertDeferredConstraintsHold(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->addToAssertionCount(1);
    }
}
