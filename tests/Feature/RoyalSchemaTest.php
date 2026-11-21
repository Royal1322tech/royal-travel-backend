<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoyalSchemaTest extends TestCase
{
    public function test_royal_database_contains_required_tables(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());

        $result = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_type = 'BASE TABLE'
              AND table_name LIKE 'rt_%'
        ");

        $this->assertGreaterThanOrEqual(58, (int) $result->total);
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('rt_company_contacts')
        );
    }

    public function test_core_booking_tables_exist(): void
    {
        $tables = [
            'rt_orders',
            'rt_bookings',
            'rt_payment_intents',
            'rt_payment_allocations',
            'rt_refunds',
        ];

        foreach ($tables as $table) {
            $exists = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1
                    FROM information_schema.tables
                    WHERE table_schema = 'public'
                      AND table_name = ?
                ) AS found
            ", [$table]);

            $this->assertTrue(
                (bool) $exists->found,
                "Missing database table: {$table}"
            );
        }
    }

    public function test_bookings_have_order_foreign_key(): void
    {
        $result = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.constraint_schema = kcu.constraint_schema
            WHERE tc.table_schema = 'public'
              AND tc.table_name = 'rt_bookings'
              AND tc.constraint_type = 'FOREIGN KEY'
              AND kcu.column_name = 'order_id'
        ");

        $this->assertGreaterThan(0, (int) $result->total);
    }
}