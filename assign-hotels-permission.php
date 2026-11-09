<?php

use Illuminate\Support\Facades\DB;

$permission = DB::table('rt_permissions')
    ->where('code', 'hotels.view')
    ->first();

$roles = DB::table('rt_roles')
    ->where('portal', 'b2b')
    ->whereIn('code', ['company_admin', 'company_employee'])
    ->get();

if (!$permission || $roles->count() !== 2) {
    throw new RuntimeException('Required permission or roles are missing.');
}

DB::transaction(function () use ($permission, $roles) {
    foreach ($roles as $role) {
        $exists = DB::table('rt_role_permissions')
            ->where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->exists();

        if (!$exists) {
            DB::table('rt_role_permissions')->insert([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }
    }
});

echo "B2B hotels.view permission assigned successfully.\n";
