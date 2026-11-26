<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grants pricing.company.manage to the B2B company_admin role (approved 2026-11-26),
 * so company owners can manage their own company's markup in the Company Portal.
 *
 * Idempotent and additive only: registers the permission if missing and adds a single
 * role grant. company_employee and every other role or assignment are untouched.
 * Owner-only access and company scoping are still enforced by EnsureCompanyPortalOwner.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('rt_permissions')->insertOrIgnore([
            'code' => 'pricing.company.manage',
            'description' => 'Manage markup of own B2B company',
        ]);
        $permissionId = DB::table('rt_permissions')->where('code', 'pricing.company.manage')->value('id');

        $roleIds = DB::table('rt_roles')->where('portal', 'b2b')->where('code', 'company_admin')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('rt_role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: removing a live grant could lock owners out mid-session.
    }
};
