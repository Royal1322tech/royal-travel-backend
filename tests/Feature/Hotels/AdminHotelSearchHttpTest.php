<?php

namespace Tests\Feature\Hotels;

use App\Models\User;
use App\Services\Hotels\RateHawkHotelContentService;
use App\Services\Hotels\RateHawkSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AdminHotelSearchHttpTest extends TestCase
{
    use DatabaseTransactions;

    private function loginAdmin(): void
    {
        $user = User::factory()->create();

        $roleId = (string) Str::uuid();
        $permissionId = DB::table('rt_permissions')
            ->where('code', 'hotels.view')
            ->value('id') ?? (string) Str::uuid();

        DB::table('rt_roles')->insert([
            'id' => $roleId,
            'code' => 'admin_search_test_' . Str::random(12),
            'portal' => 'admin',
        ]);

        DB::table('rt_permissions')->insertOrIgnore([
            'id' => $permissionId,
            'code' => 'hotels.view',
        ]);

        DB::table('rt_role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'company_id' => null,
        ]);

        $this->withoutVite();
        $this->withSession(['auth_portal' => 'admin'])->actingAs($user);
    }

    private function searchParams(): array
    {
        return [
            'search' => '1',
            'destination' => 'dubai',
            'occupancy' => '1-2',
            'checkin' => now()->addDays(10)->toDateString(),
            'checkout' => now()->addDays(12)->toDateString(),
            'residency' => 'eg',
        ];
    }

    public function test_search_displays_hotel_without_supplier_secrets(): void
    {
        $this->loginAdmin();

        $rawRate = [
            'room_name' => 'Deluxe Room',
            'room_data_trans' => [
                'main_name' => 'Deluxe Room',
                'bedding_type' => 'King Bed',
            ],
            'meal' => 'breakfast',
            'meal_data' => [
                'value' => 'Breakfast Included',
                'has_breakfast' => true,
            ],
            'allotment' => 2,
            'is_package' => false,
            'match_hash' => 'PRIVATE_RATE_HASH',
            'search_hash' => 'PRIVATE_SEARCH_HASH',
            'payment_options' => [
                'payment_types' => [[
                    'type' => 'now',
                    'amount' => '98765.43',
                    'currency_code' => 'USD',
                    'commission_info' => [
                        'amount' => '87654.32',
                    ],
                ]],
            ],
        ];

        $this->mock(RateHawkSearchService::class)
            ->shouldReceive('searchByGeo')
            ->once()
            ->andReturn([
                'total' => 1,
                'hotels' => [[
                    'id' => 'test-hotel',
                    'hid' => 123456,
                    'rates' => [$rawRate],
                ]],
            ]);

        $this->mock(RateHawkHotelContentService::class)
            ->shouldReceive('getHotel')
            ->once()
            ->andReturn([
                'name' => 'Royal Test Hotel',
                'address' => 'Dubai Marina',
                'star_rating' => 5,
                'images' => [],
                'amenity_groups' => [],
            ]);

        $response = $this->get(route('admin.hotels.index', $this->searchParams()));

        $response->assertOk()
            ->assertSee('Royal Test Hotel')
            ->assertSee('Deluxe Room')
            ->assertSee('Dubai Marina')
            ->assertDontSee('PRIVATE_SUPPLIER_SECRET')
            ->assertDontSee('PRIVATE_RATE_HASH')
            ->assertDontSee('98765.43')
            ->assertDontSee('87654.32');
    }


    public function test_search_to_rooms_requires_original_session(): void
    {
        $this->loginAdmin();

        $rawRate = [
            'room_name' => 'Secure Deluxe Room',
            'room_data_trans' => [
                'bedding_type' => 'King Bed',
            ],
            'meal' => 'breakfast',
            'meal_data' => [
                'value' => 'Breakfast Included',
            ],
            'allotment' => 2,
            'match_hash' => 'SECRET_MATCH_HASH_123',
            'search_hash' => 'SECRET_SEARCH_HASH_456',
            'payment_options' => [
                'payment_types' => [[
                    'type' => 'now',
                    'amount' => '76543.21',
                    'currency_code' => 'USD',
                    'commission_info' => [
                        'amount' => '65432.10',
                    ],
                ]],
            ],
        ];

        $this->mock(RateHawkSearchService::class)
            ->shouldReceive('searchByGeo')
            ->once()
            ->andReturn([
                'total' => 1,
                'hotels' => [[
                    'id' => 'secure-test-hotel',
                    'hid' => 123456,
                    'rates' => [$rawRate],
                ]],
            ]);

        $this->mock(RateHawkHotelContentService::class)
            ->shouldReceive('getHotel')
            ->once()
            ->andReturn([
                'name' => 'Secure Test Hotel',
                'address' => 'Dubai Marina',
                'star_rating' => 5,
                'images' => [],
                'amenity_groups' => [],
            ]);

        $searchResponse = $this->get(
            route('admin.hotels.index', $this->searchParams())
        );

        $searchResponse->assertOk()
            ->assertSee('Secure Test Hotel')
            ->assertSee('View Rooms');

        $html = $searchResponse->getContent();

        $matched = preg_match(
            '~admin/hotels/rooms/([A-Za-z0-9]{48})~',
            $html,
            $matches
        );

        $this->assertSame(1, $matched, 'Expected a valid rooms link.');

        $token = $matches[1];
        $roomsUrl = route('admin.hotels.rooms', ['token' => $token]);

        // Simulate the browser returning the session cookie from search.
        $sessionName = config('session.cookie');
        $sessionId = $this->app['session']->driver()->getId();

        $this->withCookie($sessionName, $sessionId);

        $roomsResponse = $this->get($roomsUrl);

        $roomsResponse->assertOk()
            ->assertSee('Secure Deluxe Room')
            ->assertDontSee('SECRET_MATCH_HASH_123')
            ->assertDontSee('SECRET_SEARCH_HASH_456')
            ->assertDontSee('76543.21')
            ->assertDontSee('65432.10');

        // Preserve the authenticated Admin user, but change the session ID.
        // The cached rooms token must not be valid for this new session.
        $this->withCookie(
            $sessionName,
            str_repeat('a', 40)
        );

        $this->get($roomsUrl)->assertForbidden();
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSearchCases')]
    public function test_invalid_search_is_rejected_before_supplier_call(
        array $changes,
        string $field
    ): void {
        $this->loginAdmin();

        $this->mock(RateHawkSearchService::class)
            ->shouldNotReceive('searchByGeo');

        $params = array_replace($this->searchParams(), $changes);

        $this->get(route('admin.hotels.index', $params))
            ->assertRedirect()
            ->assertSessionHasErrors($field);
    }

    public static function invalidSearchCases(): array
    {
        return [
            'invalid destination' => [
                ['destination' => 'unknown-city'],
                'destination',
            ],
            'invalid occupancy' => [
                ['occupancy' => '9-20'],
                'occupancy',
            ],
            'past checkin' => [
                ['checkin' => now()->subDay()->toDateString()],
                'checkin',
            ],
            'checkout before checkin' => [
                [
                    'checkin' => now()->addDays(10)->toDateString(),
                    'checkout' => now()->addDays(9)->toDateString(),
                ],
                'checkout',
            ],
            'invalid residency' => [
                ['residency' => 'xx'],
                'residency',
            ],
            'invalid board' => [
                ['board' => ['invalid-meal']],
                'board.0',
            ],
            'invalid package type' => [
                ['package_type' => 'unknown'],
                'package_type',
            ],
            'invalid star rating' => [
                ['stars' => [9]],
                'stars.0',
            ],
            'invalid sorting' => [
                ['sort' => 'supplier_cost'],
                'sort',
            ],
        ];
    }

    public function test_supplier_failure_does_not_expose_internal_exception(): void
    {
        $this->loginAdmin();

        $this->mock(RateHawkSearchService::class)
            ->shouldReceive('searchByGeo')
            ->once()
            ->andThrow(new RuntimeException('PRIVATE_API_INTERNAL_ERROR'));

        $response = $this->get(route('admin.hotels.index', $this->searchParams()));

        $response->assertOk()
            ->assertSee('Unable to retrieve hotels')
            ->assertDontSee('PRIVATE_API_INTERNAL_ERROR');
    }
}
