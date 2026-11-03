<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BCompanyPermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_access_is_revoked_when_company_or_membership_becomes_inactive(): void
    {
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();
        $roleId = (string) Str::uuid();

        $permissionId = DB::table('rt_permissions')
            ->where('code', 'hotels.view')
            ->value('id') ?? (string) Str::uuid();

        DB::table('rt_permissions')->insertOrIgnore([
            'id' => $permissionId,
            'code' => 'hotels.view',
        ]);

        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'B2B Permission Security Test',
            'status' => 'approved',
        ]);

        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role_code' => 'company_admin',
            'status' => 'active',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        DB::table('rt_roles')->insert([
            'id' => $roleId,
            'code' => 'b2b_security_' . Str::random(12),
            'portal' => 'b2b',
        ]);

        DB::table('rt_role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'company_id' => $companyId,
        ]);

        $this->withoutVite();

        // Admin sessions cannot access the B2B dashboard.
        $this->actingAs($user)
            ->withSession(['auth_portal' => 'admin', 'active_company_id' => $companyId])
            ->get('/b2b/hotels')
            ->assertForbidden();

        // A valid B2B session can access its approved company.
        $this->withSession(['auth_portal' => 'b2b', 'active_company_id' => $companyId])
            ->get('/b2b/hotels')
            ->assertOk();

        DB::table('rt_companies')
            ->where('id', $companyId)
            ->update(['status' => 'suspended']);

        $this->get('/b2b/hotels')->assertForbidden();

        DB::table('rt_companies')
            ->where('id', $companyId)
            ->update(['status' => 'approved']);

        DB::table('rt_company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->update(['status' => 'inactive']);

        $this->get('/b2b/hotels')->assertForbidden();
    }
}
