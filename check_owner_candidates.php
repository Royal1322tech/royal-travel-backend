<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (DB::table('users')->orderBy('id')->get() as $user) {
    echo PHP_EOL . 'User ID: ' . $user->id . PHP_EOL;

    foreach (['name', 'email', 'role', 'is_admin', 'status'] as $field) {
        if (property_exists($user, $field)) {
            if ($field === 'email') {
                echo 'Email configured: ' . (!empty($user->email) ? 'YES' : 'NO') . PHP_EOL;
            } else {
                echo $field . ': ' . ($user->$field ?? 'NULL') . PHP_EOL;
            }
        }
    }

    $memberships = DB::table('rt_company_memberships')
        ->where('user_id', $user->id)
        ->count();

    echo 'Existing memberships: ' . $memberships . PHP_EOL;
}
