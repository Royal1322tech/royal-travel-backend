<?php

declare(strict_types=1);

namespace Tests\Feature\CompanyPortal;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\CompanyPortalFixtures;
use Tests\TestCase;

/** Employee creation from the Company Portal and its compatibility with the B2B API login. */
final class CompanyPortalEmployeeTest extends TestCase
{
    use DatabaseTransactions;
    use CompanyPortalFixtures;

    private const EMPLOYEE_PASSWORD = 'Employee-Pass-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
    }

    private function body(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Sara Agent', 'email' => 'Sara.Agent@Example.test',
            'password' => self::EMPLOYEE_PASSWORD, 'password_confirmation' => self::EMPLOYEE_PASSWORD,
        ], $overrides);
    }

    private function create(array $overrides = [])
    {
        return $this->from(route('company-portal.employees.index'))->post(route('company-portal.employees.store'), $this->body($overrides));
    }

    private function apiLogin(string $email, string $companyId, string $channel)
    {
        return $this->postJson('/api/v1/b2b/auth/login', [
            'email' => $email, 'password' => self::EMPLOYEE_PASSWORD, 'company_id' => $companyId, 'channel' => $channel,
        ]);
    }

    public function test_owner_creates_employee_with_hashed_password_active_membership_and_employee_role(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        $this->loginOwner($owner);

        $response = $this->create()->assertRedirect(route('company-portal.employees.index'))->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Employee created. They cannot sign in until their email address is verified.');

        $user = User::query()->where('email', 'sara.agent@example.test')->firstOrFail();
        $this->assertNotSame(self::EMPLOYEE_PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::EMPLOYEE_PASSWORD, $user->password));
        $this->assertNull($user->email_verified_at);
        $membership = DB::table('rt_company_memberships')->where('user_id', $user->id)->first();
        $this->assertSame([$company, 'company_employee', 'active', $owner->id],
            [(string) $membership->company_id, $membership->role_code, $membership->status, (int) $membership->approved_by]);
        $this->assertNotNull($membership->approved_at);
        $this->assertSame(['company_employee'], DB::table('rt_user_roles as ur')->join('rt_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $user->id)->where('ur.company_id', $company)->pluck('r.code')->all());

        $page = $this->get(route('company-portal.employees.index'))->assertOk()
            ->assertSee('sara.agent@example.test')->assertSee('Email not verified, cannot sign in yet');
        $this->assertStringNotContainsString(self::EMPLOYEE_PASSWORD, (string) $page->getContent());
        $this->assertStringNotContainsString(self::EMPLOYEE_PASSWORD, (string) $response->getContent());
    }

    /**
     * Business decision boundary: the existing B2B login requires a verified email and no
     * approved employee activation flow exists, so a portal-created employee cannot log in.
     * Once the email is verified (by whatever activation policy is approved), both channels
     * work with only the employee permissions.
     */
    public function test_new_employee_cannot_log_in_until_verified_then_has_only_employee_access(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        $this->loginOwner($owner);
        $this->create()->assertSessionHasNoErrors();
        $this->post(route('company-portal.logout'));
        $this->app['auth']->forgetGuards();

        foreach (['website', 'mobile'] as $channel) {
            $this->apiLogin('sara.agent@example.test', $company, $channel)->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        // Simulate a completed (future) activation: verified email only.
        User::query()->where('email', 'sara.agent@example.test')->update(['email_verified_at' => now()]);
        $tokens = [];
        foreach (['website', 'mobile'] as $channel) {
            $tokens[$channel] = $this->apiLogin('sara.agent@example.test', $company, $channel)->assertOk()->json('access_token');
        }

        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['mobile'])->getJson('/api/v1/b2b/bookings')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['website'])->getJson('/api/v1/b2b/finance')->assertForbidden();

        // Another company's booking is invisible to the employee.
        $other = $this->portalCompany();
        $foreignBooking = $this->booking($other, $this->owner($other)->id, null);
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['website'])->getJson("/api/v1/b2b/bookings/{$foreignBooking}")->assertNotFound();

        // Not an owner: no Company Portal; no Royal Admin.
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $this->from(route('company-portal.login'))->post(route('company-portal.login.submit'), [
            'email' => 'sara.agent@example.test', 'password' => self::EMPLOYEE_PASSWORD,
        ])->assertSessionHasErrors('email');
        $employee = User::query()->where('email', 'sara.agent@example.test')->firstOrFail();
        $this->withSession(['auth_portal' => 'b2b', 'active_company_id' => $company])->actingAs($employee, 'web')
            ->get(route('company-portal.dashboard'))->assertForbidden();
        $this->withSession(['auth_portal' => 'b2b'])->actingAs($employee, 'web')->get('/admin/dashboard')->assertForbidden();
    }

    public function test_validation_and_normalization(): void
    {
        $company = $this->portalCompany();
        $this->loginOwner($this->owner($company));
        $this->portalUser('taken@example.test');

        $this->create(['email' => 'TAKEN@example.test'])->assertSessionHasErrors('email');
        $this->create(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->create(['name' => ''])->assertSessionHasErrors('name');
        $this->create(['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        Cache::flush();
        $this->create(['password_confirmation' => 'Something-Else-2026!'])->assertSessionHasErrors('password');
        $this->assertSame(0, User::query()->where('email', 'sara.agent@example.test')->count());

        // Old input is kept for name/email but passwords are never repopulated.
        $this->create(['password_confirmation' => 'mismatch-value-123']);
        $page = (string) $this->get(route('company-portal.employees.index'))->getContent();
        $this->assertStringContainsString('value="sara.agent@example.test"', $page);
        $this->assertStringNotContainsString(self::EMPLOYEE_PASSWORD, $page);
    }

    public function test_privilege_and_company_injection_is_ignored(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        $foreign = $this->portalCompany();
        $this->loginOwner($owner);

        $this->create(['company_id' => $foreign, 'role_code' => 'company_admin', 'approved_by' => 1, 'status' => 'pending',
            'email_verified_at' => now()->toDateTimeString()])->assertSessionHasNoErrors();
        $user = User::query()->where('email', 'sara.agent@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $membership = DB::table('rt_company_memberships')->where('user_id', $user->id)->get();
        $this->assertCount(1, $membership);
        $this->assertSame([$company, 'company_employee', 'active', $owner->id],
            [(string) $membership[0]->company_id, $membership[0]->role_code, $membership[0]->status, (int) $membership[0]->approved_by]);
        $this->assertSame(0, DB::table('rt_company_memberships')->where('company_id', $foreign)->where('user_id', $user->id)->count());
    }

    public function test_missing_employee_role_creates_nothing(): void
    {
        $company = $this->portalCompany();
        $this->loginOwner($this->owner($company));
        DB::table('rt_roles')->where('code', 'company_employee')->where('portal', 'b2b')->update(['code' => 'company_employee_disabled']);
        $this->create()->assertStatus(422);
        $this->assertSame(0, User::query()->where('email', 'sara.agent@example.test')->count());
    }

    public function test_failure_during_role_assignment_rolls_back_user_and_membership(): void
    {
        $company = $this->portalCompany();
        $this->loginOwner($this->owner($company));
        $armed = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$armed): void {
            if ($armed && str_starts_with(strtolower($sql), 'insert into "rt_user_roles"')) {
                $armed = false;
                throw new RuntimeException('Simulated role assignment failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->create();
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated role assignment failure', $e->getMessage());
        }
        $this->assertSame(0, User::query()->where('email', 'sara.agent@example.test')->count());
        $this->assertSame(0, DB::table('rt_company_memberships')->where('company_id', $company)->where('role_code', 'company_employee')->count());
    }

    public function test_concurrent_duplicate_email_returns_validation_error_not_500(): void
    {
        $company = $this->portalCompany();
        $this->loginOwner($this->owner($company));
        // Simulate a second request inserting the same email after validation passed.
        $armed = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$armed): void {
            if ($armed && str_starts_with(strtolower($sql), 'insert into "users"')) {
                $armed = false;
                DB::table('users')->insert(['name' => 'Racer', 'email' => 'sara.agent@example.test',
                    'password' => Hash::make(Str::random(20)), 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $this->create()->assertRedirect(route('company-portal.employees.index'))->assertSessionHasErrors('email');
        $this->assertSame(0, DB::table('rt_company_memberships')->where('company_id', $company)->where('role_code', 'company_employee')->count());
    }

    public function test_employee_creation_is_throttled(): void
    {
        $company = $this->portalCompany();
        $this->loginOwner($this->owner($company));
        for ($i = 0; $i < 10; $i++) {
            $this->create(['email' => "throttle{$i}@example.test", 'name' => "T {$i}"])->assertSessionHasNoErrors();
        }
        $this->create(['email' => 'throttle-x@example.test'])->assertStatus(429);
    }

    public function test_employee_lists_are_company_scoped(): void
    {
        $a = $this->portalCompany();
        $b = $this->portalCompany();
        $ownerA = $this->owner($a);
        $this->employee($a, 'Alice Visible');
        $this->employee($b, 'Bob Hidden');
        $this->loginOwner($ownerA);
        $this->get(route('company-portal.employees.index'))->assertOk()->assertSee('Alice Visible')->assertDontSee('Bob Hidden');
    }
}
