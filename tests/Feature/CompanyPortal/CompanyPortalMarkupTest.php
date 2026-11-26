<?php

declare(strict_types=1);

namespace Tests\Feature\CompanyPortal;

use App\Services\Pricing\HotelPricingService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CompanyPortalFixtures;
use Tests\TestCase;

/**
 * Company markup in the portal and its effect on the existing B2B hotel APIs.
 * Supplier HTTP is mocked: deposit 100.00 USD; Royal B2B rule 10% + 1.00 gives an
 * agency purchase price of 111.00 that company markup must never change.
 */
final class CompanyPortalMarkupTest extends TestCase
{
    use DatabaseTransactions;
    use CompanyPortalFixtures;

    private string $company;
    private \App\Models\User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
        $this->grantCompanyPricingToCompanyAdmin();
        $this->company = $this->portalCompany();
        $this->owner = $this->owner($this->company);
    }

    private function pricing(): HotelPricingService
    {
        return app(HotelPricingService::class);
    }

    private function save(array $body)
    {
        return $this->from(route('company-portal.markup.index'))->post(route('company-portal.markup.save'), $body);
    }

    private function version(): int
    {
        return (int) ($this->pricing()->activeRule('company_b2b', $this->company)['version'] ?? 0);
    }

    public function test_page_shows_missing_royal_pricing_and_save_is_refused(): void
    {
        $this->loginOwner($this->owner);
        $this->get(route('company-portal.markup.index'))->assertOk()->assertViewHas('hasRoyalPrice', false)->assertViewHas('companyRule', null);
        $this->save(['percentage' => '5', 'fixed' => '0', 'expected_version' => 0])->assertSessionHasErrors('pricing');
        $this->assertNull($this->pricing()->activeRule('company_b2b', $this->company));
    }

    public function test_valid_saves_versioning_conflicts_and_audit_actor(): void
    {
        $this->pricing()->saveRule('royal_b2b', $this->company, '10', '1.00');
        $royalBefore = $this->pricing()->activeRule('royal_b2b', $this->company);
        $this->loginOwner($this->owner);

        $this->save(['percentage' => '5', 'fixed' => '2.00', 'expected_version' => 0])
            ->assertSessionHas('success', 'Company markup updated successfully.');
        $this->assertSame(1, $this->version());
        $this->get(route('company-portal.markup.index'))->assertOk()->assertSee('value="5', false);

        $this->save(['percentage' => '0', 'fixed' => '0', 'expected_version' => 1])->assertSessionHasNoErrors();
        $this->save(['percentage' => '12.345678', 'fixed' => '1000000', 'expected_version' => 2])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->version());

        // Stale version: rejected with a conflict message, nothing saved.
        $this->save(['percentage' => '7', 'fixed' => '0', 'expected_version' => 1])
            ->assertSessionHas('pricing_conflict', 'Pricing settings have changed. Please review and try again.');
        $this->assertSame(3, $this->version());
        $this->get(route('company-portal.markup.index'))->assertSee('Pricing settings have changed');

        $this->assertEquals($royalBefore, $this->pricing()->activeRule('royal_b2b', $this->company));
        $this->assertTrue(DB::table('rt_audit_events')->where('actor_user_id', $this->owner->id)->exists(),
            'Company pricing changes are audited with the owner as actor.');
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->pricing()->saveRule('royal_b2b', $this->company, '10', '1.00');
        $this->loginOwner($this->owner);
        $cases = [
            [['percentage' => '-1', 'fixed' => '0', 'expected_version' => 0], 'percentage'],
            [['percentage' => '100.5', 'fixed' => '0', 'expected_version' => 0], 'percentage'],
            [['percentage' => '1.1234567', 'fixed' => '0', 'expected_version' => 0], 'percentage'],
            [['percentage' => '1', 'fixed' => '-5', 'expected_version' => 0], 'fixed'],
            [['percentage' => '1', 'fixed' => '1000000.01', 'expected_version' => 0], 'fixed'],
            [['percentage' => '1', 'fixed' => '1.001', 'expected_version' => 0], 'fixed'],
            [['percentage' => '1', 'fixed' => '1', 'expected_version' => -1], 'expected_version'],
            [['percentage' => '1', 'fixed' => '1', 'expected_version' => 'abc'], 'expected_version'],
        ];
        foreach ($cases as [$body, $field]) {
            \Illuminate\Support\Facades\Cache::flush(); // stay under the save throttle
            $this->loginOwner($this->owner);
            $this->save($body)->assertSessionHasErrors($field);
        }
        $this->assertSame(0, $this->version());
        $this->get(route('company-portal.markup.index'))->assertOk();
    }

    public function test_requires_pricing_permission_and_is_company_scoped_and_throttled(): void
    {
        $other = $this->portalCompany();
        $this->pricing()->saveRule('royal_b2b', $this->company, '10', '1.00');
        $this->pricing()->saveRule('royal_b2b', $other, '10', '1.00');
        $this->pricing()->saveRule('company_b2b', $other, '3', '0');
        $otherBefore = $this->pricing()->activeRule('company_b2b', $other);
        $this->loginOwner($this->owner);

        // Session injection of another company id is not possible via the request body.
        $this->save(['percentage' => '9', 'fixed' => '0', 'expected_version' => 0, 'company_id' => $other])->assertSessionHasNoErrors();
        $this->assertEquals($otherBefore, $this->pricing()->activeRule('company_b2b', $other));
        $this->assertSame('9.000000', $this->pricing()->activeRule('company_b2b', $this->company)['percentage'] ?? null);

        for ($i = 0; $i < 9; $i++) {
            $this->save(['percentage' => '9', 'fixed' => '0', 'expected_version' => 99]);
        }
        $this->save(['percentage' => '9', 'fixed' => '0', 'expected_version' => 99])->assertStatus(429);

        DB::table('rt_role_permissions')->where('permission_id', DB::table('rt_permissions')->where('code', 'pricing.company.manage')->value('id'))->delete();
        \Illuminate\Support\Facades\Cache::flush();
        $this->get(route('company-portal.markup.index'))->assertForbidden();
        $this->save(['percentage' => '1', 'fixed' => '0', 'expected_version' => 1])->assertForbidden();
    }

    public function test_markup_saved_in_portal_reaches_b2b_rooms_select_prebook_and_draft_without_changing_wallet_charge(): void
    {
        $pricing = $this->pricing();
        $pricing->saveRule('royal_b2b', $this->company, '10', '1.00');
        $other = $this->portalCompany();
        $pricing->saveRule('royal_b2b', $other, '10', '1.00');
        $pricing->saveRule('company_b2b', $other, '3', '0');
        $otherBefore = $pricing->activeRule('company_b2b', $other);
        $walletBefore = DB::table('rt_b2b_finance_accounts')->where('company_id', $this->company)->first();
        $supplier = DB::table('rt_suppliers')->where('code', 'RATEHAWK')->value('id') ?? tap((string) Str::uuid(), fn ($id) =>
            DB::table('rt_suppliers')->insert(['id' => $id, 'code' => 'RATEHAWK', 'name' => 'RateHawk', 'status' => 'active']));
        DB::table('rt_suppliers')->where('id', $supplier)->update(['status' => 'active', 'adapter_code' => 'RATEHAWK']);
        $this->fakeSupplier();

        // 1) Owner sets 5% + 2.00 in the portal.
        $this->loginOwner($this->owner);
        $this->save(['percentage' => '5', 'fixed' => '2.00', 'expected_version' => 0])->assertSessionHasNoErrors();
        $this->post(route('company-portal.logout'));
        $token = $this->owner->createToken('it', ['b2b:access', 'company:' . $this->company])->plainTextToken;

        $rooms = $this->roomsQuote($token);
        $this->assertSame(['118.55', '111.00'], [$rooms->json('rooms.0.price.selling_price'), $rooms->json('rooms.0.price.agency_purchase_price')]);

        // 2) Owner changes it to 10% + 0; new searches use the new selling price only.
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
        $this->loginOwner($this->owner);
        $this->save(['percentage' => '10', 'fixed' => '0', 'expected_version' => 1])->assertSessionHasNoErrors();
        $this->post(route('company-portal.logout'));

        $rooms = $this->roomsQuote($token);
        $this->assertSame(['122.10', '111.00'], [$rooms->json('rooms.0.price.selling_price'), $rooms->json('rooms.0.price.agency_purchase_price')]);
        $this->assertStringNotContainsString('100.00', (string) $rooms->getContent(), 'Supplier cost must not be exposed.');

        $select = $this->api($token)->postJson('/api/v1/b2b/hotels/rooms/select', ['rooms_token' => $rooms->json('rooms_token'), 'rate_index' => 0])->assertOk();
        $this->assertSame('122.10', $select->json('price.selling_price'));
        $prebook = $this->api($token)->postJson('/api/v1/b2b/hotels/rooms/prebook', ['selection_token' => $select->json('selection_token')])->assertOk();
        $this->assertSame(['122.10', '111.00'], [$prebook->json('price.selling_price'), $prebook->json('price.agency_purchase_price')]);

        $draft = $this->api($token)->withHeader('Idempotency-Key', 'markup-draft-' . Str::random(12))->postJson('/api/v1/b2b/bookings/draft', [
            'review_token' => $prebook->json('review_token'),
            'customer' => ['first_name' => 'Mona', 'last_name' => 'Hassan', 'email' => 'mona@example.test'],
            'contact_email' => 'agent@example.test', 'contact_phone' => '+201000000001',
            'guests' => [
                ['first_name' => 'Jane', 'last_name' => 'Doe', 'guest_type' => 'adult', 'is_lead' => true, 'room_index' => 0],
                ['first_name' => 'John', 'last_name' => 'Doe', 'guest_type' => 'adult', 'is_lead' => false, 'room_index' => 0],
            ],
        ]);
        $draft->assertCreated();
        $this->assertSame(['122.10', '111.00'], [$draft->json('customer_total'), $draft->json('agency_purchase_price')]);
        $booking = DB::table('rt_bookings')->where('id', $draft->json('booking_id'))->first();
        $this->assertSame(0, bccomp((string) $booking->approved_total, '111.00', 2), 'Wallet charge = agency purchase price, unaffected by company markup.');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        // 3) A later markup change never rewrites the existing draft's immutable snapshots.
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
        $this->loginOwner($this->owner);
        $this->save(['percentage' => '20', 'fixed' => '0', 'expected_version' => 2])->assertSessionHasNoErrors();
        $this->assertSame(['111.000000', '122.100000'], DB::table('rt_booking_price_snapshots')->where('booking_id', $booking->id)
            ->orderBy('version')->pluck('customer_total')->all());

        $this->assertEquals($otherBefore, $pricing->activeRule('company_b2b', $other));
        $this->assertEquals($walletBefore, DB::table('rt_b2b_finance_accounts')->where('company_id', $this->company)->first());
    }

    private function api(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function roomsQuote(string $token)
    {
        return $this->api($token)->postJson('/api/v1/b2b/hotels/10700720/rooms', [
            'checkin' => now()->addDays(10)->toDateString(), 'checkout' => now()->addDays(12)->toDateString(),
            'residency' => 'eg', 'currency' => 'USD', 'guests' => [['adults' => 2, 'children' => []]],
        ])->assertOk();
    }

    private function fakeSupplier(): void
    {
        $rate = fn (string $hash) => [
            'room_name' => 'Deluxe King', 'book_hash' => $hash, 'match_hash' => 'm-' . $hash,
            'daily_prices' => ['50.00', '50.00'], 'meal' => 'breakfast',
            'meal_data' => ['value' => 'breakfast', 'has_breakfast' => true],
            'payment_options' => ['payment_types' => [[
                'type' => 'deposit', 'amount' => '100.00', 'currency_code' => 'USD',
                'show_amount' => '100.00', 'show_currency_code' => 'USD',
                'vat_data' => ['included' => false, 'applied' => false], 'tax_data' => [],
            ]]],
        ];
        $this->app->instance(RateHawkClient::class, new class ($rate) extends RateHawkClient {
            public function __construct(private readonly \Closure $rate) {}

            public function post(string $path, array $payload, int $timeout = 30): array
            {
                return match ($path) {
                    '/api/b2b/v3/search/hp/' => ['status' => 'ok', 'data' => ['hotels' => [['id' => 'test_hotel', 'hid' => 10700720, 'rates' => [($this->rate)('h-markup-rate')]]]]],
                    '/api/b2b/v3/hotel/prebook/' => ['status' => 'ok', 'data' => ['hotels' => [['id' => 'test_hotel', 'hid' => 10700720, 'rates' => [($this->rate)('p-markup-rate')]]],
                        'changes' => ['price_changed' => false]]],
                    default => throw new \LogicException("Unexpected supplier call {$path}"),
                };
            }
        });
    }
}
