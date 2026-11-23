<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use App\Services\Bookings\CustomerDirectoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Loyalty entries expose a fixed, safe field set; booking-draft customers must belong to the company. */
final class B2BLoyaltyAndCustomerScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function member(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Loyalty Test', 'status' => 'approved']);
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => 'company_admin', 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id, 'company_id' => $companyId,
            'role_id' => DB::table('rt_roles')->where('code', 'company_admin')->where('portal', 'b2b')->value('id'),
        ]);

        return [$companyId, $user, $user->createToken('loyalty', ['b2b:access', 'company:' . $companyId])->plainTextToken];
    }

    public function test_loyalty_transactions_have_a_fixed_safe_field_set(): void
    {
        [$companyId, $user, $token] = $this->member();
        DB::table('rt_loyalty_entries')->insert([
            'company_id' => $companyId, 'actor_user_id' => $user->id, 'entry_type' => 'manual_credit',
            'points' => 100, 'balance_bucket' => 'available', 'dedupe_key' => 'secret-dedupe-key', 'note' => 'Welcome bonus',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/b2b/loyalty')->assertOk()
            ->assertJsonPath('redemption_enabled', false)
            ->assertJsonPath('transactions.0.entry_type', 'manual_credit')
            ->assertJsonPath('transactions.0.balance_bucket', 'available')
            ->assertJsonPath('transactions.0.note', 'Welcome bonus')
            ->assertJsonPath('transactions.0.points', 100);
        $this->assertSame(['id', 'booking_id', 'entry_type', 'points', 'balance_bucket', 'note', 'created_at'], array_keys($response->json('transactions.0')));
        $this->assertStringNotContainsString('secret-dedupe-key', $response->getContent());
    }

    public function test_a_draft_customer_from_another_company_is_not_found(): void
    {
        [$companyId, $user] = $this->member();
        $directory = app(CustomerDirectoryService::class);
        $own = $directory->create($companyId, $user->id, ['first_name' => 'Nour', 'last_name' => 'Adel', 'email' => 'nour@example.test']);
        $this->assertSame($own->id, $directory->findForBooking($own->id, $companyId)->id);

        $this->expectException(ModelNotFoundException::class);
        $directory->findForBooking($own->id, (string) Str::uuid());
    }
}
