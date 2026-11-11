<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'fx.view' => 'View reference exchange rates and FX adjustments',
            'fx.manage' => 'Manage USD to EGP/SAR exchange-rate adjustments',
        ] as $code => $description) {
            DB::table('rt_permissions')->updateOrInsert(['code' => $code], ['description' => $description]);
        }
        // No role grants. Assign only after explicit authorization.
    }

    public function down(): void
    {
        // No destructive rollback: permissions may be referenced by role mappings.
    }
};
