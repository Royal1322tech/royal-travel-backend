<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rt_b2b_finance_accounts', function (Blueprint $table) {
            $table->bigInteger('wallet_reserved_cents')
                ->default(0);
        });

        DB::statement(
            'ALTER TABLE rt_b2b_finance_accounts
             ADD CONSTRAINT rt_b2b_wallet_reserved_nonnegative
             CHECK (
                wallet_reserved_cents >= 0
                AND wallet_reserved_cents <= wallet_cents
             )'
        );

        DB::statement(
            "CREATE UNIQUE INDEX rt_fin_res_one_held_wallet_per_booking
             ON rt_financial_reservations (booking_id)
             WHERE state = 'held'
               AND balance_account_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS rt_fin_res_one_held_wallet_per_booking'
        );

        DB::statement(
            'ALTER TABLE rt_b2b_finance_accounts
             DROP CONSTRAINT IF EXISTS rt_b2b_wallet_reserved_nonnegative'
        );

        Schema::table('rt_b2b_finance_accounts', function (Blueprint $table) {
            $table->dropColumn('wallet_reserved_cents');
        });
    }
};
