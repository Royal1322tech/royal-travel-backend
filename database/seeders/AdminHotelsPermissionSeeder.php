<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminHotelsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {

            /*
            |--------------------------------------------------------------------------
            | 1. Find Admin Role
            |--------------------------------------------------------------------------
            */

            $adminRole = DB::table('rt_roles')
                ->where('code', 'super_admin')
                ->where('portal', 'admin')
                ->first();

            if (! $adminRole) {
                throw new RuntimeException(
                    'Admin super_admin role was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Create Hotels View Permission
            |--------------------------------------------------------------------------
            */

            $permission = DB::table('rt_permissions')
                ->where('code', 'hotels.view')
                ->first();

            if (! $permission) {

                $permissionId = (string) Str::uuid();

                DB::table('rt_permissions')->insert([
                    'id' => $permissionId,
                    'code' => 'hotels.view',
                    'description' => 'View hotel search and results',
                ]);

            } else {

                $permissionId = $permission->id;

            }

            /*
            |--------------------------------------------------------------------------
            | 3. Assign Permission to Super Admin
            |--------------------------------------------------------------------------
            */

            $alreadyAssigned = DB::table('rt_role_permissions')
                ->where('role_id', $adminRole->id)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $alreadyAssigned) {

                DB::table('rt_role_permissions')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permissionId,
                ]);

            }

            $this->command?->info(
                'hotels.view permission assigned to admin super_admin.'
            );
        });
    }
}
