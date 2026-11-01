<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('PostgreSQL required.');
        }
        $column = DB::selectOne("SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'rt_companies' AND column_name = 'created_by'");
        if (! $column) {
            throw new RuntimeException('rt_companies.created_by is missing; review prerequisite migrations.');
        }
        if ($column->data_type === 'uuid') {
            if (DB::table('rt_companies')->whereNotNull('created_by')->exists()) {
                throw new RuntimeException('Existing created_by UUID values require manual review.');
            }
            DB::statement('ALTER TABLE rt_companies ALTER COLUMN created_by TYPE bigint USING NULL::bigint');
        } elseif ($column->data_type !== 'bigint') {
            throw new RuntimeException('Unexpected created_by column type: '.$column->data_type);
        }
        DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'rt_companies_created_by_fk' AND conrelid = 'public.rt_companies'::regclass) THEN ALTER TABLE public.rt_companies ADD CONSTRAINT rt_companies_created_by_fk FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE RESTRICT; END IF; END $$;");
    }

    public function down(): void
    {
        throw new RuntimeException('Do not reverse creator ID migration on existing data.');
    }
};
