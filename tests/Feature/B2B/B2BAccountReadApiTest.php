<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Read-only B2B finance, customer and booking endpoints: permissions, company isolation, safe fields. */
final class B2BAccountReadApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array{0: string, 1: string, 2: User} company id, token, user */
    private function member(string $role = 'company_admin', ?string $companyId = null): array
    {
        $user = User::factory()->create();
        $companyId ??= (string) Str::uuid();
        if (!DB::table('rt_companies')->where('id', $companyId)->exists()) {
            DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Read API ' . substr($companyId, 0, 8), 'status' => 'approved']);
        }
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => $role, 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('rt_roles')->where('code', $role)->where('portal', 'b2b')->value('id'),
            'company_id' => $companyId,
        ]);
        $token = $user->createToken('read-api-test', ['b2b:access', 'company:' . $companyId])->plainTextToken;

        return [$companyId, $token, $user];
    }

    private function customer(string $companyId, int $createdBy, string $last = 'Hassan'): string
    {
        $id = (string) Str::uuid();
        DB::table('rt_customers')->insert([
            'id' => $id, 'company_id' => $companyId, 'first_name' => 'Laila', 'last_name' => $last,
            'email' => strtolower($last) . '@example.test', 'phone' => '+201000000000', 'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function booking(string $companyId, int $userId, string $customerId, string $status = 'draft'): string
    {
        $orderId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        DB::table('rt_orders')->insert([
            'id' => $orderId, 'order_no' => 'ORD-' . strtoupper(Str::random(16)), 'channel' => 'b2b',
            'company_id' => $companyId, 'customer_id' => $customerId, 'customer_user_id' => null,
            'created_by' => $userId, 'status' => 'draft', 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rt_bookings')->insert([
            'id' => $bookingId, 'order_id' => $orderId, 'booking_no' => 'HTL-' . strtoupper(Str::random(16)),
            'service_type' => 'hotel', 'channel' => 'b2b', 'company_id' => $companyId, 'customer_id' => $customerId,
            'customer_user_id' => null, 'status' => $status, 'currency' => 'USD', 'approved_total' => null, 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rt_hotel_booking_details')->insert([
            'booking_id' => $bookingId, 'hotel_id' => null, 'check_in' => '2030-12-06', 'check_out' => '2030-12-09',
            'rooms_count' => 1, 'supplier_offer_ref' => 'h-secret-offer-ref',
        ]);
        foreach ([['Laila', 'Hassan', true], ['Omar', 'Hassan', false]] as [$first, $last, $lead]) {
            DB::table('rt_booking_guests')->insert([
                'id' => (string) Str::uuid(), 'booking_id' => $bookingId, 'first_name' => $first, 'last_name' => $last,
                'guest_type' => 'adult', 'date_of_birth' => null, 'nationality_code' => 'EG', 'is_lead' => $lead,
            ]);
        }
        DB::table('rt_booking_price_snapshots')->insert([
            'id' => (string) Str::uuid(), 'booking_id' => $bookingId, 'version' => 1, 'currency' => 'USD',
            'supplier_cost' => '100.00', 'admin_markup' => '11.00', 'company_markup' => '7.55',
            'discount_amount' => 0, 'fee_amount' => 0, 'tax_amount' => 0, 'customer_total' => '118.55', 'created_at' => now(),
        ]);

        return $bookingId;
    }

    public function test_finance_requires_payments_view_and_hides_staff_actor(): void
    {
        [$companyId, $adminToken] = $this->member('company_admin');
        [, $employeeToken] = $this->member('company_employee', $companyId);
        [$otherCompany] = $this->member('company_admin');
        $admin = User::factory()->create();

        foreach ([$companyId, $otherCompany] as $company) {
            app(\App\Services\Finance\B2BFinanceService::class)->apply($company, (string) $admin->id, [
                'type' => 'wallet_topup', 'amount_cents' => 50000, 'reference' => 'TOPUP-' . substr($company, 0, 8), 'reason' => 'Bank transfer',
            ]);
        }

        $this->withToken($employeeToken)->getJson('/api/v1/b2b/finance')->assertForbidden();
        $this->app['auth']->forgetGuards(); // the next request authenticates a different token

        $response = $this->withToken($adminToken)->getJson('/api/v1/b2b/finance')->assertOk()
            ->assertJsonPath('account.wallet_cents', 50000)
            ->assertJsonPath('account.wallet_available_cents', 50000)
            ->assertJsonPath('account.currency', 'USD')
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.type', 'wallet_topup')
            ->assertJsonPath('entries.0.reference', 'TOPUP-' . substr($companyId, 0, 8));
        $this->assertArrayNotHasKey('actor_id', $response->json('entries.0'));
        $this->assertArrayNotHasKey('company_id', $response->json('entries.0'));

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/b2b/finance')->assertUnauthorized();
    }

    public function test_customers_list_and_details_are_company_scoped_with_safe_fields(): void
    {
        [$companyId, $token, $user] = $this->member();
        [$otherCompany, , $otherUser] = $this->member();
        $mine = $this->customer($companyId, $user->id, 'Hassan');
        $theirs = $this->customer($otherCompany, $otherUser->id, 'Other');

        $list = $this->withToken($token)->getJson('/api/v1/b2b/customers')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('data.0.id', $mine);
        $this->assertSame(['id', 'first_name', 'last_name', 'email', 'phone', 'created_at', 'updated_at'], array_keys($list->json('data.0')));

        $this->withToken($token)->getJson('/api/v1/b2b/customers?q=hass')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($token)->getJson("/api/v1/b2b/customers/{$mine}")->assertOk()
            ->assertJsonPath('data.email', 'hassan@example.test');
        $this->withToken($token)->getJson("/api/v1/b2b/customers/{$theirs}")->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/b2b/customers/not-a-uuid')->assertNotFound();

        $created = $this->withToken($token)->postJson('/api/v1/b2b/customers', ['first_name' => 'Nour', 'last_name' => 'Adel', 'email' => 'nour@example.test'])
            ->assertCreated();
        $this->assertArrayNotHasKey('created_by', $created->json('data'));
        $this->withToken($token)->postJson('/api/v1/b2b/customers', ['first_name' => 'Nour', 'last_name' => 'Adel'])->assertUnprocessable();
    }

    public function test_bookings_are_listed_and_shown_for_the_own_company_only(): void
    {
        [$companyId, $token, $user] = $this->member('company_employee');
        [$otherCompany, , $otherUser] = $this->member();
        $customer = $this->customer($companyId, $user->id);
        $draft = $this->booking($companyId, $user->id, $customer, 'draft');
        $this->booking($companyId, $user->id, $customer, 'confirmed');
        $foreign = $this->booking($otherCompany, $otherUser->id, $this->customer($otherCompany, $otherUser->id, 'Foreign'));

        $list = $this->withToken($token)->getJson('/api/v1/b2b/bookings')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.customer_total', '118.55')
            ->assertJsonPath('data.0.currency', 'USD')
            ->assertJsonPath('data.0.customer.id', $customer);
        $this->withToken($token)->getJson('/api/v1/b2b/bookings?status=draft')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.booking_id', $draft);
        $this->withToken($token)->getJson('/api/v1/b2b/bookings?status=unknown')->assertUnprocessable();

        $detail = $this->withToken($token)->getJson("/api/v1/b2b/bookings/{$draft}")->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.check_in', '2030-12-06')
            ->assertJsonCount(2, 'data.guests')
            ->assertJsonPath('data.guests.0.is_lead', true);
        $body = $detail->getContent() . $list->getContent();
        foreach (['supplier_offer_ref', 'h-secret-offer-ref', 'supplier_cost', 'admin_markup', 'company_markup', '100.00', $otherCompany] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        $this->withToken($token)->getJson("/api/v1/b2b/bookings/{$foreign}")->assertNotFound();
    }

    public function test_bookings_need_the_bookings_view_permission(): void
    {
        [$companyId] = $this->member();
        $user = User::factory()->create();
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => 'company_employee', 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        // Member without any B2B role: no permissions.
        $token = $user->createToken('no-role', ['b2b:access', 'company:' . $companyId])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/b2b/bookings')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/b2b/finance')->assertForbidden();
    }
}
