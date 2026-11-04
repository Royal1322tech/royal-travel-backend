<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BPasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    private const NEW_PASSWORD = 'NewSecurePassword123!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function createB2BUser(bool $approved = true): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldSecurePassword123!'),
            'must_change_password' => true,
        ]);

        $companyId = (string) Str::uuid();

        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'Password Reset Test',
            'status' => $approved ? 'approved' : 'pending',
        ]);

        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role_code' => 'company_admin',
            'status' => $approved ? 'active' : 'pending',
            'approved_by' => $approved ? $user->id : null,
            'approved_at' => $approved ? now() : null,
        ]);

        return $user;
    }

    private function resetPassword(User $user, string $token): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('b2b.password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);
    }

    public function test_valid_token_resets_password(): void
    {
        $user = $this->createB2BUser();
        $token = Password::broker('b2b_users')->createToken($user);

        $this->resetPassword($user, $token)
            ->assertRedirect(route('b2b.login'));

        $user->refresh();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_token_cannot_be_reused(): void
    {
        $user = $this->createB2BUser();
        $token = Password::broker('b2b_users')->createToken($user);

        $this->resetPassword($user, $token)
            ->assertRedirect(route('b2b.login'));

        $this->resetPassword($user, $token)
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = $this->createB2BUser();

        $this->resetPassword($user, 'invalid-token')
            ->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check('OldSecurePassword123!', $user->fresh()->password)
        );
    }

    public function test_unapproved_membership_cannot_reset_password(): void
    {
        $user = $this->createB2BUser(false);
        $token = Password::broker('b2b_users')->createToken($user);

        $this->resetPassword($user, $token)
            ->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check('OldSecurePassword123!', $user->fresh()->password)
        );
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->createB2BUser();
        $token = Password::broker('b2b_users')->createToken($user);

        DB::table('b2b_password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(31)]);

        $this->resetPassword($user, $token)
            ->assertSessionHasErrors('email');

        $this->assertTrue(
            Hash::check('OldSecurePassword123!', $user->fresh()->password)
        );
    }
}
