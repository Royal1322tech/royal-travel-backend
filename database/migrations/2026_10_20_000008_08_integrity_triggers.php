<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Royal Platform requires PostgreSQL.');
        }
        DB::unprepared(file_get_contents(database_path('schema/royal/08_integrity_triggers.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('Royal schema rollback is intentionally disabled; use a disposable test database.');
    }
};
