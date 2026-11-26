<?php

namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->attributes->get('portal_company');

        abort_unless($company, 403);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $query = DB::table('rt_customers as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->where('c.company_id', $company->id)
            ->select([
                'c.id',
                'c.first_name',
                'c.last_name',
                'c.email',
                'c.phone',
                'c.created_at',
                'u.name as created_by_name',
            ])
            ->selectSub(function ($q) use ($company) {
                $q->from('rt_orders as o')
                    ->join('rt_bookings as b', 'b.order_id', '=', 'o.id')
                    ->whereColumn('o.customer_id', 'c.id')
                    ->where('o.company_id', $company->id)
                    ->where('b.company_id', $company->id)
                    ->where('o.channel', 'b2b')
                    ->where('b.channel', 'b2b')
                    ->where('b.service_type', 'hotel')
                    ->selectRaw('COUNT(DISTINCT b.id)');
            }, 'bookings_count');

        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';

            $query->where(function ($q) use ($search) {
                $q->where('c.first_name', 'ilike', $search)
                    ->orWhere('c.last_name', 'ilike', $search)
                    ->orWhere('c.email', 'ilike', $search)
                    ->orWhere('c.phone', 'ilike', $search)
                    ->orWhereRaw(
                        "concat_ws(' ', c.first_name, c.last_name) ILIKE ?",
                        [$search]
                    );
            });
        }

        $customers = $query
            ->orderByDesc('c.created_at')
            ->orderByDesc('c.id')
            ->paginate(20)
            ->withQueryString();

        $totalCustomers = DB::table('rt_customers')
            ->where('company_id', $company->id)
            ->count();

        return view(
            'b2b.company-portal.customers.index',
            compact('company', 'customers', 'totalCustomers')
        );
    }

    public function show(Request $request, string $id)
    {
        $company = $request->attributes->get('portal_company');

        abort_unless($company, 403);

        $customer = DB::table('rt_customers as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->where('c.company_id', $company->id)
            ->where('c.id', $id)
            ->select('c.*', 'u.name as created_by_name')
            ->first();

        abort_unless($customer, 404);

        $bookings = DB::table('rt_orders as o')
            ->join('rt_bookings as b', 'b.order_id', '=', 'o.id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->where('o.company_id', $company->id)
            ->where('b.company_id', $company->id)
            ->where('o.customer_id', $customer->id)
            ->where('o.channel', 'b2b')
            ->where('b.channel', 'b2b')
            ->where('b.service_type', 'hotel')
            ->select([
                'b.id',
                'b.booking_no',
                'b.status',
                'b.currency',
                'b.approved_total',
                'b.created_at',
                'u.name as employee_name',
            ])
            ->orderByDesc('b.created_at')
            ->paginate(20)
            ->withQueryString();

        return view(
            'b2b.company-portal.customers.show',
            compact('company', 'customer', 'bookings')
        );
    }
}