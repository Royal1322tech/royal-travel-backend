<?php

declare(strict_types=1);

namespace Tests\Feature\CompanyPortal;

use App\Services\Pricing\HotelPricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompanyPortalFixtures;
use Tests\TestCase;

/**
 * pricing.company.manage granted to company_admin (approved 2026-11-26) by migration
 * 2026_11_26_000001 and the seeder. No test-level grant is used here.
 */
final class CompanyPortalMarkupPermissionTest extends TestCase
{
    use DatabaseTransactions;
    use CompanyPortalFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortal();
    }

    private function roleHas(string $role, string $permission): bool
    {
        return DB::table('rt_role_permissions as rp')->join('rt_roles as r', 'r.id', '=', 'rp.role_id')
            ->join('rt_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('r.code', $role)->where('p.code', $permission)->exists();
    }

    public function test_company_admin_has_the_permission_and_employee_and_admin_roles_do_not(): void
    {
        $this->assertTrue($this->roleHas('company_admin', 'pricing.company.manage'));
        $this->assertFalse($this->roleHas('company_employee', 'pricing.company.manage'));
        $this->assertFalse($this->roleHas('super_admin', 'pricing.company.manage'));
        // Existing company_admin permissions are preserved.
        foreach (['dashboard.view', 'hotels.view', 'bookings.view', 'bookings.create', 'bookings.cancel', 'users.manage'] as $code) {
            $this->assertTrue($this->roleHas('company_admin', $code), $code);
        }
    }

    public function test_migrations_are_idempotent_and_additive(): void
    {
        $before = DB::table('rt_role_permissions')->count();
        foreach (['2026_11_26_000001_grant_company_pricing_to_company_admin', '2026_11_26_000002_grant_super_admin_sidebar_permissions'] as $migration) {
            (require base_path("database/migrations/{$migration}.php"))->up();
        }
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->assertSame($before, DB::table('rt_role_permissions')->count());
        $this->assertSame(1, DB::table('rt_permissions')->where('code', 'pricing.company.manage')->count());
    }

    public function test_owner_opens_and_saves_markup_without_any_extra_grant(): void
    {
        $company = $this->portalCompany();
        $owner = $this->owner($company);
        app(HotelPricingService::class)->saveRule('royal_b2b', $company, '10', '1.00');
        $royalBefore = app(HotelPricingService::class)->activeRule('royal_b2b', $company);
        $this->loginOwner($owner);

        $this->get(route('company-portal.markup.index'))->assertOk();
        $this->from(route('company-portal.markup.index'))->post(route('company-portal.markup.save'), [
            'percentage' => '5', 'fixed' => '2.00', 'expected_version' => 0,
        ])->assertSessionHas('success');
        $this->assertSame('5.000000', app(HotelPricingService::class)->activeRule('company_b2b', $company)['percentage']);
        $this->assertEquals($royalBefore, app(HotelPricingService::class)->activeRule('royal_b2b', $company));

        $this->from(route('company-portal.markup.index'))->post(route('company-portal.markup.save'), [
            'percentage' => '101', 'fixed' => '-1', 'expected_version' => 1,
        ])->assertSessionHasErrors(['percentage', 'fixed']);
        $this->assertSame(1, (int) app(HotelPricingService::class)->activeRule('company_b2b', $company)['version']);
    }

    public function test_employee_and_other_company_owner_cannot_manage_this_company_markup(): void
    {
        $company = $this->portalCompany();
        $employee = $this->employee($company);
        app(HotelPricingService::class)->saveRule('royal_b2b', $company, '10', '1.00');

        // Employee: blocked by the owner gate even with a crafted portal session.
        $this->withSession(['auth_portal' => 'b2b', 'active_company_id' => $company])->actingAs($employee, 'web')
            ->get(route('company-portal.markup.index'))->assertForbidden();
        $this->withSession(['auth_portal' => 'b2b', 'active_company_id' => $company])->actingAs($employee, 'web')
            ->post(route('company-portal.markup.save'), ['percentage' => '50', 'fixed' => '0', 'expected_version' => 0])->assertForbidden();

        // Owner of another company pointing the session at this company: blocked.
        $other = $this->portalCompany();
        $otherOwner = $this->owner($other);
        $this->flushSession();
        $this->withSession(['auth_portal' => 'b2b', 'active_company_id' => $company])->actingAs($otherOwner, 'web')
            ->post(route('company-portal.markup.save'), ['percentage' => '50', 'fixed' => '0', 'expected_version' => 0])->assertForbidden();

        $this->assertNull(app(HotelPricingService::class)->activeRule('company_b2b', $company));
    }
}
