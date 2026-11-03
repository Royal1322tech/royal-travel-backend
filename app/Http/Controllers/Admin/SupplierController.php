<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Suppliers\RateHawk\RateHawkClient;
use Illuminate\Support\Facades\Log;
use Throwable;

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






public function show(string $supplier)
{
    $record = DB::table('rt_suppliers')
        ->where('id', $supplier)
        ->first();

    abort_unless($record, 404);

    return view('admin.suppliers.show', [
        'supplier' => $record,
        'environment' => config('services.ratehawk.environment', 'sandbox'),
        'configured' => filled(config('services.ratehawk.key_id'))
            && filled(config('services.ratehawk.api_key')),
    ]);
}

public function testConnection(
    string $supplier,
    RateHawkClient $client
) {
    $record = DB::table('rt_suppliers')
        ->where('id', $supplier)
        ->first();

    abort_unless($record, 404);
    abort_unless($record->adapter_code === 'ratehawk', 422);

    // Keep this action restricted to sandbox during setup.
    abort_unless(
        config('services.ratehawk.environment') === 'sandbox',
        403,
        'Connection testing is restricted to Sandbox.'
    );

    try {
        $result = $client->post(
            '/api/b2b/v3/search/serp/geo/',
            [
                'checkin' => now()->addDays(7)->toDateString(),
                'checkout' => now()->addDays(9)->toDateString(),
                'residency' => 'eg',
                'language' => 'en',
                'guests' => [
                    ['adults' => 2, 'children' => []],
                ],
                'latitude' => 25.0753,
                'longitude' => 55.1385,
                'radius' => 10000,
                'currency' => 'USD',
            ]
        );

        if (($result['status'] ?? null) !== 'ok') {
            return back()->with(
                'connection_error',
                'RateHawk returned an unsuccessful application response.'
            );
        }

        return back()->with(
            'connection_success',
            'RateHawk Sandbox responded successfully.'
        );
    } catch (Throwable $exception) {
    Log::error('RateHawk Sandbox Connection Failed', [
        'supplier_id' => $supplier,
        'exception_type' => get_class($exception),
        'error' => $exception->getMessage(),
    ]);

    return back()->with(
        'connection_error',
        'Connection failed. Check Laravel logs for details.'
    );
}
}

}
