<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Several Royal Admin sidebar permissions were registered without role grants, so a
 * super_admin provisioned only by code (fresh install, CI) could not open Pricing &
 * Commissions, Exchange Rates, Users Management or Roles & Permissions.
 *
 * Grants the admin-portal super_admin role exactly the set already authorized on
 * staging. roles.manage is deliberately NOT granted (not granted on staging either).
 * Idempotent and additive; no other role or assignment is touched.
 */
return new class extends Migration {
    private const GRANTS = [
        'pricing.royal.manage' => 'Manage Royal direct and B2B wholesale hotel pricing',
        'fx.view' => 'View reference exchange rates and FX adjustments',
        'fx.manage' => 'Manage USD to EGP/SAR exchange-rate adjustments',
        'users.view' => 'View admin users',
        'users.manage' => 'Manage admin users',
        'roles.view' => 'View admin roles',
    ];

    public function up(): void
    {
        $roleIds = DB::table('rt_roles')->where('portal', 'admin')->where('code', 'super_admin')->pluck('id');
        foreach (self::GRANTS as $code => $description) {
            DB::table('rt_permissions')->insertOrIgnore(['code' => $code, 'description' => $description]);
            $permissionId = DB::table('rt_permissions')->where('code', $code)->value('id');
            foreach ($roleIds as $roleId) {
                DB::table('rt_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
