<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$companyId = 'c03c7e8f-f8c2-4196-b274-1bb2a571e616';
$userId = 2;

DB::transaction(function () use ($companyId, $userId) {

    $company = DB::table('rt_companies')
        ->where('id', $companyId)
        ->lockForUpdate()
        ->first();

    if (!$company || $company->status !== 'pending') {
        throw new RuntimeException('Company is not pending.');
    }

    $user = DB::table('users')
        ->where('id', $userId)
        ->first();

    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $existing = DB::table('rt_company_memberships')
        ->where('user_id', $userId)
        ->exists();

    if ($existing) {
        throw new RuntimeException('User already has a company membership.');
    }

    $ownerExists = DB::table('rt_company_memberships')
        ->where('company_id', $companyId)
        ->where('role_code', 'company_admin')
        ->exists();

    if ($ownerExists) {
        throw new RuntimeException('Company already has an owner.');
    }

    DB::table('rt_company_memberships')->insert([
        'id' => (string) Str::uuid(),
        'company_id' => $companyId,
        'user_id' => $userId,
        'role_code' => 'company_admin',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo 'Owner membership created successfully.' . PHP_EOL;
});
