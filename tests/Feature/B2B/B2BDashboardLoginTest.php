<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BDashboardLoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_login_uses_password_without_otp_after_approval(): void
    {
        $this->withoutVite();
        $user = User::factory()->create([
            'password' => Hash::make('TestPassword123!'),
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert([
            'id' => $companyId, 'legal_name' => 'Dashboard Login Test', 'status' => 'approved',
        ]);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId,
            'user_id' => $user->id, 'role_code' => 'company_admin',
            'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $this->post(route('b2b.login.submit'), [
            'email' => $user->email, 'password' => 'TestPassword123!',
            'company_id' => $companyId,
        ])->assertRedirect(route('b2b.hotels.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($companyId, session('active_company_id'));
        $this->assertSame(0, DB::table('b2b_login_challenges')
            ->where('user_id', $user->id)->count());
    }

    public function test_unverified_email_cannot_login(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => Hash::make('TestPassword123!'), 'must_change_password' => false,
        ]);
        $this->post(route('b2b.login.submit'), [
            'email' => $user->email, 'password' => 'TestPassword123!',
            'company_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
