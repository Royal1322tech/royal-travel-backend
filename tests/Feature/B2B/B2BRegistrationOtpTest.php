<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use App\Modules\Identity\Services\B2BRegistrationOtpService;
use App\Notifications\B2BRegistrationOtpNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BRegistrationOtpTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_otp_verifies_email_once(): void
    {
        Notification::fake();
        $owner = User::factory()->unverified()->create();
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert([
            'id' => $companyId, 'legal_name' => 'Pending OTP Test', 'status' => 'approved',
        ]);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId,
            'user_id' => $owner->id, 'role_code' => 'company_admin', 'status' => 'pending',
        ]);
        $service = app(B2BRegistrationOtpService::class);
        $service->send($owner, $companyId);
        Notification::assertSentTo($owner, B2BRegistrationOtpNotification::class);
        $this->assertFalse($service->verify($owner, $companyId, '000000'));
        $notification = Notification::sent($owner, B2BRegistrationOtpNotification::class)[0];
        $lines = implode(' ', $notification->toMail($owner)->introLines);
        preg_match('/\*\*([0-9]{6})\*\*/', $lines, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertTrue($service->verify($owner, $companyId, $matches[1]));
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertFalse($service->verify($owner, $companyId, $matches[1]));
    }
}
