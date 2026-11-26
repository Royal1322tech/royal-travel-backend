<?php

declare(strict_types=1);

namespace Tests\Feature\CompanyPortal;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CompanyPortalFixtures;
use Tests\TestCase;

/** Company Portal login, owner-only authorization and session handling. */
final class CompanyPortalAuthTest extends TestCase
{
    use DatabaseTransactions;
    use CompanyPortalFixtures;

    private const PROTECTED_GETS = ['company-portal.dashboard', 'company-portal.employees.index', 'company-portal.bookings.index',
        'company-portal.customers.index', 'company-portal.profile.index', 'company-portal.markup.index'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
        $this->grantCompanyPricingToCompanyAdmin();
    }

    private function attempt(string $email, string $password = self::OWNER_PASSWORD, ?string $companyId = null)
    {
        return $this->from(route('company-portal.login'))->post(route('company-portal.login.submit'), array_filter([
            'email' => $email, 'password' => $password, 'company_id' => $companyId,
        ]));
    }

    private function assertRejected($response): void
    {
        // Reset the login limiter (array cache) so later cases are not throttled.
        \Illuminate\Support\Facades\Cache::flush();
        $response->assertRedirect(route('company-portal.login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_routes_are_registered_with_expected_names_and_middleware(): void
    {
        $this->assertSame(url('/b2b/company/login'), route('company-portal.login'));
        $this->assertSame(url('/b2b/company/bookings/' . ($id = (string) Str::uuid())), route('company-portal.bookings.show', $id));
        $routes = app('router')->getRoutes();
        $mw = fn (string $name) => $routes->getByName($name)->gatherMiddleware();
        foreach (self::PROTECTED_GETS as $name) {
            $this->assertContains('web', $mw($name), $name);
            $this->assertContains(\App\Http\Middleware\EnsureCompanyPortalOwner::class, $mw($name), $name);
        }
        $this->assertContains('company.permission:bookings.view', $mw('company-portal.bookings.show'));
        $this->assertContains('company.permission:pricing.company.manage', $mw('company-portal.markup.save'));
        $this->assertNotContains(\App\Http\Middleware\EnsureCompanyPortalOwner::class, $mw('company-portal.logout'));
        $this->assertContains('web', $mw('company-portal.logout'));
        $this->assertNotContains(\App\Http\Middleware\EnsureCompanyPortalOwner::class, $mw('company-portal.login.submit'));
    }

    public function test_login_page_renders_with_csrf_token(): void
    {
        $this->get(route('company-portal.login'))->assertOk()
            ->assertSee('name="_token"', false)->assertSee(route('company-portal.login.submit'), false);
    }

    public function test_eligible_owner_logs_in_and_session_is_regenerated(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company, 'Owner.Case@Example.test');
        $this->get(route('company-portal.login'));
        $before = $this->app['session']->getId();

        $this->attempt('  OWNER.case@example.TEST ')->assertRedirect(route('company-portal.dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->assertNotSame($before, $this->app['session']->getId(), 'Session id must be regenerated (fixation).');
        $this->assertSame('b2b', session('auth_portal'));
        $this->assertSame($company, session('active_company_id'));
        foreach (self::PROTECTED_GETS as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_ineligible_logins_are_rejected_with_a_generic_error(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);

        $this->assertRejected($this->attempt($owner->email, 'wrong-password'));
        $this->assertRejected($this->attempt('nobody@example.test'));

        $unverified = $this->owner($company);
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->assertRejected($this->attempt($unverified->email));

        $mustChange = $this->owner($company);
        $mustChange->forceFill(['must_change_password' => true])->save();
        $this->assertRejected($this->attempt($mustChange->email));

        $employee = $this->employee($company);
        $employee->forceFill(['password' => bcrypt(self::OWNER_PASSWORD)])->save();
        $this->assertRejected($this->attempt($employee->email));

        // company_admin who is not the primary contact
        $secondaryAdmin = $this->portalUser('secondary@example.test');
        $this->addMembership($secondaryAdmin, $company, 'company_admin');
        $this->addPrimaryContact($secondaryAdmin, $company, 'secondary');
        $this->assertRejected($this->attempt($secondaryAdmin->email));

        // primary contact without a company_admin membership
        $contactOnly = $this->portalUser('contact-only@example.test');
        $this->addMembership($contactOnly, $company, 'company_employee');
        $this->addPrimaryContact($contactOnly, $company);
        $this->assertRejected($this->attempt($contactOnly->email));

        // inactive / unapproved membership
        foreach ([['pending', true], ['suspended', true], ['active', false]] as [$status, $approved]) {
            $user = $this->portalUser("m-{$status}-" . (int) $approved . '@example.test');
            $this->addMembership($user, $company, 'company_admin', $status, $approved);
            $this->addPrimaryContact($user, $company);
            $this->assertRejected($this->attempt($user->email));
        }

        // company states other than approved
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $other = $this->portalCompany($status);
            $this->assertRejected($this->attempt($this->owner($other)->email));
        }
    }

    public function test_multiple_companies_require_explicit_selection(): void
    {
        $a = $this->portalCompany();
        $b = $this->portalCompany();
        $owner = $this->owner($a, 'multi@example.test');
        $this->addMembership($owner, $b, 'company_admin');
        $this->addPrimaryContact($owner, $b);

        $this->attempt($owner->email)->assertSessionHasErrors('company_id');
        $this->assertGuest();
        $this->get(route('company-portal.login'))->assertSee('name="company_id"', false);

        $this->attempt($owner->email, self::OWNER_PASSWORD, $b)->assertRedirect(route('company-portal.dashboard'));
        $this->assertSame($b, session('active_company_id'));
    }

    public function test_unauthorized_or_invalid_company_id_is_rejected(): void
    {
        $mine = $this->portalCompany();
        $owner = $this->owner($mine);
        $foreign = $this->portalCompany();
        $this->owner($foreign);

        $this->assertRejected($this->attempt($owner->email, self::OWNER_PASSWORD, $foreign));
        $this->attempt($owner->email, self::OWNER_PASSWORD, 'not-a-uuid')->assertSessionHasErrors('company_id');
        $this->assertGuest();
    }

    public function test_unauthenticated_access_redirects_to_login(): void
    {
        foreach (self::PROTECTED_GETS as $name) {
            $this->get(route($name))->assertRedirect(route('company-portal.login'));
        }
        $this->get(route('company-portal.bookings.show', (string) Str::uuid()))->assertRedirect(route('company-portal.login'));
        $this->get(route('company-portal.customers.show', (string) Str::uuid()))->assertRedirect(route('company-portal.login'));
        $this->post(route('company-portal.employees.store'), [])->assertRedirect(route('company-portal.login'));
        $this->post(route('company-portal.markup.save'), [])->assertRedirect(route('company-portal.login'));
    }

    public function test_tampered_session_company_or_portal_is_rejected(): void
    {
        $mine = $this->portalCompany();
        $owner = $this->owner($mine);
        $foreign = $this->portalCompany();

        $this->withSession(['auth_portal' => 'b2b', 'active_company_id' => $foreign])->actingAs($owner, 'web')
            ->get(route('company-portal.dashboard'))->assertForbidden();
        $this->flushSession();
        $this->withSession(['auth_portal' => 'admin', 'active_company_id' => $mine])->actingAs($owner, 'web')
            ->get(route('company-portal.dashboard'))->assertForbidden();
        $this->flushSession();
        $this->withSession(['auth_portal' => 'b2b'])->actingAs($owner, 'web')
            ->get(route('company-portal.dashboard'))->assertForbidden();
    }

    public function test_revocations_during_a_session_take_effect_immediately_and_logout_still_works(): void
    {
        $revocations = [
            'membership revoked' => fn (User $u, string $c) => DB::table('rt_company_memberships')->where('user_id', $u->id)->update(['status' => 'suspended']),
            'company suspended' => fn (User $u, string $c) => DB::table('rt_companies')->where('id', $c)->update(['status' => 'suspended']),
            'owner role revoked' => fn (User $u, string $c) => DB::table('rt_company_memberships')->where('user_id', $u->id)->update(['role_code' => 'company_employee']),
            'primary contact removed' => fn (User $u, string $c) => DB::table('rt_company_contacts')->where('user_id', $u->id)->update(['contact_type' => 'secondary']),
        ];
        foreach ($revocations as $label => $revoke) {
            $company = $this->portalCompany();
            $owner = $this->owner($company);
            $this->loginOwner($owner);
            $this->get(route('company-portal.dashboard'))->assertOk();

            $revoke($owner, $company);
            $this->get(route('company-portal.dashboard'))->assertForbidden();
            $this->get(route('company-portal.bookings.index'))->assertForbidden();

            // A revoked user can still end the session safely.
            $this->post(route('company-portal.logout'))->assertRedirect(route('company-portal.login'));
            $this->assertGuest();
            $this->get(route('company-portal.dashboard'))->assertRedirect(route('company-portal.login'));
        }
    }

    public function test_logout_ends_the_session(): void
    {
        $owner = $this->owner($this->portalCompany());
        $this->loginOwner($owner);
        $this->post(route('company-portal.logout'))->assertRedirect(route('company-portal.login'));
        $this->assertGuest();
        $this->assertNull(session('active_company_id'));
        foreach (self::PROTECTED_GETS as $name) {
            $this->get(route($name))->assertRedirect(route('company-portal.login'));
        }
        // Logging out as a guest is harmless.
        $this->post(route('company-portal.logout'))->assertRedirect(route('company-portal.login'));
    }

    public function test_csrf_is_enforced_on_every_state_changing_form(): void
    {
        $owner = $this->owner($this->portalCompany());
        $this->loginOwner($owner);
        // CSRF is bypassed while running unit tests; switch the environment to enforce it.
        $this->app['env'] = 'local';
        try {
            foreach ([route('company-portal.login.submit'), route('company-portal.employees.store'),
                route('company-portal.markup.save'), route('company-portal.logout')] as $url) {
                $this->post($url, [])->assertStatus(419);
            }
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertAuthenticatedAs($owner);
        foreach (['company-portal.employees.index', 'company-portal.markup.index', 'company-portal.dashboard'] as $page) {
            $this->get(route($page))->assertOk()->assertSee('name="_token"', false);
        }
    }

    public function test_login_is_throttled_with_its_own_counter(): void
    {
        $owner = $this->owner($this->portalCompany());
        for ($i = 0; $i < 5; $i++) {
            $this->attempt($owner->email, 'wrong-password')->assertSessionHasErrors('email');
        }
        $this->attempt($owner->email)->assertStatus(429);
    }

    public function test_admin_and_portal_sessions_are_separate(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        $this->loginOwner($owner);
        // Company owner session gets no Royal Admin access.
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/admin/companies')->assertForbidden();

        // A Royal admin session cannot use the portal.
        $admin = User::factory()->create();
        DB::table('rt_admin_access')->insert(['user_id' => $admin->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rt_user_roles')->insert(['user_id' => $admin->id, 'company_id' => null,
            'role_id' => DB::table('rt_roles')->where('code', 'super_admin')->value('id')]);
        $this->withSession(['auth_portal' => 'admin', 'active_company_id' => $company])->actingAs($admin, 'web')
            ->get(route('company-portal.dashboard'))->assertForbidden();
    }

    public function test_b2b_api_token_login_is_unaffected_and_api_rejects_portal_sessions(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        foreach (['website', 'mobile'] as $channel) {
            $this->postJson('/api/v1/b2b/auth/login', ['email' => $owner->email, 'password' => self::OWNER_PASSWORD,
                'company_id' => $company, 'channel' => $channel])->assertOk()->assertJsonPath('company_id', $company);
        }
        // A portal (session) login never authorizes the token-only B2B API.
        $this->loginOwner($owner);
        $this->getJson('/api/v1/b2b/bookings')->assertStatus(401);
    }
}
