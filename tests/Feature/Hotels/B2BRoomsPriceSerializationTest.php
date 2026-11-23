<?php

namespace Tests\Feature\Hotels;

use App\Models\User;
use App\Services\Pricing\HotelPricingService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * B2B rooms API with a controlled $100 USD supplier rate (fake RateHawk client, no network):
 * verified prices come from the server's two markup levels, property taxes are disclosed, nothing
 * confidential is serialized, and Select re-quotes the same verified price.
 */
final class B2BRoomsPriceSerializationTest extends TestCase
{
    use DatabaseTransactions;

    private function rate(array $payment, string $hash): array
    {
        return [
            'room_name' => 'Deluxe King',
            'book_hash' => $hash,
            'match_hash' => 'm-' . $hash,
            'daily_prices' => ['50.00', '50.00'],
            'meal' => 'breakfast',
            'meal_data' => ['value' => 'breakfast', 'has_breakfast' => true],
            'room_data_trans' => ['bedding_type' => 'double'],
            'payment_options' => ['payment_types' => [$payment + [
                'type' => 'deposit',
                'amount' => '100.00',
                'currency_code' => 'USD',
                'show_amount' => '100.00',
                'show_currency_code' => 'USD',
                'vat_data' => ['included' => false, 'applied' => false, 'amount' => '0.00', 'currency_code' => 'USD', 'value' => '0.00'],
                'tax_data' => ['taxes' => [['name' => 'city_tax', 'included_by_supplier' => false, 'amount' => '23.00', 'currency_code' => 'AED']]],
                'commission_info' => ['show' => ['amount_net' => '100.00']],
            ]]],
        ];
    }

    private function signedInCompany(): array
    {
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Rooms Pricing Test', 'status' => 'approved']);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => 'company_admin', 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        // hotels.view comes from the company_admin B2B role, as in production.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('rt_roles')->where('code', 'company_admin')->where('portal', 'b2b')->value('id'),
            'company_id' => $companyId,
        ]);
        $token = $user->createToken('rooms-pricing-test', ['b2b:access', 'company:' . $companyId])->plainTextToken;

        return [$companyId, $token];
    }

    public function test_verified_rate_is_priced_by_the_server_and_select_requotes_it(): void
    {
        [$companyId, $token] = $this->signedInCompany();
        $pricing = app(HotelPricingService::class);
        $pricing->saveRule('royal_b2b', $companyId, '10', '1.00');
        $pricing->saveRule('company_b2b', $companyId, '5', '2.00');

        $rates = [
            $this->rate([], 'h-verified-rate'),
            $this->rate(['type' => 'hotel'], 'h-pay-at-hotel-rate'),
        ];
        $this->app->instance(RateHawkClient::class, new class ($rates) extends RateHawkClient {
            public function __construct(private readonly array $rates)
            {
            }

            public function post(string $path, array $payload, int $timeout = 30): array
            {
                return ['status' => 'ok', 'data' => ['hotels' => [['id' => 'test_hotel', 'hid' => $payload['hid'], 'rates' => $this->rates]]]];
            }
        });

        $response = $this->withToken($token)->postJson('/api/v1/b2b/hotels/10700720/rooms', [
            'checkin' => now()->addDays(10)->toDateString(),
            'checkout' => now()->addDays(12)->toDateString(),
            'residency' => 'eg',
            'currency' => 'USD',
            'guests' => [['adults' => 2, 'children' => []]],
        ])->assertOk();

        $response->assertJsonPath('rooms.0.price', [
            'verified' => true,
            'selling_price' => '118.55',
            'agency_purchase_price' => '111.00',
            'currency' => 'USD',
            'price_on_request' => false,
            'taxes_at_property' => [['amount' => '23.00', 'currency' => 'AED']],
        ]);
        $response->assertJsonPath('rooms.1.price.verified', false);
        $response->assertJsonPath('rooms.1.price.price_on_request', true);
        $response->assertJsonPath('rooms.1.price.selling_price', null);

        $body = $response->getContent();
        foreach (['100.00', 'book_hash', 'h-verified-rate', 'supplier_amount', 'commission_info', 'policy', 'reason'] as $secret) {
            $this->assertStringNotContainsString($secret, $body, "Rooms reply must not expose {$secret}.");
        }

        $select = $this->withToken($token)->postJson('/api/v1/b2b/hotels/rooms/select', [
            'rooms_token' => $response->json('rooms_token'),
            'rate_index' => 0,
        ])->assertOk();
        $select->assertJsonPath('price.verified', true);
        $select->assertJsonPath('price.selling_price', '118.55');
        $select->assertJsonPath('price.agency_purchase_price', '111.00');

        // An unverified rate can't be selected.
        $this->withToken($token)->postJson('/api/v1/b2b/hotels/rooms/select', [
            'rooms_token' => $response->json('rooms_token'),
            'rate_index' => 1,
        ])->assertStatus(409);
    }

    public function test_without_company_pricing_every_rate_stays_price_on_request(): void
    {
        [, $token] = $this->signedInCompany();
        $rates = [$this->rate([], 'h-verified-rate')];
        $this->app->instance(RateHawkClient::class, new class ($rates) extends RateHawkClient {
            public function __construct(private readonly array $rates)
            {
            }

            public function post(string $path, array $payload, int $timeout = 30): array
            {
                return ['status' => 'ok', 'data' => ['hotels' => [['id' => 'test_hotel', 'hid' => $payload['hid'], 'rates' => $this->rates]]]];
            }
        });

        $this->withToken($token)->postJson('/api/v1/b2b/hotels/10700720/rooms', [
            'checkin' => now()->addDays(10)->toDateString(),
            'checkout' => now()->addDays(12)->toDateString(),
            'residency' => 'eg',
            'guests' => [['adults' => 2, 'children' => []]],
        ])->assertOk()
            ->assertJsonPath('rooms.0.price.verified', false)
            ->assertJsonPath('rooms.0.price.price_on_request', true);
    }
}
