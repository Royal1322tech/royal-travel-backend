<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\UseCompanyPortalSessionCookie;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CompanyPortalFixtures;
use Tests\TestCase;

/**
 * Regression for the 2026-11-26 staging report: a Royal admin who signed in to the
 * Company Portal in the same browser got 403 on every admin page, because both
 * portals shared one session cookie. Uses real cookies and a persistent session store.
 */
final class AdminPortalSessionIsolationTest extends TestCase
{
    use DatabaseTransactions;
    use CompanyPortalFixtures;

    private const ADMIN_PASSWORD = 'Admin-Password-2026!';

    /** @var array<string, string> cookie jar: name => plain value */
    private array $jar = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
        // A persistent store is required to keep two independent sessions apart.
        config(['session.driver' => 'database', 'session.portal_base_cookie' => null]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['email' => 'root@royal.test', 'password' => Hash::make(self::ADMIN_PASSWORD)]);
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'company_id' => null,
            'role_id' => DB::table('rt_roles')->where('code', 'super_admin')->where('portal', 'admin')->value('id')]);

        return $user;
    }

    /** Sends a browser-like request: only cookies whose path matches are sent. */
    private function browser(string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        // Fresh per-request session objects, like separate PHP-FPM requests.
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->jar as $name => [$value, $cookiePath]) {
            if (str_starts_with($path, rtrim($cookiePath, '/') . '/') || $path === $cookiePath || $cookiePath === '/') {
                $this->withCookie($name, $value);
            }
        }
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());
        foreach ($response->headers->getCookies() as $cookie) {
            $plain = $response->getCookie($cookie->getName())?->getValue();
            $this->jar[$cookie->getName()] = [$plain, $cookie->getPath()];
        }

        return $response;
    }

    public function test_portal_uses_its_own_cookie_scoped_to_its_path(): void
    {
        $base = config('session.cookie');
        $admin = $this->browser('GET', '/admin/login')->assertOk();
        $portal = $this->browser('GET', '/b2b/company/login')->assertOk();

        $this->assertSame('/', $admin->getCookie($base, false)?->getPath());
        $this->assertNull($portal->getCookie($base, false), 'Portal must not (re)issue the admin cookie.');
        $portalCookie = $portal->getCookie($base . UseCompanyPortalSessionCookie::SUFFIX, false);
        $this->assertNotNull($portalCookie);
        $this->assertSame(UseCompanyPortalSessionCookie::PATH, $portalCookie->getPath());
        $this->assertTrue($portalCookie->isHttpOnly());

        // Back on admin, the default name/path are restored.
        $this->assertSame('/', $this->browser('GET', '/admin/login')->getCookie($base, false)?->getPath());
    }

    public function test_admin_and_company_owner_sessions_coexist_in_one_browser(): void
    {
        $admin = $this->adminUser();
        $company = $this->portalCompany();
        $owner = $this->owner($company);

        // Admin signs in and can use the admin pages.
        $this->browser('GET', '/admin/login');
        $this->browser('POST', '/admin/login', ['email' => $admin->email, 'password' => self::ADMIN_PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->browser('GET', '/admin/bookings')->assertOk();

        // Same browser: the owner signs in to the Company Portal.
        $this->browser('GET', '/b2b/company/login');
        $this->browser('POST', '/b2b/company/login', ['email' => $owner->email, 'password' => self::OWNER_PASSWORD])
            ->assertRedirect(route('company-portal.dashboard'));
        $this->browser('GET', '/b2b/company/dashboard')->assertOk();

        // Before the fix the admin session had been replaced and these returned 403.
        foreach (['/admin/dashboard', '/admin/bookings', '/admin/support-tickets', '/admin/hotels', '/admin/companies'] as $page) {
            $this->browser('GET', $page)->assertOk();
        }
        // The portal still belongs to the owner, and admin access never leaks into it.
        $this->browser('GET', '/b2b/company/profile')->assertOk()->assertSee($owner->email);

        // Logging out of the portal leaves the admin signed in.
        $this->browser('POST', '/b2b/company/logout')->assertRedirect(route('company-portal.login'));
        $this->browser('GET', '/b2b/company/dashboard')->assertRedirect(route('company-portal.login'));
        $this->browser('GET', '/admin/bookings')->assertOk();
    }

    public function test_owner_session_never_grants_admin_and_admin_session_never_grants_portal(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        $this->browser('POST', '/b2b/company/login', ['email' => $owner->email, 'password' => self::OWNER_PASSWORD])
            ->assertRedirect(route('company-portal.dashboard'));
        // The portal cookie is path-scoped, so /admin sees no session at all.
        $this->browser('GET', '/admin/dashboard')->assertRedirect(route('admin.login'));

        $this->jar = [];
        $admin = $this->adminUser();
        $this->browser('POST', '/admin/login', ['email' => $admin->email, 'password' => self::ADMIN_PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        // The admin cookie is sent to /b2b/company but is not the portal cookie: guest there.
        $this->browser('GET', '/b2b/company/dashboard')->assertRedirect(route('company-portal.login'));
    }
}
