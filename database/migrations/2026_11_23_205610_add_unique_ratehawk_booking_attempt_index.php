<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX rt_ratehawk_one_attempt_per_booking
            ON rt_supplier_booking_attempts (booking_id)
            WHERE operation = 'ratehawk_hotel_booking'
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS rt_ratehawk_one_attempt_per_booking
        ");
    }
};