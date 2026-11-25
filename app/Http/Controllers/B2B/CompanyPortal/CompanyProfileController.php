<?php

namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CompanyProfileController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->attributes->get('portal_company');

        abort_unless($company, 403);

        $profile = DB::table('rt_companies')
            ->where('id', $company->id)
            ->first();

        abort_unless($profile, 404);

        $owner = DB::table('rt_company_contacts as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.company_id', $company->id)
            ->where('c.user_id', $request->user()->id)
            ->where('c.contact_type', 'primary')
            ->select([
                'c.full_name',
                'c.phone',
                'c.nationality',
                'u.email',
                'u.email_verified_at',
            ])
            ->first();

        $finance = DB::table('rt_b2b_finance_accounts')
            ->where('company_id', $company->id)
            ->where('currency', 'USD')
            ->first();

        $stats = [
            'employees' => DB::table('rt_company_memberships')
                ->where('company_id', $company->id)
                ->where('role_code', 'company_employee')
                ->where('status', 'active')
                ->whereNotNull('approved_at')
                ->count(),

            'customers' => DB::table('rt_customers')
                ->where('company_id', $company->id)
                ->count(),

            'bookings' => DB::table('rt_bookings as b')
                ->join('rt_orders as o', 'o.id', '=', 'b.order_id')
                ->where('b.company_id', $company->id)
                ->where('o.company_id', $company->id)
                ->where('b.channel', 'b2b')
                ->where('o.channel', 'b2b')
                ->where('b.service_type', 'hotel')
                ->count(),
        ];

        return view(
            'b2b.company-portal.profile.index',
            compact(
                'company',
                'profile',
                'owner',
                'finance',
                'stats'
            )
        );
    }
}