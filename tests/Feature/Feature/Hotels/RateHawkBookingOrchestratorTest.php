<?php

declare(strict_types=1);

namespace Tests\Feature\Hotels;

use App\Services\Hotels\RateHawkBookingOrchestrator;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Mockery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Local database tests: NO supplier HTTP calls. Uses existing test company,
 * wallet, user and supplier; all changes are rolled back.
 */
final class RateHawkBookingOrchestratorTest extends TestCase
{
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

    private function withFixture(callable $run): void
    {
        if (app()->environment('production')) {
            $this->fail('Never run these tests in production.');
        }
        $companyId = env('WALLET_TEST_COMPANY_ID');
        if (!$companyId) {
            $this->markTestSkipped('Set WALLET_TEST_COMPANY_ID to a local test company.');
        }
        $wallet = DB::table('rt_b2b_finance_accounts')->where('company_id', $companyId)->first();
        $company = DB::table('rt_companies')->where('id', $companyId)->first();
        $userId = DB::table('users')->orderBy('id')->value('id');
        $supplierId = DB::table('rt_suppliers')->orderBy('id')->value('id');
        if (!$company || !$wallet || !$userId || !$supplierId || trim((string) $wallet->currency) !== 'USD'
            || (int) $wallet->wallet_cents - (int) $wallet->wallet_reserved_cents < 5000) {
            $this->markTestSkipped('Need existing company, USD wallet ($50 available), user and supplier.');
        }
        DB::beginTransaction();
        try {
            $orderId = (string) Str::uuid();
            $bookingId = (string) Str::uuid();
            DB::table('rt_orders')->insert([
                'id' => $orderId, 'order_no' => 'ORCHTEST-' . Str::random(16),
                'channel' => 'b2b', 'company_id' => $companyId,
                'customer_user_id' => $userId, 'created_by' => $userId,
                'status' => 'draft', 'currency' => 'USD',
            ]);
            DB::table('rt_bookings')->insert([
                'id' => $bookingId, 'order_id' => $orderId,
                'booking_no' => 'ORCHTEST-' . Str::random(16),
                'service_type' => 'hotel', 'channel' => 'b2b',
                'company_id' => $companyId, 'customer_user_id' => $userId,
                'supplier_id' => $supplierId, 'status' => 'draft',
                'currency' => 'USD', 'approved_total' => '50.000000',
            ]);
            $run($bookingId, $companyId);
        } finally {
            DB::rollBack();
        }
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
                'type' => 'deposit', 'amount' => '50.00',
                'currency_code' => 'USD', 'is_need_credit_card_data' => false,
            ]],
        ];
    }
}
