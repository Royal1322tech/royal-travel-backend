<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'pricing.royal.manage' => 'Manage Royal direct and B2B wholesale hotel pricing',
            'pricing.company.manage' => 'Manage markup of own B2B company',
        ] as $code => $description) {
            DB::table('rt_permissions')->updateOrInsert(['code' => $code], ['description' => $description]);
        }
        // Intentionally no role grants: assign explicitly after review.
    }

    public function down(): void
    {
        // No destructive rollback; permissions may be referenced by role mappings.
    }
};
