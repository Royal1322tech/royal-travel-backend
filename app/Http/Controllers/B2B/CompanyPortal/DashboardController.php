<?php
namespace App\Http\Controllers\B2B\CompanyPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->attributes->get('portal_company');
        $id = $company->id;
        $bookings = DB::table('rt_bookings as b')->join('rt_orders as o','o.id','=','b.order_id')
            ->where('b.company_id',$id)->where('o.company_id',$id)
            ->where('b.channel','b2b')->where('o.channel','b2b')->where('b.service_type','hotel');
        $stats = [
            'Hotel booking records' => (clone $bookings)->count(),
            'Active employees' => DB::table('rt_company_memberships')->where('company_id',$id)->where('role_code','company_employee')->where('status','active')->whereNotNull('approved_at')->count(),
            'Customers' => DB::table('rt_customers')->where('company_id',$id)->count(),
        ];
        return view('b2b.company-portal.dashboard', compact('company','stats'));
    }
}
