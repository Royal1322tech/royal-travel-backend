<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every Royal Admin sidebar destination, opened by a super admin provisioned only by
 * code (migrations + RolesAndPermissionsSeeder), through a real /admin/login.
 */
final class AdminSidebarAccessTest extends TestCase
{
    use DatabaseTransactions;

    /** [label, route name, permission] exactly as in admin/components/sidebar.blade.php */
    private const SIDEBAR = [
        ['Dashboard', 'admin.dashboard', 'dashboard.view'],
        ['Bookings', 'admin.bookings.index', 'bookings.view'],
        ['Support Tickets', 'admin.support.index', 'bookings.view'],
        ['Hotel Search', 'admin.hotels.index', 'hotels.view'],
        ['Reduce Price Requests', 'admin.price-reduction.index', 'price_requests.view'],
        ['Companies', 'admin.companies.index', 'companies.view'],
        ['Suppliers', 'admin.suppliers.index', 'suppliers.view'],
        ['Company Finance', 'admin.companies.finance.index', 'companies.view'],
        ['Pricing & Commissions', 'admin.pricing.index', 'pricing.royal.manage'],
        ['Exchange Rates', 'admin.exchange-rates.index', 'fx.view'],
        ['Loyalty Points', 'admin.loyalty.index', 'loyalty.view'],
        ['Users Management', 'admin.users.index', 'users.view'],
        ['Roles & Permissions', 'admin.roles.index', 'roles.view'],
    ];

    private const PASSWORD = 'Admin-Password-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('royal_test', DB::connection()->getDatabaseName());
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutVite();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'root-' . Str::lower(Str::random(8)) . '@royal.test',
            'password' => Hash::make(self::PASSWORD), 'must_change_password' => false]);
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'company_id' => null,
            'role_id' => DB::table('rt_roles')->where('code', 'super_admin')->where('portal', 'admin')->value('id')]);

        return $user;
    }

    public function test_sidebar_matches_this_test(): void
    {
        $blade = file_get_contents(resource_path('views/admin/components/sidebar.blade.php'));
        foreach (self::SIDEBAR as [$label, $route, $permission]) {
            $this->assertStringContainsString("'{$route}', '{$permission}'", $blade, $label);
        }
    }

    public function test_super_admin_has_every_sidebar_permission_from_code(): void
    {
        $granted = DB::table('rt_role_permissions as rp')->join('rt_roles as r', 'r.id', '=', 'rp.role_id')
            ->join('rt_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('r.code', 'super_admin')->pluck('p.code')->all();
        foreach (self::SIDEBAR as [$label, , $permission]) {
            $this->assertContains($permission, $granted, "super_admin lacks {$permission} ({$label})");
        }
    }

    public function test_super_admin_logs_in_and_opens_every_sidebar_page(): void
    {
        $admin = $this->superAdmin();
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $dashboard = (string) $this->get(route('admin.dashboard'))->assertOk()->getContent();
        foreach (self::SIDEBAR as [$label, $route]) {
            $this->assertStringContainsString('href="' . route($route) . '"', $dashboard, "Sidebar link missing: {$label}");
            $this->get(route($route))->assertOk();
        }
    }

    public function test_admin_without_a_permission_is_denied_that_page_only(): void
    {
        $user = User::factory()->create();
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $roleId = (string) Str::uuid();
        DB::table('rt_roles')->insert(['id' => $roleId, 'code' => 'sidebar_limited_' . Str::random(8), 'portal' => 'admin']);
        foreach (['dashboard.view', 'bookings.view'] as $code) {
            DB::table('rt_role_permissions')->insert(['role_id' => $roleId, 'permission_id' => DB::table('rt_permissions')->where('code', $code)->value('id')]);
        }
        DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'company_id' => null]);

        $this->withSession(['auth_portal' => 'admin'])->actingAs($user, 'web');
        $this->get(route('admin.bookings.index'))->assertOk();
        $this->get(route('admin.pricing.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $html = (string) $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('href="' . route('admin.pricing.index') . '"', $html, 'Sidebar hides pages without permission.');
    }
}
