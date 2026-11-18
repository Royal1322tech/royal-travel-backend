<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Illuminate\Support\Facades\DB;

$users = DB::table('users')
    ->select('id')
    ->orderBy('id')
    ->limit(20)
    ->get();

echo 'Users found: ' . DB::table('users')->count() . PHP_EOL;

foreach ($users as $user) {
    echo 'User ID: ' . $user->id . PHP_EOL;
}
