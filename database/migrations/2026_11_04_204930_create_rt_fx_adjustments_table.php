
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'Royal Platform requires PostgreSQL.'
            );
        }

        Schema::create('rt_fx_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary()
                ->default(DB::raw('gen_random_uuid()'));

            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);

            $table->string('adjustment_type', 16)
                ->default('fixed');

            $table->decimal('adjustment_value', 24, 12)
                ->default(0);

            $table->boolean('is_enabled')->default(true);

            $table->timestampsTz();

            $table->foreign('base_currency')
                ->references('code')->on('rt_currencies');

            $table->foreign('quote_currency')
                ->references('code')->on('rt_currencies');

            $table->unique(
                ['base_currency', 'quote_currency'],
                'rt_fx_adjustments_pair_unique'
            );
        });

        DB::statement("
            ALTER TABLE rt_fx_adjustments
            ADD CONSTRAINT rt_fx_adjustments_valid_check
            CHECK (
                base_currency <> quote_currency
                AND adjustment_type = 'fixed'
                AND adjustment_value >= 0
            )
        ");
    }

    public function down(): void
    {
        throw new RuntimeException(
            'FX adjustment rollback requires explicit review.'
        );
    }
};
