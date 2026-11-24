<?php

declare(strict_types=1);

namespace Tests\Feature\Hotels;

use App\Models\User;
use App\Services\Bookings\AdminHotelBookingDraftService;
use App\Services\Hotels\AdminRateHawkBookingConfirmationService;
use App\Services\Hotels\AdminRateHawkBookingFinishService;
use App\Services\Pricing\HotelPricingService;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\RoyalBookingFixtures;
use Tests\TestCase;

/**
 * Admin (B2C) RateHawk sandbox booking flow:
 * Hotelpage + Prebook cache -> draft -> prepare -> booking form -> finish -> reconcile.
 *
 * Every supplier HTTP call is MOCKED. These tests prove local state handling,
 * validation and payload mapping only; they do not prove a real RateHawk booking.
 */
final class AdminRateHawkBookingFlowTest extends TestCase
{
    use RoyalBookingFixtures;

    private const SESSION_ID = 'admin-flow-session';
    private const SUPPLIER_COST = '42.00';
    private const SELLING_PRICE = '55.00';
    private const ORDER_ID = 987654;
    private const USER_IP = '203.0.113.5';

    private string $searchToken;
    private string $prebookToken;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ratehawk.environment' => 'sandbox',
            'services.ratehawk.sandbox_url' => 'https://api-sandbox.ratehawk.com',
            'services.ratehawk.allow_sandbox_finish' => true,
            'services.ratehawk.key_id' => 'test-key-id',
            'services.ratehawk.api_key' => 'test-api-key',
        ]);
        $this->searchToken = Str::random(48);
        $this->prebookToken = bin2hex(random_bytes(24));
    }

    // ------------------------------------------------------------------ draft

    public function test_draft_persists_verified_offer_context_guests_contact_and_snapshot(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $draft = $this->createDraft($userId);

            $booking = DB::table('rt_bookings')->where('id', $draft['booking_id'])->first();
            $this->assertSame('b2c', $booking->channel);
            $this->assertNull($booking->company_id);
            $this->assertSame('draft', $booking->status);
            $this->assertSame('USD', trim((string) $booking->currency));
            $this->assertSame($this->rateHawkSupplierId(), (string) $booking->supplier_id);
            $this->assertNull($booking->approved_total);

            $details = DB::table('rt_hotel_booking_details')->where('booking_id', $draft['booking_id'])->first();
            $this->assertSame('p-prebook-hash-1', $details->supplier_offer_ref);
            $this->assertSame(1, (int) $details->rooms_count);

            $offer = DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $draft['booking_id'])->first();
            $this->assertSame('sandbox', $offer->environment);
            $this->assertSame(123456, (int) $offer->hid);
            $this->assertSame('h-hotelpage-hash-1', $offer->hotelpage_book_hash);
            $this->assertSame('p-prebook-hash-1', $offer->prebook_hash);
            $this->assertSame(0, bccomp((string) $offer->supplier_cost, self::SUPPLIER_COST, 6));
            $this->assertFalse((bool) $offer->price_changed);

            $contact = DB::table('rt_booking_supplier_contacts')->where('booking_id', $draft['booking_id'])->first();
            $this->assertStringNotContainsString('lead@example.com', $contact->email_encrypted);
            $this->assertSame('lead@example.com', Crypt::decryptString($contact->email_encrypted));
            $this->assertSame('+201000000001', Crypt::decryptString($contact->phone_encrypted));

            $guests = DB::table('rt_booking_guests')->where('booking_id', $draft['booking_id'])
                ->orderByDesc('is_lead')->orderBy('guest_type')->orderBy('first_name')->get();
            $this->assertCount(3, $guests);
            $this->assertSame(['Jane', 'adult', true, 'female', null], [$guests[0]->first_name, $guests[0]->guest_type, (bool) $guests[0]->is_lead, $guests[0]->gender, $guests[0]->age]);
            $this->assertSame(['John', 'adult', false, null], [$guests[1]->first_name, $guests[1]->guest_type, (bool) $guests[1]->is_lead, $guests[1]->gender]);
            $this->assertSame(['Kid', 'child', 7], [$guests[2]->first_name, $guests[2]->guest_type, (int) $guests[2]->age]);
            foreach ($guests as $guest) {
                $this->assertSame(0, (int) $guest->room_index);
            }

            $snapshot = DB::table('rt_booking_price_snapshots')->where('booking_id', $draft['booking_id'])->first();
            $this->assertSame(0, bccomp((string) $snapshot->supplier_cost, self::SUPPLIER_COST, 6));
            $this->assertSame(0, bccomp((string) $snapshot->customer_total, self::SELLING_PRICE, 6));
            $this->assertSame('USD', trim((string) $snapshot->currency));
            $this->assertFalse($draft['payment_collected']);
            $this->assertFalse($draft['supplier_booking_created']);
        });
    }

    public function test_draft_is_idempotent_per_prebook_token(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $first = $this->createDraft($userId);
            $second = app(AdminHotelBookingDraftService::class)
                ->create($userId, $this->prebookToken, self::SESSION_ID, $this->draftData());
            $this->assertSame($first['booking_id'], $second['booking_id']);
            $this->assertSame(1, DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $first['booking_id'])->count());

            $changed = $this->draftData();
            $changed['contact_email'] = 'other@example.com';
            $this->expectException(HttpException::class);
            app(AdminHotelBookingDraftService::class)->create($userId, $this->prebookToken, self::SESSION_ID, $changed);
        });
    }

    public function test_draft_rejects_hotelpage_hash_mismatch(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $this->putCaches(['hotelpage_book_hash' => 'h-some-other-rate']);
            $this->expectDraftHttpError($userId, 409);
        });
    }

    public function test_draft_rejects_stale_prebook(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $this->putCaches(['prebook_verified_at' => now()->subMinutes(11)->toIso8601String()]);
            $this->expectDraftHttpError($userId, 410);
        });
    }

    public function test_draft_rejects_other_session(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $this->putCaches();
            $this->configureDirectPricing();
            try {
                app(AdminHotelBookingDraftService::class)->create($userId, $this->prebookToken, 'another-session', $this->draftData());
                $this->fail('Expected another session to be rejected.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
            $this->assertSame(0, DB::table('rt_ratehawk_booking_offer_contexts')->count() - $this->baselineOfferCount);
        });
    }

    public function test_draft_rejects_supplier_invalid_guest_name_before_any_booking_is_created(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $this->putCaches();
            $data = $this->draftData();
            $data['rooms'][0]['guests'][1]['first_name'] = 'J0hn';
            $before = DB::table('rt_bookings')->count();
            try {
                app(AdminHotelBookingDraftService::class)->create($userId, $this->prebookToken, self::SESSION_ID, $data);
                $this->fail('Expected an invalid supplier guest name to be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('rooms', $e->errors());
            }
            $this->assertSame($before, DB::table('rt_bookings')->count());
        });
    }

    public function test_company_draft_quotes_with_company_id(): void
    {
        $this->inRollbackTransaction(function (): void {
            $userId = $this->createTestUser();
            $companyId = $this->createCompanyWithWallet(50000, $userId);
            $this->rateHawkSupplierId();
            $this->putCaches();
            // Before the fix this path always threw (quote called without company id).
            $pricing = app(HotelPricingService::class);
            $pricing->saveRule('royal_b2b', $companyId, '10', '0');
            $pricing->saveRule('company_b2b', $companyId, '0', '8.80');
            $data = $this->draftData();
            $data['company_id'] = $companyId;
            $draft = app(AdminHotelBookingDraftService::class)->create($userId, $this->prebookToken, self::SESSION_ID, $data);
            $booking = DB::table('rt_bookings')->where('id', $draft['booking_id'])->first();
            $this->assertSame('b2b', $booking->channel);
            $this->assertSame($companyId, (string) $booking->company_id);
            $snapshot = DB::table('rt_booking_price_snapshots')->where('booking_id', $draft['booking_id'])->first();
            $this->assertSame(0, bccomp((string) $snapshot->customer_total, self::SELLING_PRICE, 6));
            // A company booking is not an Admin B2C sandbox booking.
            $this->expectException(RuntimeException::class);
            app(AdminRateHawkBookingConfirmationService::class)->prepare($draft['booking_id']);
        });
    }

    // ------------------------------------------------------- prepare / form

    public function test_prepare_is_idempotent_and_creates_one_attempt_without_money(): void
    {
        $this->inRollbackTransaction(function (): void {
            $draft = $this->createDraft($this->createTestUser());
            $service = app(AdminRateHawkBookingConfirmationService::class);
            $first = $service->prepare($draft['booking_id']);
            $second = $service->prepare($draft['booking_id']);
            $this->assertSame($first['attempt_id'], $second['attempt_id']);
            $this->assertSame('prepared', $second['state']);
            $this->assertTrue(Str::isUuid($first['partner_order_id']));
            $this->assertSame(1, DB::table('rt_supplier_booking_attempts')->where('booking_id', $draft['booking_id'])->count());
            $this->assertSame(0, DB::table('rt_financial_reservations')->where('booking_id', $draft['booking_id'])->count());
        });
    }

    public function test_create_form_stores_encrypted_form_and_order_reference_once(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking();
            $this->assertSame('form_ready', $this->attemptState($attempt['attempt_id']));
            $stored = DB::table('rt_ratehawk_booking_forms')->where('attempt_id', $attempt['attempt_id'])->first();
            $this->assertStringNotContainsString('deposit', $stored->response_encrypted);
            $form = json_decode(Crypt::decryptString($stored->response_encrypted), true);
            $this->assertSame(self::ORDER_ID, $form['order_id']);
            $this->assertSame(1, DB::table('rt_supplier_external_refs')->where('attempt_id', $attempt['attempt_id'])
                ->where('reference_kind', 'ratehawk_order_id')->where('reference_value', (string) self::ORDER_ID)->count());

            $this->forbidSupplierCalls();
            try {
                app(AdminRateHawkBookingConfirmationService::class)->createForm($bookingId, self::USER_IP);
                $this->fail('Expected a second booking form request to be blocked.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Do not retry', $e->getMessage());
            }
        });
    }

    public function test_create_form_timeout_leaves_form_unknown_and_blocks_retry(): void
    {
        $this->inRollbackTransaction(function (): void {
            $draft = $this->createDraft($this->createTestUser());
            $attempt = app(AdminRateHawkBookingConfirmationService::class)->prepare($draft['booking_id']);
            $client = Mockery::mock(RateHawkClient::class);
            $client->shouldReceive('post')->once()->andThrow(new RuntimeException('Simulated timeout'));
            $this->app->instance(RateHawkClient::class, $client);
            try {
                app(AdminRateHawkBookingConfirmationService::class)->createForm($draft['booking_id'], self::USER_IP);
                $this->fail('Expected an uncertain form outcome.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('uncertain', $e->getMessage());
            }
            $this->assertSame('form_unknown', $this->attemptState($attempt['attempt_id']));
            $this->forbidSupplierCalls();
            $this->expectException(RuntimeException::class);
            app(AdminRateHawkBookingConfirmationService::class)->createForm($draft['booking_id'], self::USER_IP);
        });
    }

    public function test_create_form_rejects_price_changed_and_stale_offer_before_http(): void
    {
        $this->inRollbackTransaction(function (): void {
            $draft = $this->createDraft($this->createTestUser());
            $attempt = app(AdminRateHawkBookingConfirmationService::class)->prepare($draft['booking_id']);
            $this->forbidSupplierCalls();

            DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $draft['booking_id'])
                ->update(['price_changed' => true]);
            $this->assertCreateFormRejected($draft['booking_id'], 'inconsistent');

            DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $draft['booking_id'])
                ->update(['price_changed' => false, 'prebook_verified_at' => now()->subMinutes(11)]);
            $this->assertCreateFormRejected($draft['booking_id'], 'stale');

            $this->assertSame('prepared', $this->attemptState($attempt['attempt_id']));
        });
    }

    public function test_create_form_requires_sandbox(): void
    {
        $this->inRollbackTransaction(function (): void {
            $draft = $this->createDraft($this->createTestUser());
            app(AdminRateHawkBookingConfirmationService::class)->prepare($draft['booking_id']);
            $this->forbidSupplierCalls();
            config(['services.ratehawk.environment' => 'production']);
            $this->assertCreateFormRejected($draft['booking_id'], 'Sandbox');
        });
    }

    // ------------------------------------------------------------- finish

    public function test_finish_sends_exact_mapped_payload_once_and_does_not_confirm(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking();
            $preview = app(AdminRateHawkBookingFinishService::class)->preview($bookingId);
            $this->assertSame(['room_count' => 1, 'guest_count' => 3, 'finish_enabled' => true],
                ['room_count' => $preview['room_count'], 'guest_count' => $preview['guest_count'], 'finish_enabled' => $preview['finish_enabled']]);
            $this->assertArrayNotHasKey('email', $preview);

            $client = Mockery::mock(RateHawkClient::class);
            $client->shouldReceive('post')->once()->withArgs(function (string $path, array $payload, int $timeout) use ($attempt): bool {
                $guests = $payload['rooms'][0]['guests'] ?? [];
                return $path === '/api/b2b/v3/hotel/order/booking/finish/'
                    && $timeout === 60
                    && $payload['partner'] === ['partner_order_id' => $attempt['partner_order_id']]
                    && $payload['payment_type'] === ['type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD']
                    && $payload['user'] === ['email' => 'lead@example.com', 'phone' => '+201000000001']
                    && count($payload['rooms']) === 1
                    && $guests[0] === ['first_name' => 'Jane', 'last_name' => 'Doe', 'is_child' => false, 'gender' => 'female']
                    && $guests[1] === ['first_name' => 'John', 'last_name' => 'Doe', 'is_child' => false]
                    && $guests[2] === ['first_name' => 'Kid', 'last_name' => 'Doe', 'is_child' => true, 'age' => 7];
            })->andReturn(['status' => 'ok', 'error' => null, 'data' => null]);
            $this->app->instance(RateHawkClient::class, $client);

            $result = app(AdminRateHawkBookingFinishService::class)->finish($bookingId);
            $this->assertSame('finish_unknown', $result['state']);
            $this->assertSame('finish_unknown', $this->attemptState($attempt['attempt_id']));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));

            $this->forbidSupplierCalls();
            try {
                app(AdminRateHawkBookingFinishService::class)->finish($bookingId);
                $this->fail('Expected repeated Finish to be blocked.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('already claimed', $e->getMessage());
            }
        });
    }

    public function test_finish_disabled_by_default_flag(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking();
            config(['services.ratehawk.allow_sandbox_finish' => false]);
            $this->forbidSupplierCalls();
            $this->assertFinishRejectedBeforeClaim($bookingId, $attempt['attempt_id'], 'disabled');
        });
    }

    public function test_finish_rejects_deposit_that_differs_from_verified_cost(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking(['payment_types' => [
                ['type' => 'deposit', 'amount' => '43.00', 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
            ]]);
            $this->forbidSupplierCalls();
            $this->assertFinishRejectedBeforeClaim($bookingId, $attempt['attempt_id'], 'deposit differs');
        });
    }

    public function test_finish_validates_the_deposit_option_the_adapter_actually_submits(): void
    {
        $this->inRollbackTransaction(function (): void {
            // The numeric first option is NOT submittable; the adapter would send 99.00.
            [$bookingId, $attempt] = $this->formReadyBooking(['payment_types' => [
                ['type' => 'deposit', 'amount' => 42, 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
                ['type' => 'deposit', 'amount' => '99.00', 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
            ]]);
            $this->forbidSupplierCalls();
            $this->assertFinishRejectedBeforeClaim($bookingId, $attempt['attempt_id'], 'deposit differs');
        });
    }

    public function test_finish_rejects_supplier_invalid_guest_name_without_claiming(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking();
            DB::table('rt_booking_guests')->where('booking_id', $bookingId)->where('first_name', 'John')
                ->update(['first_name' => 'J0hn']);
            $this->forbidSupplierCalls();
            $this->assertFinishRejectedBeforeClaim($bookingId, $attempt['attempt_id'], 'Invalid booking finish input');
        });
    }

    public function test_finish_requires_gender_when_supplier_form_requires_it(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking(['is_gender_specification_required' => true]);
            $this->forbidSupplierCalls();
            $this->assertFinishRejectedBeforeClaim($bookingId, $attempt['attempt_id'], 'gender');
        });
    }

    public function test_finish_rejects_stale_prebook_without_claiming(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking();
            DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)
                ->update(['prebook_verified_at' => now()->subMinutes(11)]);
            $this->forbidSupplierCalls();
            $this->assertFinishRejectedBeforeClaim($bookingId, $attempt['attempt_id'], 'stale');
        });
    }

    public function test_finish_transport_failure_is_unknown_and_never_replayed(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->formReadyBooking();
            $client = Mockery::mock(RateHawkClient::class);
            $client->shouldReceive('post')->once()->andThrow(new RuntimeException('Simulated timeout'));
            $this->app->instance(RateHawkClient::class, $client);
            try {
                app(AdminRateHawkBookingFinishService::class)->finish($bookingId);
                $this->fail('Expected unknown Finish outcome.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('UNKNOWN', $e->getMessage());
            }
            $this->assertSame('finish_unknown', $this->attemptState($attempt['attempt_id']));
            $this->assertSame('RuntimeException', DB::table('rt_supplier_booking_attempts')
                ->where('id', $attempt['attempt_id'])->value('last_error_code'));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        });
    }

    // ---------------------------------------------------------- reconcile

    public function test_reconcile_processing_then_ok_confirms_once(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->finishUnknownBooking();
            $this->fakeStatus('processing', $attempt['partner_order_id']);
            $this->assertSame('processing', app(AdminRateHawkBookingFinishService::class)->reconcile($bookingId)['status']);
            $this->assertSame('processing', $this->attemptState($attempt['attempt_id']));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));

            $this->fakeStatus('ok', $attempt['partner_order_id']);
            $this->assertSame('ok', app(AdminRateHawkBookingFinishService::class)->reconcile($bookingId)['status']);
            $this->assertSame('confirmed', $this->attemptState($attempt['attempt_id']));
            $booking = DB::table('rt_bookings')->where('id', $bookingId)->first();
            $this->assertSame('confirmed', $booking->status);
            $this->assertNotNull($booking->confirmed_at);

            // Already confirmed: no further supplier call, still ok.
            $this->forbidSupplierCalls();
            $this->assertSame('ok', app(AdminRateHawkBookingFinishService::class)->reconcile($bookingId)['status']);
            $this->assertSame(0, DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->count());
        });
    }

    public function test_reconcile_supplier_error_and_timeout_do_not_change_booking(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId, $attempt] = $this->finishUnknownBooking();
            $this->fakeStatus('error', $attempt['partner_order_id']);
            $result = app(AdminRateHawkBookingFinishService::class)->reconcile($bookingId);
            $this->assertSame('supplier_error_review', $result['status']);
            $this->assertSame('finish_unknown', $this->attemptState($attempt['attempt_id']));

            $client = Mockery::mock(RateHawkClient::class);
            $client->shouldReceive('post')->once()->andThrow(new RuntimeException('Simulated timeout'));
            $this->app->instance(RateHawkClient::class, $client);
            $this->assertSame('unknown', app(AdminRateHawkBookingFinishService::class)->reconcile($bookingId)['status']);
            $this->assertSame('finish_unknown', $this->attemptState($attempt['attempt_id']));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        });
    }

    public function test_reconcile_rejects_booking_not_awaiting_finish(): void
    {
        $this->inRollbackTransaction(function (): void {
            [$bookingId] = $this->formReadyBooking();
            $this->forbidSupplierCalls();
            $this->expectException(RuntimeException::class);
            app(AdminRateHawkBookingFinishService::class)->reconcile($bookingId);
        });
    }

    // --------------------------------------------------------------- HTTP

    public function test_save_draft_requires_bookings_create_permission(): void
    {
        $this->inRollbackTransaction(function (): void {
            $user = User::factory()->create();
            $this->grantAdminPermission($user, 'hotels.view');
            $this->withSession(['auth_portal' => 'admin'])->actingAs($user)
                ->post(route('admin.hotels.rooms.draft', ['token' => $this->prebookToken]), $this->draftData())
                ->assertForbidden();
        });
    }

    public function test_save_draft_http_persists_selected_gender(): void
    {
        $this->inRollbackTransaction(function (): void {
            $user = User::factory()->create();
            $this->grantAdminPermission($user, 'bookings.create');
            // Pin the request to a known session id: the prebook cache is bound to it.
            $sessionId = Str::random(40);
            $store = $this->app['session']->driver();
            $store->setId($sessionId);
            $store->put('auth_portal', 'admin');
            $store->save();
            $this->rateHawkSupplierId();
            $this->putCaches([], $sessionId);
            $this->configureDirectPricing();
            $data = $this->draftData();
            $data['rooms'][0]['guests'][1]['gender'] = 'male';
            $this->actingAs($user)->withCookie(config('session.cookie'), $sessionId)
                ->post(route('admin.hotels.rooms.draft', ['token' => $this->prebookToken]), $data)
                ->assertRedirect(route('admin.hotels.rooms.review', ['token' => $this->prebookToken]))
                ->assertSessionHas('draft_saved');
            $bookingId = DB::table('rt_bookings')->where('created_at', '>=', now()->subMinute())
                ->orderByDesc('created_at')->value('id');
            $this->assertSame('male', DB::table('rt_booking_guests')->where('booking_id', $bookingId)
                ->where('first_name', 'John')->value('gender'));
        });
    }

    // ------------------------------------------------------------ helpers

    private int $baselineOfferCount = 0;

    private function prebookRate(): array
    {
        return [
            'book_hash' => 'p-prebook-hash-1',
            'room_name' => 'Standard Double Room',
            'daily_prices' => ['20.00', '22.00'],
            'payment_options' => ['payment_types' => [[
                'type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD',
                'show_amount' => self::SUPPLIER_COST, 'show_currency_code' => 'USD',
                'vat_data' => ['included' => false, 'applied' => false], 'tax_data' => [],
            ]]],
        ];
    }

    private function putCaches(array $prebookOverrides = [], ?string $sessionId = null): void
    {
        $sessionHash = hash('sha256', $sessionId ?? self::SESSION_ID);
        $searchHash = hash('sha256', $this->searchToken);
        Cache::put('admin_hotel_rooms:' . $searchHash, [
            'session' => $sessionHash,
            'environment' => 'sandbox',
            'rates_source' => 'hotelpage',
            'hid' => 123456,
            'hotel' => ['name' => 'Fixture Hotel'],
            'search' => [
                'checkin' => now()->addDays(30)->toDateString(),
                'checkout' => now()->addDays(32)->toDateString(),
                'guests' => [['adults' => 2, 'children' => [7]]],
            ],
            'rates' => [0 => ['supplier' => ['book_hash' => 'h-hotelpage-hash-1']]],
        ], now()->addMinutes(15));
        Cache::put('admin_hotel_prebook:' . hash('sha256', $this->prebookToken), array_merge([
            'session' => $sessionHash,
            'environment' => 'sandbox',
            'hid' => 123456,
            'rate_index' => 0,
            'search_token_hash' => $searchHash,
            'price_changed' => false,
            'hotelpage_book_hash' => 'h-hotelpage-hash-1',
            'prebook_verified_at' => now()->toIso8601String(),
            'rate' => $this->prebookRate(),
        ], $prebookOverrides), now()->addMinutes(10));
    }

    private function draftData(): array
    {
        return [
            'company_id' => null,
            'customer_mode' => 'new',
            'customer' => ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => null],
            'rooms' => [['guests' => [
                ['first_name' => 'Jane', 'last_name' => 'Doe', 'gender' => 'female'],
                ['first_name' => 'John', 'last_name' => 'Doe'],
                ['first_name' => 'Kid', 'last_name' => 'Doe'],
            ]]],
            'contact_email' => 'lead@example.com',
            'contact_phone' => '+201000000001',
            'accept_terms' => '1',
        ];
    }

    private function configureDirectPricing(): void
    {
        // Real pricing engine: 42.00 + 20% (8.40) + 4.60 fixed = 55.00 USD.
        app(HotelPricingService::class)->saveRule('royal_direct', null, '20', '4.60');
    }

    private function createDraft(int $userId): array
    {
        $this->rateHawkSupplierId();
        $this->baselineOfferCount = DB::table('rt_ratehawk_booking_offer_contexts')->count();
        $this->putCaches();
        $this->configureDirectPricing();
        return app(AdminHotelBookingDraftService::class)
            ->create($userId, $this->prebookToken, self::SESSION_ID, $this->draftData());
    }

    private function expectDraftHttpError(int $userId, int $status): void
    {
        $this->rateHawkSupplierId();
        $this->configureDirectPricing();
        $before = DB::table('rt_bookings')->count();
        try {
            app(AdminHotelBookingDraftService::class)->create($userId, $this->prebookToken, self::SESSION_ID, $this->draftData());
            $this->fail('Expected the draft to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
        $this->assertSame($before, DB::table('rt_bookings')->count());
    }

    /** @return array{0: string, 1: array} */
    private function formReadyBooking(array $formOverrides = []): array
    {
        $draft = $this->createDraft($this->createTestUser());
        $attempt = app(AdminRateHawkBookingConfirmationService::class)->prepare($draft['booking_id']);
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()
            ->with('/api/b2b/v3/hotel/order/booking/form/', [
                'partner_order_id' => $attempt['partner_order_id'],
                'book_hash' => 'p-prebook-hash-1',
                'language' => 'en',
                'user_ip' => self::USER_IP,
            ], 60)
            ->andReturn(['status' => 'ok', 'error' => null, 'data' => array_merge([
                'order_id' => self::ORDER_ID,
                'partner_order_id' => $attempt['partner_order_id'],
                'payment_types' => [[
                    'type' => 'deposit', 'amount' => self::SUPPLIER_COST,
                    'currency_code' => 'USD', 'is_need_credit_card_data' => false,
                ]],
                'is_gender_specification_required' => false,
            ], $formOverrides)]);
        $this->app->instance(RateHawkClient::class, $client);
        app(AdminRateHawkBookingConfirmationService::class)->createForm($draft['booking_id'], self::USER_IP);
        return [$draft['booking_id'], $attempt];
    }

    /** @return array{0: string, 1: array} */
    private function finishUnknownBooking(): array
    {
        [$bookingId, $attempt] = $this->formReadyBooking();
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()->andReturn(['status' => 'ok', 'error' => null, 'data' => null]);
        $this->app->instance(RateHawkClient::class, $client);
        app(AdminRateHawkBookingFinishService::class)->finish($bookingId);
        return [$bookingId, $attempt];
    }

    private function assertFinishRejectedBeforeClaim(string $bookingId, string $attemptId, string $message): void
    {
        try {
            app(AdminRateHawkBookingFinishService::class)->finish($bookingId);
            $this->fail('Expected Finish to be rejected locally.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsStringIgnoringCase($message, $e->getMessage());
        }
        $this->assertSame('form_ready', $this->attemptState($attemptId));
        $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        $this->assertNull(DB::table('rt_supplier_booking_attempts')->where('id', $attemptId)->value('last_error_code'));
    }

    private function assertCreateFormRejected(string $bookingId, string $message): void
    {
        try {
            app(AdminRateHawkBookingConfirmationService::class)->createForm($bookingId, self::USER_IP);
            $this->fail('Expected createForm() to be rejected locally.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsStringIgnoringCase($message, $e->getMessage());
        }
    }

    private function fakeStatus(string $status, string $partnerId): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldReceive('post')->once()
            ->with('/api/b2b/v3/hotel/order/booking/finish/status/', ['partner_order_id' => $partnerId], 30)
            ->andReturn(['status' => $status, 'error' => $status === 'error' ? 'supplier_error' : null,
                'data' => ['partner_order_id' => $partnerId]]);
        $this->app->instance(RateHawkClient::class, $client);
    }

    private function forbidSupplierCalls(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');
        $this->app->instance(RateHawkClient::class, $client);
    }

    private function attemptState(string $attemptId): string
    {
        return (string) DB::table('rt_supplier_booking_attempts')->where('id', $attemptId)->value('state');
    }

    private function grantAdminPermission(User $user, string $permission): void
    {
        DB::table('rt_admin_access')->insert([
            'user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleId = (string) Str::uuid();
        DB::table('rt_roles')->insert(['id' => $roleId, 'code' => 'admin_flow_test_' . Str::random(12), 'portal' => 'admin']);
        $permissionId = DB::table('rt_permissions')->where('code', $permission)->value('id');
        if ($permissionId === null) {
            $permissionId = (string) Str::uuid();
            DB::table('rt_permissions')->insert(['id' => $permissionId, 'code' => $permission]);
        }
        DB::table('rt_role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'company_id' => null]);
    }
}
