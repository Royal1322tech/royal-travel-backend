<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BProfileAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_access_b2b_profile(): void
    {
        $this->getJson('/api/v1/b2b/me')
            ->assertUnauthorized();
    }

    public function test_unapproved_membership_cannot_access_b2b_profile(): void
    {
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();

        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'Unapproved Membership Test',
            'status' => 'approved',
        ]);

        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role_code' => 'company_admin',
            'status' => 'active',
            'approved_at' => null,
        ]);

        $token = $user->createToken('b2b-test', [
            'b2b:access',
            'company:' . $companyId,
        ])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/b2b/me')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Company access is not available.'
            );
    }

    public function test_token_cannot_access_another_company(): void
    {
        $user = User::factory()->create();
        $companyA = (string) Str::uuid();
        $companyB = (string) Str::uuid();

        foreach ([$companyA, $companyB] as $companyId) {
            DB::table('rt_companies')->insert([
                'id' => $companyId,
                'legal_name' => 'Tenant Isolation Test',
                'status' => 'approved',
            ]);
        }

        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyA,
            'user_id' => $user->id,
            'role_code' => 'company_admin',
            'status' => 'active',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $token = $user->createToken('wrong-company-test', [
            'b2b:access',
            'company:' . $companyB,
        ])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/b2b/me')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Company access is not available.'
            );
    }


    public function test_token_without_b2b_permission_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('missing-permission-test', [
            'company:' . (string) Str::uuid(),
        ])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/b2b/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_token_with_multiple_company_scopes_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('multiple-companies-test', [
            'b2b:access',
            'company:' . (string) Str::uuid(),
            'company:' . (string) Str::uuid(),
        ])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/b2b/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid company scope.');
    }


    public function test_approved_member_can_access_own_company_profile(): void
    {
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();

        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'Authorized Company Test',
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

        $token = $user->createToken('approved-company-test', [
            'b2b:access',
            'company:' . $companyId,
        ])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/b2b/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.company.id', $companyId)
            ->assertJsonPath('data.company.legal_name', 'Authorized Company Test')
            ->assertJsonPath('data.membership.status', 'active');
    }

}
