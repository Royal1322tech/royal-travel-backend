<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers bookings.manage (Admin booking sandbox actions and support-ticket
 * replies) on existing installations, where RolesAndPermissionsSeeder is not run.
 *
 * Idempotent and additive only: it creates the permission if missing and grants it
 * to the admin-portal super_admin role only. No other role or assignment is
 * modified or removed.
 */
return new class extends Migration {
    public function up(): void
    {
        $existing = DB::table('rt_permissions')->where('code', 'bookings.manage')->value('id');
        if ($existing === null) {
            DB::table('rt_permissions')->insert(['code' => 'bookings.manage', 'description' => 'bookings.manage']);
        }
        $permissionId = DB::table('rt_permissions')->where('code', 'bookings.manage')->value('id');

        $superAdminRoleIds = DB::table('rt_roles')
            ->where('portal', 'admin')->where('code', 'super_admin')->pluck('id');
        foreach ($superAdminRoleIds as $roleId) {
            DB::table('rt_role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: the permission may already be referenced by role grants.
    }
};
