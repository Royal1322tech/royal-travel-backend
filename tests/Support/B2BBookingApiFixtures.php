<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;

/**
 * Self-contained fixtures for B2B booking/confirmation/ticket HTTP tests.
 * Use together with DatabaseTransactions; every row is rolled back.
 *
 * Pricing used everywhere: verified supplier deposit 42.00 USD, agency purchase
 * price 46.20 USD (charged to the wallet), agency selling price 55.00 USD.
 */
trait B2BBookingApiFixtures
{
    protected const SUPPLIER_COST = '42.00';
    protected const PURCHASE = '46.20';
    protected const SELLING = '55.00';

    protected function setUpB2BFixtures(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        if (!app()->environment('testing') || preg_match('/(^|_)test$/D', $database) !== 1) {
            $this->fail('Refusing to run outside an isolated *_test database.');
        }
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();
        config([
            'services.ratehawk.environment' => 'sandbox',
            'services.ratehawk.sandbox_url' => 'https://api-sandbox.ratehawk.com',
            'services.ratehawk.allow_sandbox_finish' => true,
            'services.ratehawk.key_id' => 'test-key-id',
            'services.ratehawk.api_key' => 'test-api-key',
        ]);
        $this->forbidSupplier();
    }

    protected function supplierId(): string
    {
        $id = DB::table('rt_suppliers')->where('code', 'RATEHAWK')->value('id');
        if ($id === null) {
            $id = (string) Str::uuid();
            DB::table('rt_suppliers')->insert(['id' => $id, 'code' => 'RATEHAWK', 'name' => 'RateHawk', 'status' => 'active', 'adapter_code' => 'RATEHAWK']);
        }
        DB::table('rt_suppliers')->where('id', $id)->update(['status' => 'active', 'adapter_code' => 'RATEHAWK']);

        return (string) $id;
    }

    protected function company(int $walletCents = 50000): string
    {
        $companyId = (string) Str::uuid();
        DB::table('rt_companies')->insert(['id' => $companyId, 'legal_name' => 'Fixture ' . substr($companyId, 0, 8), 'status' => 'approved', 'account_currency' => 'USD']);
        DB::table('rt_b2b_finance_accounts')->insert([
            'company_id' => $companyId, 'currency' => 'USD', 'wallet_cents' => $walletCents,
            'wallet_reserved_cents' => 0, 'credit_limit_cents' => 0, 'credit_used_cents' => 0,
            'credit_frozen' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $companyId;
    }

    /** @return array{0: User, 1: string} user, plain token */
    protected function member(string $companyId, string $role = 'company_employee', array $abilities = ['b2b:access']): array
    {
        $user = User::factory()->create();
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'role_code' => $role, 'status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('rt_roles')->where('code', $role)->where('portal', 'b2b')->value('id'),
            'company_id' => $companyId,
        ]);

        return [$user, $user->createToken('fixture', [...$abilities, 'company:' . $companyId])->plainTextToken];
    }

    protected function prebookRate(string $deposit = self::SUPPLIER_COST): array
    {
        return [
            'book_hash' => 'p-fixture-prebook-hash',
            'room_name' => 'Standard Double Room',
            'daily_prices' => ['20.00', bcsub($deposit, '20.00', 2)],
            'payment_options' => ['payment_types' => [[
                'type' => 'deposit', 'amount' => $deposit, 'currency_code' => 'USD',
                'show_amount' => $deposit, 'show_currency_code' => 'USD',
                'vat_data' => ['included' => false, 'applied' => false], 'tax_data' => [],
            ]]],
        ];
    }

    /** Puts a prebook review in the cache exactly as HotelRoomsApiController::prebook does. */
    protected function review(User $user, string $companyId, array $overrides = []): string
    {
        $token = bin2hex(random_bytes(24));
        Cache::put('b2b_ratehawk_review:' . hash('sha256', $token), array_replace([
            'environment' => 'sandbox',
            'user_id' => $user->id,
            'company_id' => $companyId,
            'hid' => 123456,
            'search' => [
                'checkin' => now()->addDays(30)->toDateString(),
                'checkout' => now()->addDays(32)->toDateString(),
                'guests' => [['adults' => 2, 'children' => [7]]],
            ],
            'rate' => $this->prebookRate(),
            'price' => [
                'verified' => true, 'selling_price' => self::SELLING, 'agency_purchase_price' => self::PURCHASE,
                'currency' => 'USD', 'price_on_request' => false,
            ],
            'price_changed' => false,
            'hotelpage_book_hash' => 'h-fixture-hotelpage-hash',
            'prebook_verified_at' => now()->toIso8601String(),
        ], $overrides), now()->addMinutes(10));

        return $token;
    }

    protected function draftBody(string $reviewToken, array $overrides = []): array
    {
        return array_replace([
            'review_token' => $reviewToken,
            'customer' => ['first_name' => 'Mona', 'last_name' => 'Hassan', 'email' => 'mona@example.com'],
            'contact_email' => 'agent@example.com',
            'contact_phone' => '+201000000001',
            'guests' => [
                ['first_name' => 'Jane', 'last_name' => 'Doe', 'guest_type' => 'adult', 'is_lead' => true, 'room_index' => 0, 'gender' => 'female'],
                ['first_name' => 'John', 'last_name' => 'Doe', 'guest_type' => 'adult', 'is_lead' => false, 'room_index' => 0],
                ['first_name' => 'Kid', 'last_name' => 'Doe', 'guest_type' => 'child', 'is_lead' => false, 'room_index' => 0, 'age' => 7],
            ],
        ], $overrides);
    }

    /** Creates a draft through the real API and asserts deferred DB constraints accept it. */
    protected function createDraft(User $user, string $token, string $companyId): string
    {
        $this->supplierId();
        $response = $this->withToken($token)->withHeader('Idempotency-Key', 'draft-' . Str::random(20))
            ->postJson('/api/v1/b2b/bookings/draft', $this->draftBody($this->review($user, $companyId)));
        $response->assertCreated();
        $this->assertDeferredConstraintsHold();
        $this->app['auth']->forgetGuards();

        return (string) $response->json('booking_id');
    }

    /** Fires deferred constraint triggers now (they would otherwise only run at COMMIT). */
    protected function assertDeferredConstraintsHold(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->addToAssertionCount(1);
    }

    protected function forbidSupplier(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');
        $this->app->instance(RateHawkClient::class, $client);
    }

    /**
     * Scripted supplier: $script maps an API path suffix (form|finish|status) to a
     * list of responses (arrays) or Throwables, consumed in order. Records calls.
     */
    protected function fakeSupplier(array $script): object
    {
        $recorder = new class {
            public array $calls = [];
        };
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->andReturnUsing(function (string $path, array $payload, int $timeout = 30) use (&$script, $recorder) {
            $kind = match (true) {
                str_ends_with($path, '/booking/form/') => 'form',
                str_ends_with($path, '/booking/finish/') => 'finish',
                str_ends_with($path, '/booking/finish/status/') => 'status',
                default => 'other',
            };
            $recorder->calls[] = [$kind, $payload];
            $next = array_shift($script[$kind]);
            if ($next instanceof \Throwable) {
                throw $next;
            }
            if ($next === 'echo-form') {
                return ['status' => 'ok', 'error' => null, 'data' => [
                    'order_id' => 555001, 'partner_order_id' => $payload['partner_order_id'],
                    'payment_types' => [['type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD', 'is_need_credit_card_data' => false]],
                    'is_gender_specification_required' => false,
                ]];
            }
            if ($next === null) {
                throw new \LogicException("Unexpected supplier call: {$kind}");
            }

            return $next;
        });
        $this->app->instance(RateHawkClient::class, $client);

        return $recorder;
    }

    protected function wallet(string $companyId): object
    {
        return DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
    }

    protected function attemptState(string $bookingId): ?string
    {
        return DB::table('rt_supplier_booking_attempts')->where('booking_id', $bookingId)->value('state');
    }
}
