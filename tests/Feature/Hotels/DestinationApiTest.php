<?php

declare(strict_types=1);

namespace Tests\Feature\Hotels;

use App\Models\User;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * HTTP contract for destination autocomplete (B2B + Admin) and destination-based
 * hotel search. All supplier HTTP is mocked; fixtures are rolled back.
 */
final class DestinationApiTest extends TestCase
{
    use DatabaseTransactions;

    private const B2B_URL = '/api/v1/b2b/destinations';
    private const ADMIN_URL = '/admin/hotels/destinations';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('royal_test', DB::connection()->getDatabaseName(), 'Tests must use the isolated test DB.');
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();
        config([
            'services.ratehawk.environment' => 'sandbox',
            'services.ratehawk.destination_scope' => 'all',
            'services.ratehawk.key_id' => 'test-key-id',
            'services.ratehawk.api_key' => 'test-api-key',
        ]);
    }

    // ------------------------------------------------------------ fixtures

    /** @return array{0: string, 1: string} company id, token */
    private function b2bMember(array $abilities = ['b2b:access'], string $role = 'company_admin'): array
    {
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Dest API ' . substr($companyId, 0, 8), 'status' => 'approved']);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => $role, 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('rt_roles')->where('code', $role)->where('portal', 'b2b')->value('id'),
            'company_id' => $companyId,
        ]);
        $token = $user->createToken('dest-test', [...$abilities, 'company:' . $companyId])->plainTextToken;

        return [$companyId, $token];
    }

    private function adminUser(?string $permission): User
    {
        $user = User::factory()->create();
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        if ($permission !== null) {
            $roleId = (string) Str::uuid();
            DB::table('rt_roles')->insert(['id' => $roleId, 'code' => 'dest_test_' . Str::random(10), 'portal' => 'admin']);
            DB::table('rt_role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => DB::table('rt_permissions')->where('code', $permission)->value('id'),
            ]);
            DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'company_id' => null]);
        }

        return $user;
    }

    private function asAdmin(?string $permission = 'hotels.view'): self
    {
        $this->withoutVite();

        return $this->withSession(['auth_portal' => 'admin'])->actingAs($this->adminUser($permission));
    }

    private function mockMulticomplete(int $times = 1): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->times($times)
            ->with('/api/b2b/v3/search/multicomplete/', Mockery::type('array'), 10)
            ->andReturn(['status' => 'ok', 'data' => ['regions' => [
                ['id' => 6053839, 'name' => 'Dubai', 'type' => 'City', 'country_code' => 'AE'],
            ]]]);
        $this->app->instance(RateHawkClient::class, $client);
    }

    private function forbidSupplier(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');
        $this->app->instance(RateHawkClient::class, $client);
    }

    private function b2bEntitlement(): void
    {
        $supplier = DB::table('rt_suppliers')->where('code', 'RATEHAWK')->value('id');
        if (!$supplier) {
            $supplier = (string) Str::uuid();
            DB::table('rt_suppliers')->insert(['id' => $supplier, 'code' => 'RATEHAWK', 'name' => 'RateHawk', 'status' => 'active']);
        }
        DB::table('rt_suppliers')->where('id', $supplier)->update(['status' => 'active']);
        DB::table('rt_supplier_entitlements')->insert([
            'supplier_id' => $supplier, 'channel' => 'b2b', 'service_type' => 'hotel',
            'is_allowed' => true, 'is_verified' => true, 'valid_from' => now()->subDay(),
        ]);
    }

    private function searchBody(array $location): array
    {
        return $location + [
            'checkin' => now()->addDays(20)->toDateString(),
            'checkout' => now()->addDays(22)->toDateString(),
            'residency' => 'eg',
            'guests' => [['adults' => 2, 'children' => []]],
        ];
    }

    // ------------------------------------------------------- B2B autocomplete

    public function test_b2b_autocomplete_returns_contract(): void
    {
        [, $token] = $this->b2bMember();
        $this->mockMulticomplete();

        $this->withToken($token)->getJson(self::B2B_URL . '?q=dub&language=en&limit=5')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'code' => 'OK',
                'scope' => 'all',
                'data' => [[
                    'id' => 'region:6053839', 'type' => 'city', 'name' => 'Dubai',
                    'country' => 'United Arab Emirates', 'country_code' => 'AE', 'parent' => null,
                ]],
            ]);
    }

    public function test_b2b_autocomplete_requires_authentication(): void
    {
        $this->forbidSupplier();
        $this->getJson(self::B2B_URL . '?q=dubai')->assertUnauthorized();
    }

    public function test_b2b_token_without_b2b_access_is_forbidden(): void
    {
        $this->forbidSupplier();
        [, $token] = $this->b2bMember([]);
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai')->assertForbidden();
    }

    public function test_b2b_user_of_suspended_company_is_forbidden(): void
    {
        $this->forbidSupplier();
        [$companyId, $token] = $this->b2bMember();
        DB::table('rt_companies')->where('id', $companyId)->update(['status' => 'suspended']);
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai')->assertForbidden();
    }

    public function test_b2b_token_scoped_to_another_company_is_forbidden(): void
    {
        $this->forbidSupplier();
        [$companyA] = $this->b2bMember();
        // A user of company B holding a token that claims company A.
        $user = User::factory()->create();
        [$companyB] = [$this->b2bMemberCompanyFor($user)];
        $token = $user->createToken('cross', ['b2b:access', 'company:' . $companyA])->plainTextToken;
        $this->assertNotSame($companyA, $companyB);
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai')->assertForbidden();
    }

    private function b2bMemberCompanyFor(User $user): string
    {
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Other ' . substr($companyId, 0, 8), 'status' => 'approved']);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => 'company_admin', 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('rt_roles')->where('code', 'company_admin')->where('portal', 'b2b')->value('id'),
            'company_id' => $companyId,
        ]);

        return $companyId;
    }

    public function test_b2b_validation_rejects_short_query_bad_language_and_limit(): void
    {
        $this->forbidSupplier();
        [, $token] = $this->b2bMember();
        $this->withToken($token)->getJson(self::B2B_URL . '?q=d')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->withToken($token)->getJson(self::B2B_URL)->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->withToken($token)->getJson(self::B2B_URL . '?q=' . str_repeat('a', 101))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai&language=fr')->assertUnprocessable()->assertJsonValidationErrors('language');
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai&limit=21')->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai&limit=0')->assertUnprocessable()->assertJsonValidationErrors('limit');
    }

    public function test_b2b_supplier_failure_returns_generic_503(): void
    {
        [, $token] = $this->b2bMember();
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()->andThrow(new RuntimeException('RateHawk API returned HTTP 502'));
        $this->app->instance(RateHawkClient::class, $client);

        $response = $this->withToken($token)->getJson(self::B2B_URL . '?q=dubai')
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'code' => 'DESTINATION_SEARCH_UNAVAILABLE',
                'message' => 'Destination search is temporarily unavailable. Please try again.',
                'data' => [],
            ]);
        $this->assertStringNotContainsString('RateHawk', (string) $response->getContent());
    }

    public function test_b2b_autocomplete_is_cached_across_users(): void
    {
        $this->mockMulticomplete(1);
        [, $tokenA] = $this->b2bMember();
        [, $tokenB] = $this->b2bMember();
        $this->withToken($tokenA)->getJson(self::B2B_URL . '?q=dub')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenB)->getJson(self::B2B_URL . '?q=dub')->assertOk()->assertJsonPath('data.0.id', 'region:6053839');
    }

    public function test_b2b_autocomplete_is_throttled(): void
    {
        config(['services.ratehawk.destination_scope' => 'sandbox_known']);
        $this->forbidSupplier();
        [, $token] = $this->b2bMember();
        for ($i = 0; $i < 30; $i++) {
            $this->withToken($token)->getJson(self::B2B_URL . '?q=dub')->assertOk();
        }
        $this->withToken($token)->getJson(self::B2B_URL . '?q=dub')->assertStatus(429);
    }

    public function test_b2b_sandbox_known_scope(): void
    {
        config(['services.ratehawk.destination_scope' => 'sandbox_known']);
        $this->forbidSupplier();
        [, $token] = $this->b2bMember();
        $this->withToken($token)->getJson(self::B2B_URL . '?q=pa')
            ->assertOk()
            ->assertJsonPath('scope', 'sandbox_known')
            ->assertJsonPath('data.0.id', 'paris')
            ->assertJsonCount(1, 'data');
    }

    // ----------------------------------------------------- Admin autocomplete

    public function test_admin_with_hotels_view_gets_destinations(): void
    {
        $this->mockMulticomplete();
        $this->asAdmin()->getJson(self::ADMIN_URL . '?q=dub')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'region:6053839')
            ->assertJsonPath('scope', 'all');
    }

    public function test_admin_without_hotels_view_is_forbidden(): void
    {
        $this->forbidSupplier();
        $this->asAdmin('dashboard.view')->getJson(self::ADMIN_URL . '?q=dub')->assertForbidden();
    }

    public function test_b2b_session_cannot_use_admin_endpoint(): void
    {
        $this->forbidSupplier();
        $this->withSession(['auth_portal' => 'b2b'])->actingAs($this->adminUser('hotels.view'))
            ->getJson(self::ADMIN_URL . '?q=dub')->assertForbidden();
    }

    public function test_admin_guest_is_redirected_to_login(): void
    {
        $this->forbidSupplier();
        $this->get(self::ADMIN_URL . '?q=dub')->assertRedirect(route('admin.login'));
    }

    public function test_admin_validation_and_throttle(): void
    {
        config(['services.ratehawk.destination_scope' => 'sandbox_known']);
        $this->forbidSupplier();
        $admin = $this->asAdmin();
        $admin->getJson(self::ADMIN_URL . '?q=d')->assertUnprocessable()->assertJsonValidationErrors('q');
        for ($i = 1; $i < 30; $i++) {
            $admin->getJson(self::ADMIN_URL . '?q=dub')->assertOk();
        }
        $admin->getJson(self::ADMIN_URL . '?q=dub')->assertStatus(429);
    }

    // ------------------------------------------- destination-based hotel search

    public function test_b2b_search_with_region_token_uses_serp_region_with_resolved_id(): void
    {
        $this->b2bEntitlement();
        [, $token] = $this->b2bMember();
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()->withArgs(function (string $path, array $payload): bool {
            return $path === '/api/b2b/v3/search/serp/region/'
                && $payload['region_id'] === 6053839
                && !array_key_exists('latitude', $payload)
                && !array_key_exists('destination', $payload)
                && $payload['currency'] === 'USD'
                && $payload['residency'] === 'eg';
        })->andReturn(['status' => 'ok', 'data' => ['hotels' => []]]);
        $this->app->instance(RateHawkClient::class, $client);

        $this->withToken($token)->postJson('/api/v1/b2b/hotels/search', $this->searchBody(['destination' => 'region:6053839']))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_b2b_search_with_legacy_slug_uses_existing_geo_coordinates(): void
    {
        $this->b2bEntitlement();
        [, $token] = $this->b2bMember();
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()->withArgs(function (string $path, array $payload): bool {
            return $path === '/api/b2b/v3/search/serp/geo/'
                && $payload['latitude'] === 25.2048 && $payload['longitude'] === 55.2708
                && $payload['radius'] === 10000;
        })->andReturn(['status' => 'ok', 'data' => ['hotels' => []]]);
        $this->app->instance(RateHawkClient::class, $client);

        $this->withToken($token)->postJson('/api/v1/b2b/hotels/search', $this->searchBody(['destination' => 'Dubai']))
            ->assertOk();
    }

    public function test_b2b_search_with_coordinates_still_works(): void
    {
        $this->b2bEntitlement();
        [, $token] = $this->b2bMember();
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()->withArgs(fn (string $path, array $payload): bool =>
            $path === '/api/b2b/v3/search/serp/geo/' && $payload['latitude'] === 48.87844 && $payload['longitude'] === 2.35829)
            ->andReturn(['status' => 'ok', 'data' => ['hotels' => []]]);
        $this->app->instance(RateHawkClient::class, $client);

        $this->withToken($token)->postJson('/api/v1/b2b/hotels/search', $this->searchBody(['latitude' => 48.87844, 'longitude' => 2.35829]))
            ->assertOk();
    }

    public function test_b2b_search_rejects_unknown_or_malformed_destination(): void
    {
        $this->b2bEntitlement();
        $this->forbidSupplier();
        [, $token] = $this->b2bMember();
        foreach (['atlantis', 'region:0', 'region:abc', 'x'] as $destination) {
            $this->withToken($token)->postJson('/api/v1/b2b/hotels/search', $this->searchBody(['destination' => $destination]))
                ->assertUnprocessable()->assertJsonValidationErrors('destination');
        }
        $this->withToken($token)->postJson('/api/v1/b2b/hotels/search', $this->searchBody([]))
            ->assertUnprocessable()->assertJsonValidationErrors(['destination', 'latitude', 'longitude']);
    }

    public function test_admin_search_with_region_token_uses_serp_region(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()->withArgs(fn (string $path, array $payload): bool =>
            $path === '/api/b2b/v3/search/serp/region/' && $payload['region_id'] === 2734)
            ->andReturn(['status' => 'ok', 'data' => ['hotels' => []]]);
        $this->app->instance(RateHawkClient::class, $client);

        $this->asAdmin()->get('/admin/hotels?' . http_build_query($this->adminSearch('region:2734')))
            ->assertOk()->assertSessionHasNoErrors();
    }

    public function test_admin_search_legacy_slugs_keep_working(): void
    {
        $expected = ['dubai' => [25.2048, 55.2708], 'cairo' => [30.0444, 31.2357], 'riyadh' => [24.7136, 46.6753]];
        $client = Mockery::mock(RateHawkClient::class);
        foreach ($expected as [$lat, $lng]) {
            $client->shouldReceive('post')->once()->withArgs(fn (string $path, array $payload): bool =>
                $path === '/api/b2b/v3/search/serp/geo/' && $payload['latitude'] === $lat
                && $payload['longitude'] === $lng && $payload['radius'] === 10000)
                ->andReturn(['status' => 'ok', 'data' => ['hotels' => []]]);
        }
        $this->app->instance(RateHawkClient::class, $client);
        $admin = $this->asAdmin();
        foreach (array_keys($expected) as $slug) {
            $admin->get('/admin/hotels?' . http_build_query($this->adminSearch($slug)))->assertOk()->assertSessionHasNoErrors();
        }
    }

    public function test_admin_search_rejects_unknown_destination(): void
    {
        $this->forbidSupplier();
        $this->asAdmin()->get('/admin/hotels?' . http_build_query($this->adminSearch('atlantis')))
            ->assertSessionHasErrors('destination');
    }

    private function adminSearch(string $destination): array
    {
        return [
            'search' => '1', 'destination' => $destination, 'occupancy' => '1-2',
            'checkin' => now()->addDays(10)->toDateString(),
            'checkout' => now()->addDays(12)->toDateString(),
            'residency' => 'eg',
        ];
    }
}
