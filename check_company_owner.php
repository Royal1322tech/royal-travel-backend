<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Illuminate\Support\Facades\DB;

$companies = DB::table('rt_companies')
    ->select('id', 'status')
    ->get();

foreach ($companies as $company) {
    echo PHP_EOL . "Company status: {$company->status}" . PHP_EOL;

    $members = DB::table('rt_company_memberships')
        ->where('company_id', $company->id)
        ->select('role_code', 'status')
        ->selectRaw('COUNT(*) as total')
        ->groupBy('role_code', 'status')
        ->get();

    if ($members->isEmpty()) {
        echo "No memberships found." . PHP_EOL;
        continue;
    }

    foreach ($members as $member) {
        echo "Role: {$member->role_code}"
            . " | Status: {$member->status}"
            . " | Count: {$member->total}"
            . PHP_EOL;
    }
}
