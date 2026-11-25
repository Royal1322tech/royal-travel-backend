<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\B2BBookingApiFixtures;
use Tests\TestCase;

/** Admin pages and booking sandbox actions changed in the 2026-11-25 release. Supplier mocked. */
final class AdminReleasePagesTest extends TestCase
{
    use DatabaseTransactions;
    use B2BBookingApiFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpB2BFixtures();
        $this->withoutVite();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('rt_user_roles')->insert([
            'user_id' => $user->id, 'company_id' => null,
            'role_id' => DB::table('rt_roles')->where('code', 'super_admin')->where('portal', 'admin')->value('id'),
        ]);

        return $user;
    }

    private function adminWith(array $permissions): User
    {
        $user = User::factory()->create();
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $roleId = (string) Str::uuid();
        DB::table('rt_roles')->insert(['id' => $roleId, 'code' => 'release_test_' . Str::random(10), 'portal' => 'admin']);
        foreach ($permissions as $code) {
            DB::table('rt_role_permissions')->insert(['role_id' => $roleId, 'permission_id' => DB::table('rt_permissions')->where('code', $code)->value('id')]);
        }
        DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'company_id' => null]);

        return $user;
    }

    private function asAdmin(User $user): self
    {
        return $this->withSession(['auth_portal' => 'admin'])->actingAs($user);
    }

    /** A sandbox Admin B2C hotel draft with full booking context (as AdminHotelBookingDraftService writes it). */
    private function b2cDraft(): string
    {
        $supplierId = $this->supplierId();
        $creator = User::factory()->create();
        $orderId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        DB::table('rt_orders')->insert(['id' => $orderId, 'order_no' => 'ORD-' . Str::upper(Str::random(12)), 'channel' => 'b2c',
            'company_id' => null, 'customer_user_id' => null, 'created_by' => $creator->id, 'status' => 'draft', 'currency' => 'USD']);
        DB::table('rt_bookings')->insert(['id' => $bookingId, 'order_id' => $orderId, 'booking_no' => 'HTL-' . Str::upper(Str::random(12)),
            'service_type' => 'hotel', 'channel' => 'b2c', 'company_id' => null, 'customer_user_id' => null,
            'supplier_id' => $supplierId, 'status' => 'draft', 'currency' => 'USD', 'approved_total' => null]);
        DB::table('rt_hotel_booking_details')->insert(['booking_id' => $bookingId, 'hotel_id' => null,
            'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(),
            'rooms_count' => 1, 'supplier_offer_ref' => 'p-fixture-prebook-hash']);
        DB::table('rt_ratehawk_booking_offer_contexts')->insert(['booking_id' => $bookingId, 'environment' => 'sandbox', 'hid' => 123456,
            'hotelpage_book_hash' => 'h-fixture-hotelpage-hash', 'prebook_hash' => 'p-fixture-prebook-hash',
            'prebook_verified_at' => now(), 'currency' => 'USD', 'supplier_cost' => self::SUPPLIER_COST, 'price_changed' => false,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('rt_booking_supplier_contacts')->insert(['booking_id' => $bookingId,
            'email_encrypted' => Crypt::encryptString('guest@example.com'), 'phone_encrypted' => Crypt::encryptString('+201000000002'),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('rt_booking_guests')->insert(['id' => (string) Str::uuid(), 'booking_id' => $bookingId, 'first_name' => 'Jane',
            'last_name' => 'Doe', 'guest_type' => 'adult', 'is_lead' => true, 'room_index' => 0]);
        DB::table('rt_booking_price_snapshots')->insert(['id' => (string) Str::uuid(), 'booking_id' => $bookingId, 'version' => 1,
            'currency' => 'USD', 'supplier_cost' => self::SUPPLIER_COST, 'discount_amount' => 0, 'fee_amount' => 0, 'tax_amount' => 0,
            'customer_total' => self::SELLING, 'created_at' => now()]);

        return $bookingId;
    }

    public function test_changed_admin_pages_render_for_super_admin(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $b2bBooking = $this->createDraft($user, $token, $companyId);
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $admin = $this->superAdmin();

        foreach ([
            route('admin.dashboard'),
            route('admin.bookings.index'),
            route('admin.bookings.show', $b2bBooking),
            route('admin.companies.index'),
            route('admin.companies.show', $companyId),
            route('admin.companies.finance.index'),
            route('admin.companies.finance.index', ['search' => 'Fixture', 'credit_status' => 'active']),
            route('admin.companies.finance.show', $companyId),
            route('admin.loyalty.index'),
            route('admin.support.index'),
            route('admin.hotels.index'),
        ] as $url) {
            $html = (string) $this->asAdmin($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('test-api-key', $html, $url);
            $this->assertStringNotContainsString('p-fixture-prebook-hash', $html, $url);
            $this->assertStringContainsString(route('admin.support.index'), $html, "Sidebar link missing on {$url}");
        }
    }

    public function test_bookings_manage_permission_is_registered_and_granted_to_super_admin_only_by_migration(): void
    {
        $permissionId = DB::table('rt_permissions')->where('code', 'bookings.manage')->value('id');
        $this->assertNotNull($permissionId);
        $this->assertTrue(DB::table('rt_role_permissions as rp')->join('rt_roles as r', 'r.id', '=', 'rp.role_id')
            ->where('rp.permission_id', $permissionId)->where('r.code', 'super_admin')->exists());
        $this->assertFalse(DB::table('rt_role_permissions as rp')->join('rt_roles as r', 'r.id', '=', 'rp.role_id')
            ->where('rp.permission_id', $permissionId)->where('r.portal', 'b2b')->exists());

        // Re-running the migration is a no-op (idempotent, additive).
        $before = DB::table('rt_role_permissions')->count();
        (require base_path('database/migrations/2026_11_25_000001_register_bookings_manage_permission.php'))->up();
        $this->assertSame($before, DB::table('rt_role_permissions')->count());
        $this->assertSame(1, DB::table('rt_permissions')->where('code', 'bookings.manage')->count());
    }

    public function test_sandbox_actions_require_bookings_manage_and_eligible_booking(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $b2bBooking = $this->createDraft($user, $token, $companyId);
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $bookingId = $this->b2cDraft();
        $this->get(route('admin.bookings.show', $bookingId))->assertRedirect(route('admin.login'));
        $viewer = $this->adminWith(['bookings.view']);
        foreach (['prepare', 'finish', 'reconcile'] as $action) {
            $this->asAdmin($viewer)->post(route("admin.bookings.sandbox.{$action}", $bookingId))->assertForbidden();
        }

        // A B2B company booking is never eligible for the Admin B2C sandbox actions.
        $this->asAdmin($this->superAdmin())->post(route('admin.bookings.sandbox.prepare', $b2bBooking))->assertStatus(409);
        $this->assertNull($this->attemptState($b2bBooking));
    }

    public function test_sandbox_prepare_finish_reconcile_through_admin_http(): void
    {
        $bookingId = $this->b2cDraft();
        $admin = $this->superAdmin();
        $supplier = $this->fakeSupplier(['form' => ['echo-form'], 'finish' => [['status' => 'ok', 'error' => null, 'data' => null]],
            'status' => [['status' => 'ok', 'error' => null, 'data' => null]]]);

        $this->asAdmin($admin)->post(route('admin.bookings.sandbox.prepare', $bookingId))
            ->assertRedirect(route('admin.bookings.show', $bookingId))->assertSessionHasNoErrors();
        $this->assertSame('form_ready', $this->attemptState($bookingId));
        $this->asAdmin($admin)->get(route('admin.bookings.show', $bookingId))->assertOk()->assertSee('form ready')->assertDontSee('guest@example.com');

        // Finish needs explicit approval and the exact previewed supplier cost.
        $this->asAdmin($admin)->post(route('admin.bookings.sandbox.finish', $bookingId), ['supplier_cost' => self::SUPPLIER_COST])
            ->assertSessionHasErrors('approve_sandbox');
        $this->asAdmin($admin)->post(route('admin.bookings.sandbox.finish', $bookingId), ['approve_sandbox' => '1', 'supplier_cost' => '41.99'])
            ->assertSessionHasErrors('booking');
        $this->assertSame('form_ready', $this->attemptState($bookingId));
        $this->asAdmin($admin)->post(route('admin.bookings.sandbox.finish', $bookingId), ['approve_sandbox' => '1', 'supplier_cost' => self::SUPPLIER_COST])
            ->assertSessionHasNoErrors();
        $this->assertSame('finish_unknown', $this->attemptState($bookingId));
        $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));

        $this->asAdmin($admin)->post(route('admin.bookings.sandbox.reconcile', $bookingId))->assertSessionHas('notice', 'Supplier status: ok');
        $this->assertSame('confirmed', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        $this->assertSame(['form', 'finish', 'status'], array_column($supplier->calls, 0));
    }

    public function test_sandbox_finish_is_blocked_when_flag_disabled(): void
    {
        $bookingId = $this->b2cDraft();
        config(['services.ratehawk.allow_sandbox_finish' => false]);
        $this->asAdmin($this->superAdmin())->post(route('admin.bookings.sandbox.finish', $bookingId), ['approve_sandbox' => '1', 'supplier_cost' => self::SUPPLIER_COST])
            ->assertForbidden();
    }

    public function test_legacy_admin_confirm_route_is_a_safe_stub(): void
    {
        $this->asAdmin($this->superAdmin())->post('/admin/hotels/rooms/review/' . bin2hex(random_bytes(24)) . '/confirm')->assertStatus(409);
    }
}
