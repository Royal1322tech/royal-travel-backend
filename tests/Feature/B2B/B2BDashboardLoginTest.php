<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use App\Notifications\B2BLoginOtpNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BDashboardLoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_login_requires_valid_email_otp(): void
    {
        $this->withoutVite();
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('TestPassword123!'),
            'must_change_password' => false,
        ]);

        $companyId = (string) Str::uuid();

        DB::table('rt_companies')->insert([
            'id' => $companyId,
            'legal_name' => 'Dashboard OTP Test',
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

        $this->post(route('b2b.login.submit'), [
            'email' => $user->email,
            'password' => 'TestPassword123!',
            'company_id' => $companyId,
        ])->assertRedirect(route('b2b.otp.show'));

        $this->assertGuest();
        Notification::assertSentTo($user, B2BLoginOtpNotification::class);

        $challenge = DB::table('b2b_login_challenges')
            ->where('user_id', $user->id)
            ->where('channel', 'dashboard')
            ->first();

        $this->assertNotNull($challenge);

        $this->post(route('b2b.otp.verify'), [
            'otp' => '000000',
        ])->assertSessionHasErrors('otp');

        $this->assertGuest();

        $notification = Notification::sent(
            $user,
            B2BLoginOtpNotification::class
        )->first();

        $this->assertNotNull($notification);

        $mail = $notification->toMail($user);
        $otp = null;

        foreach ($mail->introLines as $line) {
            if (preg_match('/\*\*([0-9]{6})\*\*/', $line, $matches)) {
                $otp = $matches[1];
                break;
            }
        }

        $this->assertNotNull(
            $otp,
            'OTP notification must expose its code for this test.'
        );

        $this->post(route('b2b.otp.verify'), [
            'otp' => (string) $otp,
        ])->assertRedirect(route('b2b.hotels.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('b2b', session('auth_portal'));
        $this->assertSame($companyId, session('active_company_id'));

        $this->assertNotNull(
            DB::table('b2b_login_challenges')
                ->where('id', $challenge->id)
                ->value('consumed_at')
        );
    }
}
