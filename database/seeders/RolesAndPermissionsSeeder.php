<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'companies.view',
            'companies.manage',
            'users.view',
            'users.manage',
            'hotels.view',
            'bookings.view',
            'price_requests.view',
            'price_requests.manage',
            'bookings.create',
            'bookings.manage',
            'bookings.cancel',
            'payments.view',
            'payments.manage',
            'suppliers.view',
            'suppliers.manage',
            'reports.view',
            'loyalty.view',
            'loyalty.manage',
        ];

        $roles = [
            'super_admin' => [
                'portal' => 'admin',
                'permissions' => $permissions,
            ],
            'company_admin' => [
                'portal' => 'b2b',
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'users.manage',
                    'hotels.view',
                    'bookings.view',
                    'bookings.create',
                    'bookings.cancel',
                    'payments.view',
                    'reports.view',
                ],
            ],
            'company_employee' => [
                'portal' => 'b2b',
                'permissions' => [
                    'dashboard.view',
                    'hotels.view',
                    'bookings.view',
                    'bookings.create',
                ],
            ],
        ];

        DB::transaction(function () use ($permissions, $roles) {
            foreach ($permissions as $code) {
                DB::table('rt_permissions')->updateOrInsert(
                    ['code' => $code],
                    ['description' => $code]
                );
            }

            foreach ($roles as $code => $data) {
               $existing = DB::table('rt_roles')
    ->where('code', $code)
    ->first();

if ($existing && $existing->portal !== $data['portal']) {
    throw new \RuntimeException(
        "Role {$code} already exists in another portal."
    );
}

                if ($existing) {
                    $roleId = $existing->id;
                } else {
                    $roleId = (string) Str::uuid();

                    DB::table('rt_roles')->insert([
                        'id' => $roleId,
                        'code' => $code,
                        'portal' => $data['portal'],
                        'description' => $code,
                        'created_at' => now(),
                    ]);
                }

                foreach ($data['permissions'] as $permissionCode) {
                    $permissionId = DB::table('rt_permissions')
                        ->where('code', $permissionCode)
                        ->value('id');

                    DB::table('rt_role_permissions')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ], []);
                }
            }
        });
    }
}