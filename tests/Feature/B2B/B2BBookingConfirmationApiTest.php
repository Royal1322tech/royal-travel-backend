<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\B2BBookingApiFixtures;
use Tests\TestCase;

/**
 * B2B booking draft, confirmation, status and employee booking lists over HTTP.
 * Supplier HTTP is always mocked: these tests prove local state handling only,
 * not a real RateHawk booking.
 */
final class B2BBookingConfirmationApiTest extends TestCase
{
    use DatabaseTransactions;
    use B2BBookingApiFixtures;

    private const OK_STATUS = ['status' => 'ok', 'error' => null, 'data' => null];
    private const PROCESSING = ['status' => 'processing', 'error' => null, 'data' => null];
    private const FINISH_OK = ['status' => 'ok', 'error' => null, 'data' => null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpB2BFixtures();
    }

    private function confirm(string $token, string $bookingId, array $body = ['accept_price' => true])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson("/api/v1/b2b/bookings/{$bookingId}/confirm", $body);
    }

    // --------------------------------------------------------------- draft

    public function test_draft_persists_verified_context_and_passes_deferred_price_constraint(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $this->supplierId();

        $response = $this->withToken($token)->withHeader('Idempotency-Key', 'draft-key-0000000001')
            ->postJson('/api/v1/b2b/bookings/draft', $this->draftBody($this->review($user, $companyId)))
            ->assertCreated()
            ->assertJson([
                'status' => 'draft', 'currency' => 'USD', 'customer_total' => self::SELLING,
                'agency_purchase_price' => self::PURCHASE, 'next_step' => 'confirm',
                'payment_collected' => false, 'supplier_booking_created' => false,
            ]);
        // Before the fix the deferred rt_booking_price_consistency trigger rejected every draft at COMMIT.
        $this->assertDeferredConstraintsHold();

        $bookingId = $response->json('booking_id');
        $booking = DB::table('rt_bookings')->where('id', $bookingId)->first();
        $this->assertSame(0, bccomp((string) $booking->approved_total, self::PURCHASE, 6));
        $this->assertSame($this->supplierId(), (string) $booking->supplier_id);

        $snapshots = DB::table('rt_booking_price_snapshots')->where('booking_id', $bookingId)->orderBy('version')->get();
        $this->assertCount(2, $snapshots);
        $this->assertSame(0, bccomp((string) $snapshots[0]->customer_total, self::PURCHASE, 6));
        $this->assertNotNull($snapshots[0]->approved_at);
        $this->assertSame(0, bccomp((string) $snapshots[1]->customer_total, self::SELLING, 6));
        foreach ($snapshots as $snapshot) {
            $this->assertSame(0, bccomp((string) $snapshot->supplier_cost, self::SUPPLIER_COST, 6));
            $this->assertSame(0, bccomp(bcadd((string) $snapshot->supplier_cost, (string) $snapshot->admin_markup, 6), self::PURCHASE, 6));
        }

        $contact = DB::table('rt_booking_supplier_contacts')->where('booking_id', $bookingId)->first();
        $this->assertSame('agent@example.com', Crypt::decryptString($contact->email_encrypted));
        $this->assertStringNotContainsString('agent@example.com', $contact->email_encrypted);
        $this->assertSame('p-fixture-prebook-hash', DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->value('prebook_hash'));
        $this->assertSame(7, (int) DB::table('rt_booking_guests')->where('booking_id', $bookingId)->where('guest_type', 'child')->value('age'));

        // Read API still shows the agency selling price, never cost or markups.
        $this->app['auth']->forgetGuards();
        $read = $this->withToken($token)->getJson("/api/v1/b2b/bookings/{$bookingId}")->assertOk();
        $this->assertSame(self::SELLING, $read->json('data.customer_total'));
        $this->assertNoInternalData($read->getContent());
        $this->assertNoInternalData($response->getContent());
    }

    public function test_draft_replay_returns_same_booking(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $this->supplierId();
        $body = $this->draftBody($this->review($user, $companyId));
        $first = $this->withToken($token)->withHeader('Idempotency-Key', 'replay-key-000000001')->postJson('/api/v1/b2b/bookings/draft', $body)->assertCreated();
        $second = $this->withToken($token)->withHeader('Idempotency-Key', 'replay-key-000000001')->postJson('/api/v1/b2b/bookings/draft', $body)->assertCreated();
        $this->assertSame($first->json('booking_id'), $second->json('booking_id'));
        $this->assertSame(1, DB::table('rt_bookings')->where('company_id', $companyId)->count());
    }

    public function test_draft_requires_supplier_contact_and_room_assignment(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $review = $this->review($user, $companyId);
        $body = $this->draftBody($review);
        unset($body['contact_email'], $body['contact_phone'], $body['guests'][0]['room_index']);
        $this->withToken($token)->withHeader('Idempotency-Key', 'contact-key-00000001')->postJson('/api/v1/b2b/bookings/draft', $body)
            ->assertUnprocessable()->assertJsonValidationErrors(['contact_email', 'contact_phone', 'guests.0.room_index']);
    }

    public function test_draft_rejects_room_child_age_and_name_mismatches(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $this->supplierId();
        $cases = [
            'unknown room' => fn (array $b) => array_replace_recursive($b, ['guests' => [1 => ['room_index' => 3]]]),
            'wrong child age' => fn (array $b) => array_replace_recursive($b, ['guests' => [2 => ['age' => 9]]]),
            'supplier-invalid name' => fn (array $b) => array_replace_recursive($b, ['guests' => [1 => ['first_name' => 'J0hn']]]),
        ];
        foreach ($cases as $label => $mutate) {
            $this->withToken($token)->withHeader('Idempotency-Key', 'mismatch-' . Str::random(16))
                ->postJson('/api/v1/b2b/bookings/draft', $mutate($this->draftBody($this->review($user, $companyId))))
                ->assertStatus(422);
        }
        $this->withToken($token)->withHeader('Idempotency-Key', 'mismatch-' . Str::random(16))
            ->postJson('/api/v1/b2b/bookings/draft', array_replace_recursive($this->draftBody($this->review($user, $companyId)), ['guests' => [2 => ['age' => null]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('guests.2.age');
        $this->assertSame(0, DB::table('rt_bookings')->where('company_id', $companyId)->count());
    }

    public function test_draft_rejects_expired_prebook_non_usd_and_inconsistent_prices(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $this->supplierId();
        $post = fn (array $overrides) => $this->withToken($token)->withHeader('Idempotency-Key', 'price-' . Str::random(16))
            ->postJson('/api/v1/b2b/bookings/draft', $this->draftBody($this->review($user, $companyId, $overrides)));

        $post(['prebook_verified_at' => now()->subMinutes(11)->toIso8601String()])->assertStatus(410);
        $post(['price' => ['verified' => true, 'selling_price' => '2000.00', 'agency_purchase_price' => '1800.00', 'currency' => 'EGP']])->assertStatus(409);
        $post(['price' => ['verified' => true, 'selling_price' => '55.00', 'agency_purchase_price' => '40.00', 'currency' => 'USD']])->assertStatus(409);
        $post(['price' => ['verified' => true, 'selling_price' => '44.00', 'agency_purchase_price' => '46.20', 'currency' => 'USD']])->assertStatus(409);
        $post(['rate' => array_replace($this->prebookRate(), ['daily_prices' => ['1.00']])])->assertStatus(409);
        $post(['hotelpage_book_hash' => null])->assertStatus(409);
        $this->assertSame(0, DB::table('rt_bookings')->where('company_id', $companyId)->count());
    }

    // ------------------------------------------------------- confirmation

    public function test_confirm_full_success_reserves_and_captures_the_agency_purchase_price_once(): void
    {
        $companyId = $this->company(50000);
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $supplier = $this->fakeSupplier(['form' => ['echo-form'], 'finish' => [self::FINISH_OK], 'status' => [self::OK_STATUS]]);

        $response = $this->confirm($token, $bookingId)->assertOk()
            ->assertJson(['booking_id' => $bookingId, 'status' => 'confirmed', 'confirmation_state' => 'confirmed']);
        $this->assertNotNull($response->json('confirmed_at'));
        $this->assertNoInternalData($response->getContent());

        $finish = collect($supplier->calls)->firstWhere(0, 'finish')[1];
        $this->assertSame(['type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD'], $finish['payment_type']);
        $this->assertSame(['email' => 'agent@example.com', 'phone' => '+201000000001'], $finish['user']);
        $this->assertCount(3, $finish['rooms'][0]['guests']);
        $this->assertSame(['form', 'finish', 'status'], array_column($supplier->calls, 0));

        $wallet = $this->wallet($companyId);
        $this->assertSame(50000 - 4620, (int) $wallet->wallet_cents);
        $this->assertSame(0, (int) $wallet->wallet_reserved_cents);
        $this->assertSame(1, DB::table('rt_b2b_finance_entries')->where('reference', "booking:{$bookingId}:wallet:capture")->count());

        // Repeat: no supplier call, no second capture.
        $this->forbidSupplier();
        $this->confirm($token, $bookingId)->assertOk()->assertJsonPath('status', 'confirmed');
        $this->assertSame(50000 - 4620, (int) $this->wallet($companyId)->wallet_cents);
        $this->assertSame(1, DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->count());
        $this->assertDeferredConstraintsHold();
    }

    public function test_processing_is_not_reported_confirmed_and_later_reconciles_once(): void
    {
        $companyId = $this->company(50000);
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->fakeSupplier(['form' => ['echo-form'], 'finish' => [self::FINISH_OK], 'status' => [self::PROCESSING]]);

        $this->confirm($token, $bookingId)->assertOk()
            ->assertJson(['status' => 'draft', 'confirmation_state' => 'processing', 'confirmed_at' => null]);
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);
        $this->assertSame(50000, (int) $this->wallet($companyId)->wallet_cents);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/b2b/bookings/{$bookingId}/status")->assertOk()
            ->assertExactJson(['booking_id' => $bookingId, 'status' => 'draft', 'confirmation_state' => 'processing', 'confirmed_at' => null]);

        // A repeated confirm only reconciles: status lookup, never a second finish.
        $supplier = $this->fakeSupplier(['status' => [self::OK_STATUS]]);
        $this->confirm($token, $bookingId)->assertOk()->assertJsonPath('status', 'confirmed');
        $this->assertSame(['status'], array_column($supplier->calls, 0));
        $this->assertSame(50000 - 4620, (int) $this->wallet($companyId)->wallet_cents);
        $this->assertSame(1, DB::table('rt_b2b_finance_entries')->where('reference', "booking:{$bookingId}:wallet:capture")->count());
    }

    public function test_insufficient_wallet_creates_no_attempt_and_calls_no_supplier(): void
    {
        $companyId = $this->company(4619);
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->forbidSupplier();
        $this->confirm($token, $bookingId)->assertUnprocessable()->assertJsonValidationErrors('wallet');
        $this->assertNull($this->attemptState($bookingId));
        $this->assertSame(0, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_form_timeout_holds_funds_and_blocks_blind_retry(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->fakeSupplier(['form' => [new RuntimeException('cURL error 28: timeout')]]);

        $response = $this->confirm($token, $bookingId)->assertStatus(409)->assertJsonPath('confirmation_state', 'form_unknown');
        $this->assertStringNotContainsString('cURL', (string) $response->getContent());
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);

        $this->forbidSupplier();
        $this->confirm($token, $bookingId)->assertStatus(409);
        $this->assertSame('form_unknown', $this->attemptState($bookingId));
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_unknown_finish_outcome_keeps_funds_held_then_reconciles_without_resending(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->fakeSupplier(['form' => ['echo-form'], 'finish' => [new RuntimeException('HTTP 504')]]);

        $this->confirm($token, $bookingId)->assertStatus(409)->assertJsonPath('confirmation_state', 'finish_unknown');
        $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);

        $supplier = $this->fakeSupplier(['status' => [self::OK_STATUS]]);
        $this->confirm($token, $bookingId)->assertOk()->assertJsonPath('status', 'confirmed');
        $this->assertSame(['status'], array_column($supplier->calls, 0));
    }

    public function test_supplier_error_status_keeps_funds_held_for_manual_review(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->fakeSupplier(['form' => ['echo-form'], 'finish' => [self::FINISH_OK],
            'status' => [['status' => 'error', 'error' => 'book_failed', 'data' => null]]]);

        $this->confirm($token, $bookingId)->assertOk()
            ->assertJson(['status' => 'draft', 'confirmation_state' => 'finish_unknown']);
        $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_expired_prebook_before_any_attempt_reserves_nothing(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->update(['prebook_verified_at' => now()->subMinutes(11)]);
        $this->confirm($token, $bookingId)->assertStatus(410);
        $this->assertNull($this->attemptState($bookingId));
        $this->assertSame(0, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_expiry_after_wallet_reservation_releases_funds_because_finish_was_never_sent(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        app(\App\Services\Hotels\RateHawkBookingOrchestrator::class)->prepare($bookingId);
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);
        DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->update(['prebook_verified_at' => now()->subMinutes(11)]);

        $this->confirm($token, $bookingId)->assertStatus(410);
        $this->assertSame('abandoned', $this->attemptState($bookingId));
        $this->assertSame('failed', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        $this->assertSame('released', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
        $this->assertSame(0, (int) $this->wallet($companyId)->wallet_reserved_cents);
        $this->assertSame(50000, (int) $this->wallet($companyId)->wallet_cents);
    }

    public function test_expiry_after_form_creation_releases_funds(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        // Funds held and a booking form exists, but Finish was never claimed when the prebook expired.
        app(\App\Services\Hotels\RateHawkBookingOrchestrator::class)->prepare($bookingId);
        DB::table('rt_supplier_booking_attempts')->where('booking_id', $bookingId)->update(['state' => 'form_ready']);
        DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->update(['prebook_verified_at' => now()->subMinutes(11)]);

        $this->forbidSupplier();
        $this->confirm($token, $bookingId)->assertStatus(410);
        $this->assertSame('abandoned', $this->attemptState($bookingId));
        $this->assertSame(0, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_expiry_never_releases_funds_once_finish_outcome_is_uncertain(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        app(\App\Services\Hotels\RateHawkBookingOrchestrator::class)->prepare($bookingId);
        DB::table('rt_supplier_booking_attempts')->where('booking_id', $bookingId)->update(['state' => 'finish_unknown']);
        DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->update(['prebook_verified_at' => now()->subMinutes(11)]);
        $this->fakeSupplier(['status' => [self::PROCESSING]]);

        // Uncertain state: reconcile only (status lookup); never abandon or release.
        $this->confirm($token, $bookingId)->assertOk()->assertJsonPath('confirmation_state', 'processing');
        $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
        $this->assertSame(4620, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_form_deposit_differing_from_verified_price_is_never_finished(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $client = \Mockery::mock(\App\Services\Suppliers\RateHawk\RateHawkClient::class);
        $client->shouldReceive('post')->once()->withArgs(fn (string $path) => str_ends_with($path, '/booking/form/'))
            ->andReturnUsing(fn (string $path, array $payload) => ['status' => 'ok', 'error' => null, 'data' => [
                'order_id' => 555002, 'partner_order_id' => $payload['partner_order_id'],
                'payment_types' => [['type' => 'deposit', 'amount' => '43.00', 'currency_code' => 'USD', 'is_need_credit_card_data' => false]],
            ]]);
        $this->app->instance(\App\Services\Suppliers\RateHawk\RateHawkClient::class, $client);

        $this->confirm($token, $bookingId)->assertStatus(409);
        $this->assertSame('form_ready', $this->attemptState($bookingId));
        $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
    }

    public function test_legacy_draft_without_booking_context_is_rejected_without_money_or_supplier(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        DB::table('rt_ratehawk_booking_offer_contexts')->where('booking_id', $bookingId)->delete();
        $this->confirm($token, $bookingId)->assertStatus(409);
        $this->assertNull($this->attemptState($bookingId));
        $this->assertSame(0, (int) $this->wallet($companyId)->wallet_reserved_cents);
    }

    public function test_concurrent_confirmation_is_rejected_while_another_holds_the_lock(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $lock = Cache::lock('b2b-confirm:' . $bookingId, 300);
        $this->assertTrue($lock->get());
        try {
            $this->confirm($token, $bookingId)->assertStatus(409);
        } finally {
            $lock->release();
        }
        $this->assertNull($this->attemptState($bookingId));
    }

    public function test_sandbox_only_and_explicit_price_acceptance(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->confirm($token, $bookingId, [])->assertUnprocessable()->assertJsonValidationErrors('accept_price');
        config(['services.ratehawk.allow_sandbox_finish' => false]);
        $this->confirm($token, $bookingId)->assertForbidden();
        config(['services.ratehawk.allow_sandbox_finish' => true, 'services.ratehawk.environment' => 'production']);
        $this->confirm($token, $bookingId)->assertForbidden();
        $this->assertNull($this->attemptState($bookingId));
    }

    public function test_authentication_company_isolation_and_permissions(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson("/api/v1/b2b/bookings/{$bookingId}/confirm", ['accept_price' => true])->assertUnauthorized();
        $this->withoutToken()->getJson("/api/v1/b2b/bookings/{$bookingId}/status")->assertUnauthorized();

        [, $otherToken] = $this->member($this->company());
        $this->confirm($otherToken, $bookingId)->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson("/api/v1/b2b/bookings/{$bookingId}/status")->assertNotFound();

        [, $noAccess] = $this->member($companyId, 'company_employee', []);
        $this->confirm($noAccess, $bookingId)->assertForbidden();
        $this->assertNull($this->attemptState($bookingId));
    }

    /**
     * Documents CURRENT behaviour (pending a business decision): any member of the same
     * company holding bookings.create may confirm a colleague's draft and spend the
     * company wallet; status is company-wide for bookings.view.
     */
    public function test_current_policy_same_company_colleague_can_view_status_and_confirm(): void
    {
        $companyId = $this->company();
        [$owner, $ownerToken] = $this->member($companyId);
        $bookingId = $this->createDraft($owner, $ownerToken, $companyId);
        [, $colleagueToken] = $this->member($companyId, 'company_employee');

        $this->withToken($colleagueToken)->getJson("/api/v1/b2b/bookings/{$bookingId}/status")->assertOk();
        $this->fakeSupplier(['form' => ['echo-form'], 'finish' => [self::FINISH_OK], 'status' => [self::OK_STATUS]]);
        $this->confirm($colleagueToken, $bookingId)->assertOk()->assertJsonPath('status', 'confirmed');
    }

    // ------------------------------------------------- employee booking lists

    public function test_my_bookings_and_my_incomplete_are_employee_scoped(): void
    {
        $companyId = $this->company();
        [$alice, $aliceToken] = $this->member($companyId);
        [$bob, $bobToken] = $this->member($companyId);
        $aliceBooking = $this->createDraft($alice, $aliceToken, $companyId);
        $bobBooking = $this->createDraft($bob, $bobToken, $companyId);

        $this->app['auth']->forgetGuards();
        $mine = $this->withToken($aliceToken)->getJson('/api/v1/b2b/bookings/my-bookings')->assertOk();
        $this->assertSame([$aliceBooking], array_column($mine->json('data'), 'booking_id'));
        $this->assertSame(['booking_id', 'booking_no', 'order_no', 'service_type', 'status', 'currency', 'customer_total',
            'check_in', 'check_out', 'rooms_count', 'customer', 'created_at', 'updated_at', 'confirmation_state',
            'confirmed_at', 'can_resume_draft'], array_keys($mine->json('data.0')));
        $this->assertTrue($mine->json('data.0.can_resume_draft'));
        $this->assertNoInternalData($mine->getContent());

        $this->app['auth']->forgetGuards();
        $incomplete = $this->withToken($bobToken)->getJson('/api/v1/b2b/bookings/my-incomplete')->assertOk();
        $this->assertSame([$bobBooking], array_column($incomplete->json('data'), 'booking_id'));
        DB::table('rt_bookings')->where('id', $bobBooking)->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->assertSame([], $this->withToken($bobToken)->getJson('/api/v1/b2b/bookings/my-incomplete')->assertOk()->json('data'));
        $this->app['auth']->forgetGuards();
        $this->withToken($bobToken)->getJson('/api/v1/b2b/bookings/my-incomplete?status=confirmed')->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->withToken($bobToken)->getJson('/api/v1/b2b/bookings/my-bookings?per_page=101')->assertUnprocessable();
    }

    private function assertNoInternalData(string $json): void
    {
        foreach (['supplier_cost', 'admin_markup', 'company_markup', 'approved_total', 'p-fixture', 'h-fixture',
            'agent@example.com', '+201000000001', 'encrypted', 'test-api-key', 'partner_order_id', 'request_hash'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "Response leaked {$secret}");
        }
    }
}
