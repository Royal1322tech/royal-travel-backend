<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rt_b2b_finance_entries')) {
            return;
        }

        if (Schema::getColumnType(
            'rt_b2b_finance_entries',
            'actor_id'
        ) !== 'uuid') {
            return;
        }

        // Avoid silently losing historical audit records.
        if (DB::table('rt_b2b_finance_entries')->exists()) {
            throw new RuntimeException(
                'Finance entries exist. Manual data migration required.'
            );
        }

        // Check for foreign keys before changing the column.
        $foreignKeys = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'rt_b2b_finance_entries'::regclass
              AND contype = 'f'
              AND pg_get_constraintdef(oid) LIKE '%actor_id%'
        ");

        if (count($foreignKeys) > 0) {
            throw new RuntimeException(
                'actor_id has a foreign key. Review it before migration.'
            );
        }

        DB::statement('
            ALTER TABLE rt_b2b_finance_entries
            ALTER COLUMN actor_id TYPE BIGINT
            USING NULL::BIGINT
        ');
    }

    public function down(): void
    {
        // Deliberately irreversible: avoid corrupting audit data.
    }
};
