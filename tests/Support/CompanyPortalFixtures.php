<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Self-contained Company Portal fixtures (use with DatabaseTransactions).
 * Every company gets an owner (verified company_admin + primary contact), and
 * helpers add employees, customers, hotel bookings and finance accounts.
 */
trait CompanyPortalFixtures
{
    protected const OWNER_PASSWORD = 'Owner-Password-123!';

    protected function setUpPortal(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        if (!app()->environment('testing') || preg_match('/(^|_)test$/D', $database) !== 1) {
            $this->fail('Refusing to run outside an isolated *_test database.');
        }
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();
        $this->withoutVite();
    }

    /**
     * pricing.company.manage is registered without role grants in production ("assign
     * explicitly after review"). Tests that exercise markup grant it explicitly.
     */
    protected function grantCompanyPricingToCompanyAdmin(): void
    {
        DB::table('rt_role_permissions')->insertOrIgnore([
            'role_id' => DB::table('rt_roles')->where('code', 'company_admin')->where('portal', 'b2b')->value('id'),
            'permission_id' => DB::table('rt_permissions')->where('code', 'pricing.company.manage')->value('id'),
        ]);
    }

    protected function portalCompany(string $status = 'approved', ?string $name = null, int $walletCents = 125050): string
    {
        $id = (string) Str::uuid();
        DB::table('rt_companies')->insert([
            'id' => $id, 'legal_name' => $name ?? 'Portal Co ' . substr($id, 0, 6), 'display_name' => $name,
            'status' => $status, 'account_currency' => 'USD', 'registration_no' => 'REG-' . substr($id, 0, 8),
        ]);
        DB::table('rt_b2b_finance_accounts')->insert([
            'company_id' => $id, 'currency' => 'USD', 'wallet_cents' => $walletCents, 'wallet_reserved_cents' => 0,
            'credit_limit_cents' => 500000, 'credit_used_cents' => 12345, 'credit_frozen' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    protected function portalUser(string $email, bool $verified = true, string $password = self::OWNER_PASSWORD): User
    {
        return User::factory()->create([
            'email' => strtolower($email),
            'password' => Hash::make($password),
            'email_verified_at' => $verified ? now() : null,
            'must_change_password' => false,
        ]);
    }

    protected function addMembership(User $user, string $companyId, string $role, string $status = 'active', bool $approved = true): void
    {
        DB::table('rt_company_memberships')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id, 'role_code' => $role,
            'status' => $status, 'approved_by' => $approved ? $user->id : null, 'approved_at' => $approved ? now() : null,
        ]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id, 'company_id' => $companyId,
            'role_id' => DB::table('rt_roles')->where('code', $role)->where('portal', 'b2b')->value('id'),
        ]);
    }

    protected function addPrimaryContact(User $user, string $companyId, string $type = 'primary'): void
    {
        DB::table('rt_company_contacts')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $user->id,
            'full_name' => $user->name, 'phone' => '+201000000009', 'nationality' => 'Egyptian',
            'id_number_encrypted' => Crypt::encryptString('29001011234567'), 'contact_type' => $type,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** An eligible owner: verified, active approved company_admin, primary contact. */
    protected function owner(string $companyId, ?string $email = null): User
    {
        $user = $this->portalUser($email ?? 'owner-' . Str::lower(Str::random(10)) . '@example.test');
        $this->addMembership($user, $companyId, 'company_admin');
        $this->addPrimaryContact($user, $companyId);

        return $user;
    }

    protected function employee(string $companyId, ?string $name = null): User
    {
        $user = $this->portalUser('emp-' . Str::lower(Str::random(10)) . '@example.test');
        if ($name !== null) {
            $user->forceFill(['name' => $name])->save();
        }
        $this->addMembership($user, $companyId, 'company_employee');

        return $user;
    }

    /** Signs the owner in through the real portal login endpoint. */
    protected function loginOwner(User $owner, ?string $companyId = null): void
    {
        $this->post(route('company-portal.login.submit'), array_filter([
            'email' => $owner->email, 'password' => self::OWNER_PASSWORD, 'company_id' => $companyId,
        ]))->assertRedirect(route('company-portal.dashboard'));
    }

    protected function customer(string $companyId, int $createdBy, string $first = 'Mona', string $last = 'Hassan', ?string $email = null, ?string $phone = null): string
    {
        $id = (string) Str::uuid();
        DB::table('rt_customers')->insert([
            'id' => $id, 'company_id' => $companyId, 'first_name' => $first, 'last_name' => $last,
            'email' => $email, 'phone' => $phone, 'created_by' => $createdBy, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    protected function hotel(string $name, string $city = 'Dubai', string $country = 'AE'): string
    {
        $id = (string) Str::uuid();
        DB::table('rt_hotels')->insert(['id' => $id, 'name_en' => $name, 'city_name' => $city, 'country_code' => $country]);

        return $id;
    }

    /** A B2B hotel booking (order + booking + details + guests). Deferred triggers are not exercised. */
    protected function booking(string $companyId, int $createdBy, ?string $customerId, string $status = 'confirmed', array $opts = []): string
    {
        $orderId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        $created = $opts['created_at'] ?? now();
        DB::table('rt_orders')->insert([
            'id' => $orderId, 'order_no' => $opts['order_no'] ?? 'ORD-' . Str::upper(Str::random(12)), 'channel' => 'b2b',
            'company_id' => $companyId, 'customer_id' => $customerId, 'customer_user_id' => null,
            'created_by' => $createdBy, 'status' => $status, 'currency' => 'USD', 'created_at' => $created, 'updated_at' => $created,
        ]);
        DB::table('rt_bookings')->insert([
            'id' => $bookingId, 'order_id' => $orderId, 'booking_no' => $opts['booking_no'] ?? 'HTL-' . Str::upper(Str::random(12)),
            'service_type' => 'hotel', 'channel' => 'b2b', 'company_id' => $companyId, 'customer_id' => $customerId,
            'customer_user_id' => null, 'status' => $status, 'currency' => 'USD',
            'approved_total' => null, 'confirmed_at' => $status === 'confirmed' ? $created : null,
            'created_at' => $created, 'updated_at' => $created,
        ]);
        DB::table('rt_hotel_booking_details')->insert([
            'booking_id' => $bookingId, 'hotel_id' => $opts['hotel_id'] ?? null,
            'check_in' => $checkIn = $opts['check_in'] ?? now()->addDays(20)->toDateString(),
            'check_out' => $opts['check_out'] ?? \Illuminate\Support\Carbon::parse($checkIn)->addDays(2)->toDateString(),
            'rooms_count' => 1, 'supplier_offer_ref' => 'p-secret-offer-ref',
        ]);
        foreach ($opts['guests'] ?? [['Jane', 'Doe', true, 0], ['John', 'Doe', false, 0]] as [$first, $last, $lead, $room]) {
            DB::table('rt_booking_guests')->insert([
                'id' => (string) Str::uuid(), 'booking_id' => $bookingId, 'first_name' => $first, 'last_name' => $last,
                'guest_type' => 'adult', 'is_lead' => $lead, 'room_index' => $room,
            ]);
        }

        return $bookingId;
    }
}
