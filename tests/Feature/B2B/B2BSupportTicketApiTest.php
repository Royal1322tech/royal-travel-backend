<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\B2BBookingApiFixtures;
use Tests\TestCase;

/** Employee-owned booking support tickets (B2B API) and their Admin handling. */
final class B2BSupportTicketApiTest extends TestCase
{
    use DatabaseTransactions;
    use B2BBookingApiFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpB2BFixtures();
    }

    private function confirmedBooking(User $user, string $token, string $companyId): string
    {
        $bookingId = $this->createDraft($user, $token, $companyId);
        DB::table('rt_bookings')->where('id', $bookingId)->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        return $bookingId;
    }

    private function as(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function ticketBody(array $overrides = []): array
    {
        return array_replace(['category' => 'room_issue', 'subject' => 'AC not working', 'description' => 'Room 504 AC is broken.'], $overrides);
    }

    private function open(string $token, string $bookingId, string $key = 'ticket-key-0000000001', array $body = [])
    {
        return $this->as($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/b2b/bookings/{$bookingId}/tickets", $this->ticketBody($body));
    }

    public function test_categories(): void
    {
        [, $token] = $this->member($this->company());
        $this->as($token)->getJson('/api/v1/b2b/tickets/categories')->assertOk()->assertExactJson([
            'booking_issue', 'hotel_issue', 'checkin_issue', 'room_issue', 'payment_issue',
            'voucher_issue', 'cancellation_issue', 'refund_issue', 'other',
        ]);
    }

    public function test_create_for_own_confirmed_booking_with_idempotent_replay_and_conflict(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->confirmedBooking($user, $token, $companyId);

        $created = $this->open($token, $bookingId)->assertCreated()
            ->assertJsonPath('status', 'open')->assertJsonPath('booking_id', $bookingId);
        $this->assertArrayNotHasKey('request_key', $created->json());
        $this->assertArrayNotHasKey('request_hash', $created->json());
        $this->assertMatchesRegularExpression('/^TKT-[A-Z0-9]{16}$/', $created->json('ticket_no'));

        $replay = $this->open($token, $bookingId)->assertOk();
        $this->assertSame($created->json('id'), $replay->json('id'));
        $this->assertSame(1, DB::table('rt_support_tickets')->where('booking_id', $bookingId)->count());
        $this->assertSame(1, DB::table('rt_support_ticket_messages')->where('ticket_id', $created->json('id'))->count());

        $this->open($token, $bookingId, 'ticket-key-0000000001', ['subject' => 'Different'])->assertStatus(409);
        $this->open($token, $bookingId, 'short')->assertUnprocessable();
        $this->as($token)->postJson("/api/v1/b2b/bookings/{$bookingId}/tickets", $this->ticketBody())->assertUnprocessable();
    }

    public function test_validation(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->confirmedBooking($user, $token, $companyId);
        $this->open($token, $bookingId, 'val-key-000000000001', ['category' => 'complaint', 'subject' => str_repeat('a', 151), 'description' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['category', 'subject', 'description']);
    }

    public function test_unconfirmed_booking_is_rejected(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->createDraft($user, $token, $companyId);
        $this->open($token, $bookingId)->assertStatus(409);
        $this->assertSame(0, DB::table('rt_support_tickets')->count() - DB::table('rt_support_tickets')->where('booking_id', '!=', $bookingId)->count());
    }

    public function test_cross_employee_and_cross_company_access_is_denied(): void
    {
        $companyId = $this->company();
        [$alice, $aliceToken] = $this->member($companyId);
        [, $bobToken] = $this->member($companyId);
        [, $outsiderToken] = $this->member($this->company());
        $bookingId = $this->confirmedBooking($alice, $aliceToken, $companyId);
        $ticketId = $this->open($aliceToken, $bookingId)->assertCreated()->json('id');

        foreach ([$bobToken, $outsiderToken] as $token) {
            $this->open($token, $bookingId, 'other-key-000000000001')->assertNotFound();
            $this->as($token)->getJson("/api/v1/b2b/tickets/{$ticketId}")->assertNotFound();
            $this->as($token)->getJson("/api/v1/b2b/bookings/{$bookingId}/tickets")->assertNotFound();
            $this->as($token)->postJson("/api/v1/b2b/tickets/{$ticketId}/messages", ['message' => 'hi'])->assertNotFound();
            $this->assertSame(0, $this->as($token)->getJson('/api/v1/b2b/tickets')->assertOk()->json('total'));
        }
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/b2b/tickets')->assertUnauthorized();
    }

    public function test_listing_details_and_pagination(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->confirmedBooking($user, $token, $companyId);
        $this->open($token, $bookingId, 'list-key-000000000000')->assertCreated();
        // The remaining 20 are inserted directly: ticket creation is limited to 5/min.
        for ($i = 1; $i <= 20; $i++) {
            DB::table('rt_support_tickets')->insert([
                'id' => (string) Str::uuid(), 'ticket_no' => 'TKT-' . Str::upper(Str::random(16)), 'booking_id' => $bookingId,
                'company_id' => $companyId, 'created_by' => $user->id, 'category' => 'other', 'subject' => "Fixture {$i}",
                'status' => 'open', 'request_key' => hash('sha256', "fixture-{$i}"), 'request_hash' => hash('sha256', "h{$i}"),
                'created_at' => now()->subMinutes($i), 'updated_at' => now()->subMinutes($i),
            ]);
        }
        $page = $this->as($token)->getJson('/api/v1/b2b/tickets')->assertOk();
        $this->assertSame(21, $page->json('total'));
        $this->assertSame(20, $page->json('per_page'));
        $this->assertCount(20, $page->json('data'));
        $this->assertCount(1, $this->as($token)->getJson('/api/v1/b2b/tickets?page=2')->json('data'));
        $this->assertSame(21, $this->as($token)->getJson("/api/v1/b2b/bookings/{$bookingId}/tickets")->json('total'));
        foreach ($page->json('data') as $ticket) {
            $this->assertArrayNotHasKey('request_hash', $ticket);
            $this->assertArrayNotHasKey('request_key', $ticket);
        }

        $ticketId = $page->json('data.0.id');
        $detail = $this->as($token)->getJson("/api/v1/b2b/tickets/{$ticketId}")->assertOk();
        $this->assertSame(['ticket', 'messages'], array_keys($detail->json()));
        $this->assertSame(['id', 'actor_type', 'body', 'created_at'], array_keys($detail->json('messages.data.0')));
        $this->assertSame(50, $detail->json('messages.per_page'));
        $this->assertArrayNotHasKey('request_hash', $detail->json('ticket'));
    }

    public function test_replies_with_optional_idempotency_and_closed_tickets(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->confirmedBooking($user, $token, $companyId);
        $ticketId = $this->open($token, $bookingId)->assertCreated()->json('id');
        $url = "/api/v1/b2b/tickets/{$ticketId}/messages";

        $this->as($token)->postJson($url, ['message' => ''])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->as($token)->postJson($url, ['message' => str_repeat('x', 5001)])->assertUnprocessable();

        // Without a key: unchanged behaviour (each call posts).
        $this->as($token)->postJson($url, ['message' => 'Any update?'])->assertCreated()->assertJsonPath('status', 'sent');

        // With a key: a retry is not duplicated; a different message with the same key conflicts.
        $first = $this->as($token)->withHeader('Idempotency-Key', 'reply-key-0000000001')->postJson($url, ['message' => 'Retry-safe'])->assertCreated();
        $retry = $this->as($token)->withHeader('Idempotency-Key', 'reply-key-0000000001')->postJson($url, ['message' => 'Retry-safe'])->assertOk();
        $this->assertSame($first->json('message_id'), $retry->json('message_id'));
        $this->as($token)->withHeader('Idempotency-Key', 'reply-key-0000000001')->postJson($url, ['message' => 'Other'])->assertStatus(409);
        $this->as($token)->withHeader('Idempotency-Key', 'bad')->postJson($url, ['message' => 'x'])->assertUnprocessable();
        $this->assertSame(3, DB::table('rt_support_ticket_messages')->where('ticket_id', $ticketId)->count());

        foreach (['resolved', 'closed'] as $status) {
            DB::table('rt_support_tickets')->where('id', $ticketId)->update(['status' => $status]);
            $this->as($token)->withHeader('Idempotency-Key', 'late-key-' . $status . '-0000')->postJson($url, ['message' => 'Still broken'])->assertStatus(409);
        }
    }

    public function test_ticket_creation_throttle_is_isolated_from_other_api_traffic(): void
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->confirmedBooking($user, $token, $companyId);
        // Ordinary browsing must not consume the ticket-creation budget (shared-key regression).
        for ($i = 0; $i < 8; $i++) {
            $this->as($token)->getJson('/api/v1/b2b/tickets')->assertOk();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->open($token, $bookingId, 'throttle-key-' . str_pad((string) $i, 8, '0', STR_PAD_LEFT))->assertCreated();
        }
        $this->open($token, $bookingId, 'throttle-key-99999999')->assertStatus(429);
    }

    // ---------------------------------------------------------------- Admin

    private function admin(array $permissions): User
    {
        $user = User::factory()->create();
        DB::table('rt_admin_access')->insert(['user_id' => $user->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $roleId = (string) Str::uuid();
        DB::table('rt_roles')->insert(['id' => $roleId, 'code' => 'support_test_' . Str::random(10), 'portal' => 'admin']);
        foreach ($permissions as $permission) {
            DB::table('rt_role_permissions')->insert(['role_id' => $roleId,
                'permission_id' => DB::table('rt_permissions')->where('code', $permission)->value('id')]);
        }
        DB::table('rt_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'company_id' => null]);

        return $user;
    }

    private function adminTicket(): array
    {
        $companyId = $this->company();
        [$user, $token] = $this->member($companyId);
        $bookingId = $this->confirmedBooking($user, $token, $companyId);
        $ticketId = $this->open($token, $bookingId, 'admin-key-0000000001', [
            'subject' => '<script>alert("s")</script>', 'description' => '<img src=x onerror=alert(1)>',
        ])->assertCreated()->json('id');
        $this->withoutToken();
        $this->app['auth']->forgetGuards();

        return [$ticketId, $token];
    }

    public function test_admin_views_escape_user_content_and_require_permission(): void
    {
        [$ticketId] = $this->adminTicket();
        $this->withoutVite();

        $this->get('/admin/support-tickets')->assertRedirect(route('admin.login'));
        $this->withSession(['auth_portal' => 'admin'])->actingAs($this->admin(['dashboard.view']))
            ->get('/admin/support-tickets')->assertForbidden();
        $this->withSession(['auth_portal' => 'b2b'])->actingAs($this->admin(['bookings.view']))
            ->get('/admin/support-tickets')->assertForbidden();

        $viewer = $this->admin(['bookings.view']);
        $index = $this->withSession(['auth_portal' => 'admin'])->actingAs($viewer)->get('/admin/support-tickets')->assertOk();
        $show = $this->withSession(['auth_portal' => 'admin'])->actingAs($viewer)->get("/admin/support-tickets/{$ticketId}")->assertOk();
        foreach ([$index, $show] as $page) {
            $html = (string) $page->getContent();
            $this->assertStringNotContainsString('<script>alert("s")</script>', $html);
            $this->assertStringNotContainsString('<img src=x onerror', $html);
            $this->assertStringNotContainsString('request_hash', $html);
        }
        $this->assertStringContainsString('&lt;script&gt;', (string) $show->getContent());

        // bookings.view alone cannot update.
        $this->withSession(['auth_portal' => 'admin'])->actingAs($viewer)
            ->post("/admin/support-tickets/{$ticketId}", ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_admin_reply_status_transitions_and_employee_reply_rules(): void
    {
        [$ticketId, $employeeToken] = $this->adminTicket();
        $manager = $this->admin(['bookings.view', 'bookings.manage']);
        $post = fn (array $body) => $this->withSession(['auth_portal' => 'admin'])->actingAs($manager)
            ->post("/admin/support-tickets/{$ticketId}", $body);

        $post(['status' => 'in_progress', 'message' => 'Looking into it'])->assertRedirect();
        $this->assertSame('in_progress', DB::table('rt_support_tickets')->where('id', $ticketId)->value('status'));
        $this->assertSame('support', DB::table('rt_support_ticket_messages')->where('ticket_id', $ticketId)->orderByDesc('created_at')->value('actor_type'));
        $this->assertSame(1, DB::table('rt_support_ticket_events')->where('ticket_id', $ticketId)->where('to_status', 'in_progress')->count());

        $post(['status' => 'open'])->assertStatus(422);
        $post(['status' => 'bogus'])->assertSessionHasErrors('status');
        $post(['status' => 'resolved'])->assertRedirect();

        // Employee cannot reply to a resolved ticket.
        $this->as($employeeToken)->postJson("/api/v1/b2b/tickets/{$ticketId}/messages", ['message' => 'Thanks'])->assertStatus(409);

        $post(['status' => 'closed'])->assertRedirect();
        $post(['status' => 'closed', 'message' => 'after close'])->assertStatus(409);
        $post(['status' => 'in_progress'])->assertStatus(422);
        $this->assertSame('closed', DB::table('rt_support_tickets')->where('id', $ticketId)->value('status'));
    }
}
