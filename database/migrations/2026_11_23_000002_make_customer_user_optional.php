<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::statement('ALTER TABLE rt_orders ALTER COLUMN customer_user_id DROP NOT NULL');
        DB::statement('ALTER TABLE rt_bookings ALTER COLUMN customer_user_id DROP NOT NULL');
    }
    public function down(): void {
        if (DB::table('rt_orders')->whereNull('customer_user_id')->exists() ||
            DB::table('rt_bookings')->whereNull('customer_user_id')->exists()) {
            throw new RuntimeException('Cannot restore NOT NULL: customer-only records exist.');
        }
        DB::statement('ALTER TABLE rt_orders ALTER COLUMN customer_user_id SET NOT NULL');
        DB::statement('ALTER TABLE rt_bookings ALTER COLUMN customer_user_id SET NOT NULL');
    }
};
