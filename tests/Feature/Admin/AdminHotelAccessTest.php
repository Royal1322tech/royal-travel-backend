<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminHotelAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_from_hotel_pages(): void
    {
        $this->get('/admin/hotels')
            ->assertRedirect(route('admin.login'));

        $this->get('/admin/hotels/rooms/' . Str::random(48))
            ->assertRedirect(route('admin.login'));
    }

    public function test_user_without_permission_cannot_access_hotels(): void
    {
        $this->withSession(['auth_portal' => 'admin'])
            ->actingAs(User::factory()->create())
            ->get('/admin/hotels')
            ->assertForbidden();
    }

    public function test_dashboard_permission_does_not_grant_hotel_access(): void
    {
        $user = User::factory()->create();

        $this->assignPermission($user, 'admin', 'dashboard.view');

        $this->withSession(['auth_portal' => 'admin'])
            ->actingAs($user)
            ->get('/admin/hotels')
            ->assertForbidden();
    }

    public function test_b2b_permission_does_not_grant_admin_hotel_access(): void
    {
        $user = User::factory()->create();

        $this->assignPermission($user, 'b2b', 'hotels.view');

        $this->withSession(['auth_portal' => 'admin'])
            ->actingAs($user)
            ->get('/admin/hotels')
            ->assertForbidden();
    }

    public function test_b2b_session_cannot_use_admin_hotel_permission(): void
    {
        $user = User::factory()->create();

        $this->assignPermission($user, 'admin', 'hotels.view');

        $this->withSession(['auth_portal' => 'b2b'])
            ->actingAs($user)
            ->get('/admin/hotels')
            ->assertForbidden();
    }

    public function test_admin_with_hotel_permission_can_open_search_page(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $this->assignPermission($user, 'admin', 'hotels.view');

        $this->withSession(['auth_portal' => 'admin'])
            ->actingAs($user)
            ->get('/admin/hotels')
            ->assertOk();
    }

    public function test_user_without_hotel_permission_cannot_access_rooms(): void
    {
        $user = User::factory()->create();

        $this->assignPermission($user, 'admin', 'dashboard.view');

        $this->withSession(['auth_portal' => 'admin'])
            ->actingAs($user)
            ->get('/admin/hotels/rooms/' . Str::random(48))
            ->assertForbidden();
    }

    private function assignPermission(
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
            'code' => $portal . '_hotel_test_' . Str::random(12),
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
