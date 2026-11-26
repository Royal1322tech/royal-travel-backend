<?php

declare(strict_types=1);

namespace Tests\Feature\CompanyPortal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CompanyPortalFixtures;
use Tests\TestCase;

/** Dashboard, bookings, customers and profile: content, filters and two-company isolation. */
final class CompanyPortalModulesTest extends TestCase
{
    use DatabaseTransactions;
    use CompanyPortalFixtures;

    private string $a;
    private string $b;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
        $this->grantCompanyPricingToCompanyAdmin();

        // Company A
        $this->a = $this->portalCompany('approved', 'Alpha Travel', 125050);
        $ownerA = $this->owner($this->a, 'owner-a@example.test');
        $empA = $this->employee($this->a, 'Karim Employee');
        $hotel = $this->hotel('Atlantis The Palm');
        $custA = $this->customer($this->a, $empA->id, 'Mona', 'Hassan', 'mona@example.test', '+201111111111');
        $this->customer($this->a, $ownerA->id, 'Omar', 'Nabil');
        $this->ids['a_booking'] = $this->booking($this->a, $empA->id, $custA, 'confirmed', [
            'booking_no' => 'HTL-ALPHA0000001', 'order_no' => 'ORD-ALPHA0000001', 'hotel_id' => $hotel,
            'check_in' => '2027-01-10', 'created_at' => now()->subMinutes(3),
            'guests' => [['Lead', 'Guest', true, 0], ['Second', 'Guest', false, 1]],
        ]);
        $this->booking($this->a, $ownerA->id, null, 'pending_supplier', ['booking_no' => 'HTL-ALPHA0000002', 'check_in' => '2027-02-10', 'created_at' => now()->subMinutes(2)]);
        $this->booking($this->a, $ownerA->id, null, 'draft', ['booking_no' => 'HTL-ALPHA0000003', 'check_in' => '2027-03-10', 'created_at' => now()->subMinute()]);
        $this->ids['a_customer'] = $custA;
        $this->ids['a_employee'] = $empA->id;
        $this->ids['a_owner'] = $ownerA;

        // Company B (must never leak into A)
        $this->b = $this->portalCompany('approved', 'Bravo Holidays', 999900);
        $ownerB = $this->owner($this->b, 'owner-b@example.test');
        $empB = $this->employee($this->b, 'Bravo Secret Employee');
        $custB = $this->customer($this->b, $empB->id, 'Bravo', 'Customer', 'bravo@example.test');
        $this->ids['b_booking'] = $this->booking($this->b, $empB->id, $custB, 'confirmed', ['booking_no' => 'HTL-BRAVO0000001', 'order_no' => 'ORD-BRAVO0000001']);
        $this->ids['b_customer'] = $custB;

        // Inconsistent row: booking says company A, order says company B. Must never be shown.
        $mismatch = $this->booking($this->b, $empB->id, null, 'confirmed', ['booking_no' => 'HTL-MISMATCH0001']);
        // Bypass the booking/order guard trigger only for this deliberately corrupt fixture row.
        DB::statement('SET LOCAL session_replication_role = replica');
        DB::table('rt_bookings')->where('id', $mismatch)->update(['company_id' => $this->a]);
        DB::statement('SET LOCAL session_replication_role = origin');
        $this->ids['mismatch'] = $mismatch;

        $this->loginOwner($ownerA);
    }

    public function test_dashboard_counts_are_company_scoped(): void
    {
        $html = (string) $this->get(route('company-portal.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Hotel booking records<\/div><div class="number">3</', $html);
        $this->assertMatchesRegularExpression('/Active employees<\/div><div class="number">1</', $html);
        $this->assertMatchesRegularExpression('/Customers<\/div><div class="number">2</', $html);
    }

    public function test_booking_list_is_scoped_ordered_and_labels_purchase_price(): void
    {
        DB::table('rt_bookings')->where('id', $this->ids['a_booking'])->update(['approved_total' => '46.20']);
        $html = (string) $this->get(route('company-portal.bookings.index'))->assertOk()->getContent();
        foreach (['HTL-ALPHA0000001', 'HTL-ALPHA0000002', 'HTL-ALPHA0000003', 'Atlantis The Palm', 'Karim Employee', 'Agency Purchase Price', '46.20'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['HTL-BRAVO0000001', 'HTL-MISMATCH0001', 'Bravo Secret Employee', 'p-secret-offer-ref'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertLessThan(strpos($html, 'HTL-ALPHA0000001'), strpos($html, 'HTL-ALPHA0000003'), 'Newest first.');
    }

    public function test_booking_stats_count_pending_supplier_as_pending(): void
    {
        $response = $this->get(route('company-portal.bookings.index'))->assertOk();
        $this->assertSame(['total' => 3, 'confirmed' => 1, 'pending' => 1, 'cancelled' => 0],
            array_map('intval', $response->viewData('stats')));
    }

    public function test_booking_search_and_filters(): void
    {
        $find = fn (array $query) => collect($this->get(route('company-portal.bookings.index', $query))->assertOk()->viewData('bookings')->items())
            ->pluck('booking_no')->sort()->values()->all();

        $this->assertSame(['HTL-ALPHA0000001'], $find(['search' => 'htl-alpha0000001']));
        $this->assertSame(['HTL-ALPHA0000001'], $find(['search' => 'ord-alpha0000001']));
        $this->assertSame(['HTL-ALPHA0000001'], $find(['search' => 'ATLANTIS']));
        $this->assertSame(['HTL-ALPHA0000001'], $find(['search' => 'karim']));
        $this->assertSame([], $find(['search' => 'BRAVO']));
        $this->assertSame(['HTL-ALPHA0000002'], $find(['status' => 'pending_supplier']));
        $this->assertSame(['HTL-ALPHA0000001'], $find(['employee' => $this->ids['a_employee']]));
        $this->assertSame(['HTL-ALPHA0000001', 'HTL-ALPHA0000002'], $find(['from' => '2027-01-01', 'to' => '2027-02-28']));
        $this->assertSame(['HTL-ALPHA0000002'], $find(['from' => '2027-01-11', 'to' => '2027-02-28', 'status' => 'pending_supplier']));
        $this->assertSame([], $find(['search' => "%' OR 1=1 --"]));

        $this->get(route('company-portal.bookings.index', ['from' => '2027-03-01', 'to' => '2027-01-01']))->assertSessionHasErrors('to');
        $this->get(route('company-portal.bookings.index', ['from' => '01/01/2027']))->assertSessionHasErrors('from');
    }

    public function test_booking_pagination_preserves_query_string(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->booking($this->a, $this->ids['a_employee'], null, 'cancelled', ['booking_no' => sprintf('HTL-PAGE%08d', $i)]);
        }
        $page = $this->get(route('company-portal.bookings.index', ['status' => 'cancelled']))->assertOk();
        $paginator = $page->viewData('bookings');
        $this->assertSame(20, $paginator->perPage());
        $this->assertSame(21, $paginator->total());
        $this->assertStringContainsString('status=cancelled', (string) $paginator->nextPageUrl());
        $this->assertCount(1, $this->get(route('company-portal.bookings.index', ['status' => 'cancelled', 'page' => 2]))->viewData('bookings')->items());
    }

    public function test_booking_details_and_isolation(): void
    {
        $this->get(route('company-portal.bookings.show', $this->ids['a_booking']))->assertOk()
            ->assertSee('HTL-ALPHA0000001')->assertSee('Lead')->assertSee('Second')->assertDontSee('p-secret-offer-ref');
        $this->get(route('company-portal.bookings.show', $this->ids['b_booking']))->assertNotFound();
        $this->get(route('company-portal.bookings.show', $this->ids['mismatch']))->assertNotFound();
        $this->get(route('company-portal.bookings.show', (string) Str::uuid()))->assertNotFound();
        $this->get('/b2b/company/bookings/not-a-uuid')->assertNotFound();

        // Missing hotel and employee relationships still render.
        $orphan = $this->booking($this->a, $this->ids['a_employee'], null, 'confirmed', ['booking_no' => 'HTL-ORPHAN000001']);
        DB::table('users')->where('id', $this->ids['a_employee'])->update(['name' => 'Karim Employee']);
        $this->get(route('company-portal.bookings.show', $orphan))->assertOk()->assertSee('HTL-ORPHAN000001');
    }

    public function test_customers_list_search_details_and_isolation(): void
    {
        $find = fn (array $q) => collect($this->get(route('company-portal.customers.index', $q))->assertOk()->viewData('customers')->items())
            ->pluck('last_name')->sort()->values()->all();
        $this->assertSame(['Hassan', 'Nabil'], $find([]));
        foreach (['mona', 'HASSAN', 'Mona Hassan', 'mona@example', '1111111'] as $q) {
            $this->assertSame(['Hassan'], $find(['search' => $q]), $q);
        }
        $this->assertSame([], $find(['search' => 'Bravo']));

        $customers = collect($this->get(route('company-portal.customers.index'))->viewData('customers')->items())->keyBy('last_name');
        $this->assertSame(1, (int) $customers['Hassan']->bookings_count);
        $this->assertSame(0, (int) $customers['Nabil']->bookings_count);

        $this->get(route('company-portal.customers.show', $this->ids['a_customer']))->assertOk()
            ->assertSee('HTL-ALPHA0000001')->assertSee('Karim Employee')->assertDontSee('HTL-BRAVO0000001');
        $this->get(route('company-portal.customers.show', $this->ids['b_customer']))->assertNotFound();
        $this->get(route('company-portal.customers.show', (string) Str::uuid()))->assertNotFound();
    }

    public function test_user_controlled_content_is_escaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $customer = $this->customer($this->a, $this->ids['a_employee'], $xss, '<img src=x onerror=alert(2)>', 'x@example.test');
        $this->booking($this->a, $this->ids['a_employee'], $customer, 'confirmed', [
            'booking_no' => 'HTL-XSS000000001', 'guests' => [[$xss, 'Doe', true, 0]],
        ]);
        DB::table('users')->where('id', $this->ids['a_employee'])->update(['name' => $xss]);
        foreach ([route('company-portal.customers.index'), route('company-portal.customers.show', $customer),
            route('company-portal.bookings.index'), route('company-portal.employees.index')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString($xss, $html, $url);
            $this->assertStringNotContainsString('<img src=x onerror', $html, $url);
        }
    }

    public function test_profile_shows_only_active_company_and_formats_cents_without_mutating_finance(): void
    {
        $before = DB::table('rt_b2b_finance_accounts')->orderBy('company_id')->get()->toArray();
        $response = $this->get(route('company-portal.profile.index'))->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Alpha Travel', $html);
        $this->assertStringContainsString('1,250.50', $html);
        $this->assertStringContainsString('owner-a@example.test', $html);
        foreach (['Bravo Holidays', '9,999.00', 'owner-b@example.test', '29001011234567', 'id_number_encrypted'] as $leak) {
            $this->assertStringNotContainsString($leak, $html, $leak);
        }
        $this->get(route('company-portal.profile.index'))->assertOk();
        $this->assertEquals($before, DB::table('rt_b2b_finance_accounts')->orderBy('company_id')->get()->toArray());

        // Missing finance account renders.
        DB::table('rt_b2b_finance_accounts')->where('company_id', $this->a)->delete();
        $this->get(route('company-portal.profile.index'))->assertOk();
    }

    public function test_every_portal_page_renders_with_active_navigation(): void
    {
        foreach (['company-portal.dashboard', 'company-portal.employees.index', 'company-portal.bookings.index',
            'company-portal.customers.index', 'company-portal.profile.index', 'company-portal.markup.index'] as $name) {
            $html = (string) $this->get(route($name))->assertOk()->getContent();
            foreach (['company-portal.dashboard', 'company-portal.bookings.index', 'company-portal.employees.index',
                'company-portal.customers.index', 'company-portal.markup.index', 'company-portal.profile.index'] as $link) {
                $this->assertStringContainsString('href="' . route($link) . '"', $html, "{$name} missing {$link}");
            }
            $this->assertStringContainsString('action="' . route('company-portal.logout') . '"', $html);
        }
    }

    public function test_empty_states_render(): void
    {
        $fresh = $this->portalCompany('approved', 'Empty Co');
        $owner = $this->owner($fresh, 'owner-empty@example.test');
        $this->post(route('company-portal.logout'));
        $this->loginOwner($owner);
        foreach (['company-portal.dashboard', 'company-portal.employees.index', 'company-portal.bookings.index',
            'company-portal.customers.index', 'company-portal.profile.index', 'company-portal.markup.index'] as $name) {
            $this->get(route($name))->assertOk()->assertDontSee('HTL-ALPHA');
        }
        $this->get(route('company-portal.employees.index'))->assertSee('No employees found.');
    }
}
