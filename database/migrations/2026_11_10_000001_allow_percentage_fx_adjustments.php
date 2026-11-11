<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('PostgreSQL required.');
        }
        DB::statement('ALTER TABLE rt_fx_adjustments DROP CONSTRAINT IF EXISTS rt_fx_adjustments_valid_check');
        DB::statement("ALTER TABLE rt_fx_adjustments ADD CONSTRAINT rt_fx_adjustments_valid_check CHECK (base_currency <> quote_currency AND adjustment_type IN ('fixed','percentage') AND adjustment_value >= 0 AND (adjustment_type <> 'percentage' OR adjustment_value <= 100))");
    }
    public function down(): void {
        throw new RuntimeException('Rollback requires explicit review of percentage rows.');
    }
};
