<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $query = DB::table('rt_suppliers');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('code', 'ilike', '%' . $search . '%')
                    ->orWhere('adapter_code', 'ilike', '%' . $search . '%');
            });
        }

        if (in_array($status, ['pending', 'active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $suppliers = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => DB::table('rt_suppliers')->count(),
            'pending' => DB::table('rt_suppliers')
                ->where('status', 'pending')
                ->count(),
            'active' => DB::table('rt_suppliers')
                ->where('status', 'active')
                ->count(),
            'inactive' => DB::table('rt_suppliers')
                ->where('status', 'inactive')
                ->count(),
        ];

        return view('admin.suppliers.index', compact(
            'suppliers',
            'stats',
            'search',
            'status'
        ));
    }
}


