<?php

declare(strict_types=1);

namespace Tests\Feature\Hotels;

use App\Services\Hotels\RateHawkBookingOrchestrator;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Mockery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\RoyalBookingFixtures;
use Tests\TestCase;

/**
 * Isolated database tests: NO real supplier HTTP calls (RateHawkClient is always
 * mocked). Each test builds its own company, wallet, user, supplier and booking
 * inside a transaction that is rolled back, so no external fixture is required.
 *
 * Fixture pricing: verified supplier deposit (supplier_cost) = 42.00 USD and the
 * approved agency total charged to the wallet = 50.00 USD.
 */
final class RateHawkBookingOrchestratorTest extends TestCase
{
    use RoyalBookingFixtures;

    private const SUPPLIER_COST = '42.00';
    private const APPROVED_TOTAL = '50.000000';
    private const WALLET_CENTS = 50000;

    public function test_prepare_is_idempotent_and_holds_wallet_once(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $before = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
            $service = app(RateHawkBookingOrchestrator::class);
            $first = $service->prepare($bookingId);
            $second = $service->prepare($bookingId);

            $this->assertSame($first['attempt_id'], $second['attempt_id']);
            $this->assertSame($first['partner_order_id'], $second['partner_order_id']);
            $this->assertSame('prepared', $second['state']);
            $this->assertSame(1, DB::table('rt_supplier_booking_attempts')->where('booking_id', $bookingId)->count());
            $this->assertSame(1, DB::table('rt_supplier_external_refs')->where('attempt_id', $first['attempt_id'])->where('reference_kind', 'partner_order_id')->count());
            $after = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
            $this->assertSame((int) $before->wallet_reserved_cents + 5000, (int) $after->wallet_reserved_cents);
            $this->assertSame((int) $before->wallet_cents, (int) $after->wallet_cents);
        });
    }

    public function test_invalid_finish_input_does_not_claim_or_call_supplier(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $service = app(RateHawkBookingOrchestrator::class);
            $attempt = $service->prepare($bookingId);
            $this->setFormReady($attempt['attempt_id']);
            $form = $this->form($attempt['partner_order_id']);

            try {
                $service->startBooking($bookingId, $form, [['guests' => [['first_name' => '', 'last_name' => 'Doe']]]], 'test@example.com', '+201000000000');
                $this->fail('Expected validation to reject an empty guest first name.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Invalid guest name', $e->getMessage());
            }

            $this->assertSame('form_ready', DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->value('state'));
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
            $this->assertWalletUnchangedSincePrepare($companyId);
        });
    }

    public function test_unknown_finish_cannot_be_replayed_and_funds_remain_held(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $service = app(RateHawkBookingOrchestrator::class);
            $attempt = $service->prepare($bookingId);
            $this->setFormReady($attempt['attempt_id']);
            // Simulate a previously committed send marker; NEVER send a real request.
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->update(['state' => 'finish_unknown']);
            try {
                $service->startBooking($bookingId, $this->form($attempt['partner_order_id']), [['guests' => [['first_name' => 'Jane', 'last_name' => 'Doe']]]], 'test@example.com', '+201000000000');
                $this->fail('Expected a repeated finish to be blocked.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('not ready', $e->getMessage());
            }
            $this->assertSame('finish_unknown', DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->value('state'));
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        });
    }

    public function test_processing_keeps_wallet_held(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $service = app(RateHawkBookingOrchestrator::class);
            $attempt = $service->prepare($bookingId);
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->update(['state' => 'finish_unknown']);
            $this->fakeStatus('processing', $attempt['partner_order_id']);
            $result = app(RateHawkBookingOrchestrator::class)->reconcile($bookingId);
            $this->assertSame('processing', $result['status']);
            $this->assertSame('processing', DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->value('state'));
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        });
    }

    public function test_ok_captures_exactly_once_even_when_reconciled_twice(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $service = app(RateHawkBookingOrchestrator::class);
            $attempt = $service->prepare($bookingId);
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->update(['state' => 'finish_unknown']);
            $before = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
            $this->fakeStatus('ok', $attempt['partner_order_id']);
            $this->assertSame('ok', app(RateHawkBookingOrchestrator::class)->reconcile($bookingId)['status']);
            $this->assertSame('ok', app(RateHawkBookingOrchestrator::class)->reconcile($bookingId)['status']);
            $after = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
            $this->assertSame((int) $before->wallet_cents - 5000, (int) $after->wallet_cents);
            $this->assertSame((int) $before->wallet_reserved_cents - 5000, (int) $after->wallet_reserved_cents);
            $this->assertSame('confirmed', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
            $this->assertSame('confirmed', DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->value('state'));
            $this->assertSame(1, DB::table('rt_b2b_finance_entries')->where('company_id', $companyId)
                ->where('reference', 'booking:' . $bookingId . ':wallet:capture')->count());
        });
    }

    public function test_status_timeout_keeps_funds_held(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $service = app(RateHawkBookingOrchestrator::class);
            $attempt = $service->prepare($bookingId);
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->update(['state' => 'finish_unknown']);
            $client = Mockery::mock(RateHawkClient::class);
            $client->shouldReceive('post')->once()->with('/api/b2b/v3/hotel/order/booking/finish/status/',
                ['partner_order_id' => $attempt['partner_order_id']], 30)
                ->andThrow(new RuntimeException('Simulated timeout'));
            $this->app->instance(RateHawkClient::class, $client);
            $this->assertSame('unknown', app(RateHawkBookingOrchestrator::class)->reconcile($bookingId)['status']);
            $this->assertSame('finish_unknown', DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->value('state'));
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
        });
    }

    public function test_supplier_error_does_not_release_funds(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $service = app(RateHawkBookingOrchestrator::class);
            $attempt = $service->prepare($bookingId);
            DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->update(['state' => 'finish_unknown']);
            $this->fakeStatus('error', $attempt['partner_order_id']);
            $this->assertSame('supplier_error_review', app(RateHawkBookingOrchestrator::class)->reconcile($bookingId)['status']);
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        });
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

    /**
     * @param callable(string $bookingId, string $companyId): void $run
     */
    private function withFixture(callable $run, ?string $supplierCost = self::SUPPLIER_COST): void
    {
        $this->inRollbackTransaction(function () use ($run, $supplierCost): void {
            // Default: any supplier HTTP call fails the test. Tests that need a
            // specific mocked response replace this binding explicitly.
            $this->forbidSupplierCalls();
            $userId = $this->createTestUser();
            $companyId = $this->createCompanyWithWallet(self::WALLET_CENTS, $userId);
            $supplierId = $this->rateHawkSupplierId();
            $bookingId = $this->createApprovedB2BHotelBooking(
                $companyId, $userId, $supplierId, self::APPROVED_TOTAL, $supplierCost
            );
            if ($supplierCost !== null) {
                $this->assertDeferredConstraintsHold();
            }
            $run($bookingId, $companyId);
        });
    }

    private function forbidSupplierCalls(): void
    {
        $client = Mockery::mock(RateHawkClient::class);
        $client->shouldNotReceive('post');
        $this->app->instance(RateHawkClient::class, $client);
    }

    /** After prepare(): exactly the approved total is reserved, nothing captured. */
    private function assertWalletUnchangedSincePrepare(string $companyId): void
    {
        $account = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
        $this->assertSame(self::WALLET_CENTS, (int) $account->wallet_cents);
        $this->assertSame(5000, (int) $account->wallet_reserved_cents);
        $this->assertSame(0, DB::table('rt_b2b_finance_entries')->where('company_id', $companyId)->count());
    }

    /**
     * Calls startBooking() expecting a local rejection and verifies nothing
     * irreversible happened: no claim, no supplier call, no financial change.
     */
    private function assertFinishRejected(string $bookingId, string $companyId, string $attemptId, array $form,
        array $rooms, string $expectedMessage): void
    {
        try {
            app(RateHawkBookingOrchestrator::class)->startBooking(
                $bookingId, $form, $rooms, 'test@example.com', '+201000000000'
            );
            $this->fail('Expected startBooking() to reject the input.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
        $this->assertSame('form_ready', DB::table('rt_supplier_booking_attempts')->where('id', $attemptId)->value('state'));
        $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
        $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
        $this->assertWalletUnchangedSincePrepare($companyId);
    }

    private function validRooms(): array
    {
        return [['guests' => [['first_name' => 'Jane', 'last_name' => 'Doe']]]];
    }

    private function preparedFormReady(string $bookingId): array
    {
        $attempt = app(RateHawkBookingOrchestrator::class)->prepare($bookingId);
        $this->setFormReady($attempt['attempt_id']);
        return $attempt;
    }

    public function test_missing_verified_price_snapshot_rejects_finish_without_supplier_call(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $this->assertSame(0, DB::table('rt_booking_price_snapshots')->where('booking_id', $bookingId)->count());
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'],
                $this->form($attempt['partner_order_id']), $this->validRooms(),
                'Supplier deposit does not match latest verified supplier cost');
        }, null);
    }

    public function test_incorrect_supplier_deposit_amount_is_rejected(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $form = $this->form($attempt['partner_order_id']);
            $form['payment_types'][0]['amount'] = '42.01';
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'], $form, $this->validRooms(),
                'Supplier deposit does not match latest verified supplier cost');
        });
    }

    public function test_agency_total_is_not_accepted_as_supplier_deposit(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $form = $this->form($attempt['partner_order_id']);
            $form['payment_types'][0]['amount'] = '50.00';
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'], $form, $this->validRooms(),
                'Supplier deposit does not match latest verified supplier cost');
        });
    }

    public function test_incorrect_deposit_currency_is_rejected(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $form = $this->form($attempt['partner_order_id']);
            $form['payment_types'][0]['currency_code'] = 'EUR';
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'], $form, $this->validRooms(),
                'Supplier deposit does not match latest verified supplier cost');
        });
    }

    public function test_first_supported_deposit_mismatch_is_rejected_even_if_later_option_matches(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $form = $this->form($attempt['partner_order_id']);
            $form['payment_types'] = [
                ['type' => 'deposit', 'amount' => '99.00', 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
                ['type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
            ];
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'], $form, $this->validRooms(),
                'Supplier deposit does not match latest verified supplier cost');
        });
    }

    public function test_numeric_first_deposit_is_skipped_exactly_like_the_supplier_adapter(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $form = $this->form($attempt['partner_order_id']);
            // A numeric (non-string) amount is not submittable by RateHawkBookingService,
            // so the adapter would send the SECOND option. Validation must check that one.
            $form['payment_types'] = [
                ['type' => 'deposit', 'amount' => 42.0, 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
                ['type' => 'deposit', 'amount' => '77.00', 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
            ];
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'], $form, $this->validRooms(),
                'Supplier deposit does not match latest verified supplier cost');
        });
    }

    public function test_card_deposit_before_matching_deposit_is_ignored_and_matching_deposit_is_submitted(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $form = $this->form($attempt['partner_order_id']);
            $form['payment_types'] = [
                ['type' => 'deposit', 'amount' => '10.00', 'currency_code' => 'USD', 'is_need_credit_card_data' => true],
                ['type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD', 'is_need_credit_card_data' => false],
            ];
            $client = Mockery::mock(RateHawkClient::class);
            $client->shouldReceive('post')->once()->withArgs(function (string $path, array $payload, int $timeout) use ($attempt): bool {
                return $path === '/api/b2b/v3/hotel/order/booking/finish/'
                    && $payload['partner']['partner_order_id'] === $attempt['partner_order_id']
                    && $payload['payment_type'] === ['type' => 'deposit', 'amount' => self::SUPPLIER_COST, 'currency_code' => 'USD']
                    && $payload['rooms'] === $this->validRooms()
                    && $timeout === 60;
            })->andReturn(['status' => 'ok', 'error' => null, 'data' => null]);
            $this->app->instance(RateHawkClient::class, $client);

            $result = app(RateHawkBookingOrchestrator::class)->startBooking(
                $bookingId, $form, $this->validRooms(), 'test@example.com', '+201000000000'
            );
            $this->assertSame('submitted', $result['status']);
            // Submission is NOT confirmation: funds stay held until reconcile() sees ok.
            $this->assertSame('finish_unknown', DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->value('state'));
            $this->assertSame('held', DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->value('state'));
            $this->assertSame('draft', DB::table('rt_bookings')->where('id', $bookingId)->value('status'));
            $this->assertWalletUnchangedSincePrepare($companyId);
        });
    }

    public function test_invalid_guest_name_with_valid_matching_deposit_is_rejected_before_claim(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = $this->preparedFormReady($bookingId);
            $this->assertFinishRejected($bookingId, $companyId, $attempt['attempt_id'],
                $this->form($attempt['partner_order_id']),
                [['guests' => [['first_name' => 'J4ne<script>', 'last_name' => 'Doe']]]],
                'Invalid guest name');
        });
    }

    public function test_prepare_requires_approved_total_and_reserves_nothing_without_it(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            DB::table('rt_bookings')->where('id', $bookingId)->update(['approved_total' => null]);
            try {
                app(RateHawkBookingOrchestrator::class)->prepare($bookingId);
                $this->fail('Expected prepare() to require an approved total.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('approved total', $e->getMessage());
            }
            $this->assertSame(0, DB::table('rt_supplier_booking_attempts')->where('booking_id', $bookingId)->count());
            $this->assertSame(0, (int) DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->value('wallet_reserved_cents'));
        });
    }

    public function test_second_attempt_row_for_same_booking_is_rejected_by_database(): void
    {
        $this->withFixture(function (string $bookingId, string $companyId): void {
            $attempt = app(RateHawkBookingOrchestrator::class)->prepare($bookingId);
            $row = DB::table('rt_supplier_booking_attempts')->where('id', $attempt['attempt_id'])->first();
            DB::statement('SAVEPOINT duplicate_attempt');
            try {
                DB::table('rt_supplier_booking_attempts')->insert([
                    'id' => (string) Str::uuid(), 'booking_id' => $bookingId,
                    'supplier_id' => $row->supplier_id, 'operation' => 'ratehawk_hotel_booking',
                    'attempt_no' => 2, 'state' => 'prepared', 'request_key' => (string) Str::uuid(),
                ]);
                $this->fail('Expected the partial unique index to reject a second RateHawk attempt.');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('rt_ratehawk_one_attempt_per_booking', $e->getMessage());
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT duplicate_attempt');
            }
            $this->assertSame(1, DB::table('rt_financial_reservations')->where('booking_id', $bookingId)->count());
        });
    }

    private function setFormReady(string $attemptId): void
    {
        $attempt = DB::table('rt_supplier_booking_attempts')->where('id', $attemptId)->first();
        DB::table('rt_supplier_external_refs')->insert([
            'id' => (string) Str::uuid(), 'attempt_id' => $attemptId,
            'supplier_id' => $attempt->supplier_id,
            'reference_kind' => 'ratehawk_order_id', 'reference_value' => '123456789',
        ]);
        DB::table('rt_supplier_booking_attempts')->where('id', $attemptId)->update(['state' => 'form_ready']);
    }

    private function form(string $partnerId): array
    {
        return [
            'partner_order_id' => $partnerId, 'order_id' => 123456789,
            'payment_types' => [[
                'type' => 'deposit', 'amount' => self::SUPPLIER_COST,
                'currency_code' => 'USD', 'is_need_credit_card_data' => false,
            ]],
        ];
    }
}
