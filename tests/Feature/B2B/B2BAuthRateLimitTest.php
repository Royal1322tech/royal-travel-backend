<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use App\Notifications\B2BRegistrationOtpNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Rate limits on the public B2B auth endpoints: separate counters per endpoint, per account and per IP,
 * readable Retry-After, and no change to how credentials and codes are checked.
 */
class B2BAuthRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets its own empty, clock-aware limiter store. CI runs with CACHE_STORE=redis, where
        // counters would carry over between tests and ignore travel(). The RateLimiter singleton is built
        // while the app boots (AppServiceProvider registers the named limiters), so swap its store in place
        // instead of rebinding it, which would drop those limiters. The limits are store-independent.
        $store = $this->app->make('cache')->driver('array');
        (fn () => $this->cache = $store)->call($this->app->make(\Illuminate\Cache\RateLimiter::class));
    }

    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function login(string $ip, string $email = 'owner@example.com'): TestResponse
    {
        return $this->fromIp($ip)->postJson('/api/v1/b2b/auth/login', [
            'email' => $email, 'password' => 'Wrong-Password-1!', 'company_id' => (string) Str::uuid(), 'channel' => 'website',
        ]);
    }

    private function verify(string $ip, string $email = 'owner@example.com', ?string $company = null, string $otp = '000000'): TestResponse
    {
        return $this->fromIp($ip)->postJson('/api/v1/b2b/auth/verify-otp', [
            'email' => $email, 'company_id' => $company ?? '8f14e45f-ceea-467a-9575-3c4b1f0b5c1a', 'otp' => $otp,
        ]);
    }

    private function resend(string $ip, string $email = 'owner@example.com', ?string $company = null): TestResponse
    {
        return $this->fromIp($ip)->postJson('/api/v1/b2b/auth/resend-verification-otp', [
            'email' => $email, 'company_id' => $company ?? '8f14e45f-ceea-467a-9575-3c4b1f0b5c1a',
        ]);
    }

    private function assertThrottled(TestResponse $response, int $maxSeconds): void
    {
        // Same contract as before: 429 with Laravel's standard message (debug builds add exception details).
        $response->assertStatus(429)->assertJsonPath('message', 'Too Many Attempts.');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual($maxSeconds, $retryAfter);
    }

    /** An approved company whose owner is waiting for email verification, as after admin approval. */
    private function pendingOwner(string $email): array
    {
        $owner = User::factory()->unverified()->create(['email' => $email]);
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Rate Limit Test Co', 'status' => 'approved']);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $owner->id,
            'role_code' => 'company_admin', 'status' => 'pending',
        ]);

        return [$owner, $companyId];
    }

    public function test_login_allows_five_attempts_per_account_and_ip_then_429_with_retry_after(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('198.51.100.7')->assertStatus(422);
        }
        $this->assertThrottled($this->login('198.51.100.7'), 60);
    }

    public function test_login_limit_for_one_user_does_not_block_another_user_on_the_same_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('198.51.100.7', 'alice@example.com');
        }
        $this->login('198.51.100.7', 'alice@example.com')->assertStatus(429);
        $this->login('198.51.100.7', 'bob@example.com')->assertStatus(422);
    }

    public function test_login_account_limit_is_per_ip_for_ordinary_mistakes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('198.51.100.7');
        }
        $this->login('198.51.100.7')->assertStatus(429);
        // The same person on another network is not locked out by the first network's mistakes.
        $this->login('203.0.113.9')->assertStatus(422);
    }

    public function test_login_distributed_guessing_against_one_account_is_capped_across_ips(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->login("192.0.2.$i", 'victim@example.com')->assertStatus(422);
        }
        $this->assertThrottled($this->login('192.0.2.200', 'victim@example.com'), 60);
    }

    public function test_login_email_normalisation_cannot_be_used_to_bypass_the_account_limit(): void
    {
        foreach (['Owner@Example.com', ' owner@example.com', 'OWNER@EXAMPLE.COM ', 'owner@example.com', 'owner@EXAMPLE.com'] as $email) {
            $this->login('198.51.100.7', $email)->assertStatus(422);
        }
        $this->login('198.51.100.7', 'owner@example.com')->assertStatus(429);
    }

    public function test_login_rotating_emails_from_one_ip_is_capped_per_ip(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->login('198.51.100.7', "user$i@example.com")->assertStatus(422);
        }
        $this->assertThrottled($this->login('198.51.100.7', 'user21@example.com'), 60);
        // Another network is unaffected.
        $this->login('203.0.113.9', 'user21@example.com')->assertStatus(422);
    }

    public function test_endpoints_have_independent_counters(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->login('198.51.100.7');
        }
        $this->login('198.51.100.7')->assertStatus(429);

        // The exhausted login limit does not reach verification, resend or registration.
        $this->verify('198.51.100.7')->assertStatus(422)->assertJsonValidationErrors('otp');
        $this->resend('198.51.100.7')->assertOk();
        $this->fromIp('198.51.100.7')->postJson('/api/v1/b2b/auth/register', [])->assertStatus(422);
    }

    public function test_verify_allows_five_attempts_per_account_then_429_and_the_code_is_still_checked_until_then(): void
    {
        Notification::fake();
        [$owner, $companyId] = $this->pendingOwner('pending@example.com');
        for ($i = 0; $i < 5; $i++) {
            $this->verify('198.51.100.7', 'pending@example.com', $companyId)->assertStatus(422)->assertJsonValidationErrors('otp');
        }
        $this->assertThrottled($this->verify('198.51.100.7', 'pending@example.com', $companyId), 60);
        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
    }

    public function test_verify_limit_for_one_account_does_not_block_another_account_on_the_same_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->verify('198.51.100.7', 'first@example.com');
        }
        $this->verify('198.51.100.7', 'first@example.com')->assertStatus(429);
        $this->verify('198.51.100.7', 'second@example.com')->assertStatus(422);
    }

    public function test_verify_account_limit_holds_across_ips_against_code_guessing(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->verify("192.0.2.$i", 'target@example.com')->assertStatus(422);
        }
        $this->verify('192.0.2.99', 'target@example.com')->assertStatus(429);
    }

    public function test_verify_rotating_accounts_from_one_ip_is_capped_per_ip(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->verify('198.51.100.7', "owner$i@example.com")->assertStatus(422);
        }
        $this->assertThrottled($this->verify('198.51.100.7', 'owner21@example.com'), 60);
    }

    public function test_a_valid_code_still_activates_the_account_within_the_limit(): void
    {
        Notification::fake();
        [$owner, $companyId] = $this->pendingOwner('activate@example.com');
        $this->fromIp('198.51.100.7')->postJson('/api/v1/b2b/auth/resend-verification-otp', ['email' => 'activate@example.com', 'company_id' => $companyId])->assertOk();
        Notification::assertSentTo($owner, B2BRegistrationOtpNotification::class, function ($notification) use ($owner, &$code) {
            preg_match('/\*\*([0-9]{6})\*\*/', implode(' ', $notification->toMail($owner)->introLines), $match);
            $code = $match[1] ?? null;

            return $code !== null;
        });
        $this->verify('198.51.100.7', 'activate@example.com', $companyId, $code)
            ->assertOk()->assertJson(['status' => 'active']);
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
    }

    public function test_resend_allows_three_requests_per_account_per_five_minutes_with_the_same_reply_for_unknown_accounts(): void
    {
        Notification::fake();
        [, $companyId] = $this->pendingOwner('real@example.com');
        foreach ([['real@example.com', $companyId], ['nobody@example.com', null]] as [$email, $company]) {
            for ($i = 0; $i < 3; $i++) {
                $this->resend('198.51.100.7', $email, $company)->assertOk();
            }
            // Existing and unknown accounts are throttled identically: the limit reveals nothing.
            $this->assertThrottled($this->resend('198.51.100.7', $email, $company), 300);
        }
    }

    public function test_resend_rotating_accounts_from_one_ip_is_capped_per_hour(): void
    {
        Notification::fake();
        for ($i = 1; $i <= 10; $i++) {
            $this->resend('198.51.100.7', "flood$i@example.com")->assertOk();
        }
        $this->assertThrottled($this->resend('198.51.100.7', 'flood11@example.com'), 3600);
    }

    public function test_register_allows_ten_per_hour_per_ip_and_other_ips_are_unaffected(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->fromIp('198.51.100.7')->postJson('/api/v1/b2b/auth/register', [])->assertStatus(422);
        }
        $this->assertThrottled($this->fromIp('198.51.100.7')->postJson('/api/v1/b2b/auth/register', []), 3600);
        $this->fromIp('203.0.113.9')->postJson('/api/v1/b2b/auth/register', [])->assertStatus(422);
    }

    public function test_limits_reset_after_their_window(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('198.51.100.7');
        }
        $this->login('198.51.100.7')->assertStatus(429);
        $this->travel(61)->seconds();
        $this->login('198.51.100.7')->assertStatus(422);
    }

    public function test_missing_or_malformed_identifiers_are_validated_not_crashed_and_still_limited(): void
    {
        // Identifiers that aren't strings (missing, arrays, numbers, null) share one bucket per IP and
        // company: five are validated, the sixth is throttled. None of them is a server error.
        $unusable = [
            [],
            ['email' => ['owner@example.com'], 'company_id' => ['x'], 'otp' => '000000'],
            ['email' => 12345, 'otp' => '000000'],
            ['email' => null, 'otp' => '000000'],
            ['email' => ['a' => 'b'], 'otp' => ['1']],
        ];
        foreach ($unusable as $body) {
            $this->fromIp('198.51.100.7')->postJson('/api/v1/b2b/auth/verify-otp', $body)->assertStatus(422);
        }
        $this->assertThrottled($this->fromIp('198.51.100.7')->postJson('/api/v1/b2b/auth/verify-otp', []), 60);

        // Malformed strings get their own buckets and are validated, never a server error.
        foreach (['not-an-email', str_repeat('a', 5000).'@example.com', "owner@example.com\0", '   '] as $email) {
            $this->fromIp('203.0.113.9')->postJson('/api/v1/b2b/auth/verify-otp', ['email' => $email, 'company_id' => 'not-a-uuid', 'otp' => 'abc'])->assertStatus(422);
            $this->fromIp('203.0.113.9')->postJson('/api/v1/b2b/auth/login', ['email' => $email])->assertStatus(422);
        }
    }

    public function test_clients_behind_the_trusted_proxy_are_limited_independently_and_spoofing_is_ignored(): void
    {
        // 172.16.1.0/24 is the default trusted proxy range (bootstrap/app.php).
        $viaProxy = fn (string $client) => $this->withServerVariables(['REMOTE_ADDR' => '172.16.1.5'])->withHeaders(['X-Forwarded-For' => $client]);
        for ($i = 1; $i <= 20; $i++) {
            $viaProxy('198.51.100.7')->postJson('/api/v1/b2b/auth/login', ['email' => "a$i@example.com", 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website'])->assertStatus(422);
        }
        $viaProxy('198.51.100.7')->postJson('/api/v1/b2b/auth/login', ['email' => 'a21@example.com', 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website'])->assertStatus(429);
        // A different client behind the same proxy has its own counter.
        $viaProxy('203.0.113.9')->postJson('/api/v1/b2b/auth/login', ['email' => 'a21@example.com', 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website'])->assertStatus(422);
        // An untrusted sender can't escape its own counter by claiming another IP: 198.51.100.7, sending
        // directly and claiming to be 203.0.113.9, is still counted as itself and stays throttled.
        $this->flushHeaders();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->postJson('/api/v1/b2b/auth/login', ['email' => 'a22@example.com', 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website'])
            ->assertStatus(429);
        $this->flushHeaders();
        for ($i = 23; $i <= 42; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])->withHeaders(['X-Forwarded-For' => "203.0.113.$i"])
                ->postJson('/api/v1/b2b/auth/login', ['email' => "s$i@example.com", 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])->withHeaders(['X-Forwarded-For' => '203.0.113.250'])
            ->postJson('/api/v1/b2b/auth/login', ['email' => 's99@example.com', 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website'])
            ->assertStatus(429);
    }

    public function test_cors_exposes_retry_after_only_to_the_allowed_origin(): void
    {
        $origin = config('cors.allowed_origins')[0];
        $this->assertNotSame('*', $origin, 'CORS must stay restricted to the frontend origin');
        for ($i = 0; $i < 5; $i++) {
            $this->login('198.51.100.7');
        }
        $throttled = $this->fromIp('198.51.100.7')->withHeaders(['Origin' => $origin])->postJson('/api/v1/b2b/auth/login', [
            'email' => 'owner@example.com', 'password' => 'x', 'company_id' => (string) Str::uuid(), 'channel' => 'website',
        ]);
        $throttled->assertStatus(429)
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
        $this->assertNotNull($throttled->headers->get('Retry-After'));
        $this->assertNull($throttled->headers->get('Access-Control-Allow-Credentials'));

        $this->flushHeaders();
        $preflight = $this->withHeaders(['Origin' => $origin, 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'content-type,x-locale'])
            ->options('/api/v1/b2b/auth/verify-otp');
        $preflight->assertNoContent()->assertHeader('Access-Control-Allow-Origin', $origin);

        $this->flushHeaders();
        $other = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])->options('/api/v1/b2b/auth/verify-otp');
        // With a single allowed origin the CORS package always names that origin (unchanged behaviour);
        // a foreign site is never echoed back or allowed by wildcard, so browsers block it.
        $this->assertNotSame('https://evil.example', $other->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $other->headers->get('Access-Control-Allow-Origin'));
    }
}
