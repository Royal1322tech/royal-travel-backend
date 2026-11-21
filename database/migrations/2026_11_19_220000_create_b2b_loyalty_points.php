<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('B2B loyalty requires PostgreSQL.');
        }
        DB::unprepared(<<<'SQL'
CREATE TABLE rt_loyalty_rules (
 id uuid PRIMARY KEY DEFAULT gen_random_uuid(), company_id uuid REFERENCES rt_companies(id) ON DELETE RESTRICT,
 currency char(3) NOT NULL REFERENCES rt_currencies(code),
 points_per_100 numeric(14,4) NOT NULL CHECK(points_per_100 >= 0 AND points_per_100 <= 100000),
 enabled boolean NOT NULL DEFAULT false, effective_from timestamptz NOT NULL DEFAULT now(),
 created_by bigint REFERENCES users(id) ON DELETE RESTRICT, updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX rt_loyalty_rules_scope_unique ON rt_loyalty_rules (COALESCE(company_id::text, 'GLOBAL'), currency);
CREATE TABLE rt_loyalty_entries (
 id uuid PRIMARY KEY DEFAULT gen_random_uuid(), company_id uuid NOT NULL REFERENCES rt_companies(id) ON DELETE RESTRICT,
 booking_id uuid REFERENCES rt_bookings(id) ON DELETE RESTRICT, actor_user_id bigint REFERENCES users(id) ON DELETE RESTRICT,
 entry_type varchar(32) NOT NULL CHECK(entry_type IN ('booking_earn','booking_release','booking_void','manual_credit','manual_debit')),
 points bigint NOT NULL CHECK(points <> 0), balance_bucket varchar(12) NOT NULL CHECK(balance_bucket IN ('pending','available')),
 dedupe_key varchar(180) NOT NULL UNIQUE, note text, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX rt_loyalty_entries_company_idx ON rt_loyalty_entries(company_id, created_at DESC);
CREATE INDEX rt_loyalty_entries_booking_idx ON rt_loyalty_entries(booking_id);
CREATE TABLE rt_loyalty_booking_awards (
 booking_id uuid PRIMARY KEY REFERENCES rt_bookings(id) ON DELETE RESTRICT,
 company_id uuid NOT NULL REFERENCES rt_companies(id) ON DELETE RESTRICT,
 rule_id uuid NOT NULL REFERENCES rt_loyalty_rules(id) ON DELETE RESTRICT,
 currency char(3) NOT NULL, booking_total numeric(20,6) NOT NULL,
 points bigint NOT NULL CHECK(points > 0), state varchar(12) NOT NULL DEFAULT 'pending' CHECK(state IN ('pending','available','void')),
 created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
SQL);
        foreach (['loyalty.view', 'loyalty.manage'] as $code) {
            DB::table('rt_permissions')->updateOrInsert(['code' => $code], ['description' => $code]);
            $role = DB::table('rt_roles')->where('code', 'super_admin')->where('portal', 'admin')->value('id');
            $permission = DB::table('rt_permissions')->where('code', $code)->value('id');
            if ($role) DB::table('rt_role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Loyalty rollback intentionally disabled to protect the points ledger.');
    }
};
