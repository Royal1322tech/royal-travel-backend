<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_approval_activates_company_and_membership(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();

        $this->grantManagePermission($admin);

        $companyId = (string) Str::uuid();

        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'Approval Test Company',
            'status' => 'pending',
        ]);

        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'role_code' => 'company_admin',
            'status' => 'pending',
        ]);

        $this->withSession(['auth_portal' => 'admin'])->actingAs($admin)
            ->patch(route('admin.companies.approve', $companyId))
            ->assertRedirect();

        $this->assertDatabaseHas('rt_companies', [
            'id' => $companyId,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('rt_company_memberships', [
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'status' => 'active',
            'approved_by' => $admin->id,
        ]);

        $this->assertNotNull(
            DB::table('rt_company_memberships')
                ->where('company_id', $companyId)
                ->where('user_id', $owner->id)
                ->value('approved_at')
        );

        $this->withSession(['auth_portal' => 'admin'])->actingAs($admin)
            ->patch(route('admin.companies.approve', $companyId))
            ->assertStatus(409);
    }

    private function grantManagePermission(User $admin): void
    {
        $roleId = (string) Str::uuid();

        $permissionId = DB::table('rt_permissions')
            ->where('code', 'companies.manage')
            ->value('id');

        if (! $permissionId) {
            $permissionId = (string) Str::uuid();

            DB::table('rt_permissions')->insert([
                'id' => $permissionId,
                'code' => 'companies.manage',
            ]);
        }

        DB::table('rt_roles')->insert([
            'id' => $roleId,
            'code' => 'admin_approval_test_' . Str::random(12),
            'portal' => 'admin',
        ]);

        DB::table('rt_role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        DB::table('rt_user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $roleId,
            'company_id' => null,
        ]);
    }
}
