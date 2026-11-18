<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$companyId = 'c03c7e8f-f8c2-4196-b274-1bb2a571e616';

$company = DB::table('rt_companies')
    ->where('id', $companyId)
    ->first();

$owner = DB::table('rt_company_memberships')
    ->where('company_id', $companyId)
    ->where('role_code', 'company_admin')
    ->first();

echo 'Company status: ' . ($company->status ?? 'NOT FOUND') . PHP_EOL;
echo 'Owner membership: ' . ($owner->status ?? 'NOT FOUND') . PHP_EOL;

if ($owner) {
    $user = DB::table('users')->where('id', $owner->user_id)->first();

    echo 'Owner user exists: ' . ($user ? 'YES' : 'NO') . PHP_EOL;

    if ($user) {
        echo 'Email configured: ' . (!empty($user->email) ? 'YES' : 'NO') . PHP_EOL;

        if (Schema::hasColumn('users', 'email_verified_at')) {
            echo 'Email verified: '
                . (!empty($user->email_verified_at) ? 'YES' : 'NO')
                . PHP_EOL;
        }

        if (Schema::hasColumn('users', 'must_change_password')) {
            echo 'Must change password: '
                . ($user->must_change_password ? 'YES' : 'NO')
                . PHP_EOL;
        }
    }
}
