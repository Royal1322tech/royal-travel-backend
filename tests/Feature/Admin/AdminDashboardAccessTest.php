<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_user_without_admin_permission_is_forbidden(): void
    {
        $this->withSession(['auth_portal' => 'admin'])->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_b2b_role_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $this->assignRoleWithPermission($user, 'b2b', 'dashboard.view');

        $this->withSession(['auth_portal' => 'admin'])->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_without_dashboard_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->assignRoleWithPermission($user, 'admin', 'bookings.view');

        $this->withSession(['auth_portal' => 'admin'])->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_with_dashboard_permission_can_access(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $this->assignRoleWithPermission($user, 'admin', 'dashboard.view');

        $this->withSession(['auth_portal' => 'admin'])->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_b2b_session_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->assignRoleWithPermission($user, 'admin', 'dashboard.view');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'b2b'])
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    private function assignRoleWithPermission(
        User $user,
        string $portal,
        string $permission
    ): void {
        $roleId = (string) Str::uuid();
        $permissionId = DB::table('rt_permissions')
            ->where('code', $permission)
            ->value('id') ?? (string) Str::uuid();

        DB::table('rt_roles')->insert([
            'id' => $roleId,
            'code' => $portal . '_test_' . Str::random(12),
            'portal' => $portal,
        ]);

        DB::table('rt_permissions')->insertOrIgnore([
            'id' => $permissionId,
            'code' => $permission,
        ]);

        DB::table('rt_role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'company_id' => null,
        ]);
    }
}
