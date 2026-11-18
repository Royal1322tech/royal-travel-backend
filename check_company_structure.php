<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$tables = [
    'rt_companies',
    'rt_company_memberships',
];

foreach ($tables as $table) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;

    if (!Illuminate\Support\Facades\Schema::hasTable($table)) {
        echo "TABLE MISSING" . PHP_EOL;
        continue;
    }

    foreach (Illuminate\Support\Facades\Schema::getColumnListing($table) as $column) {
        echo $column . PHP_EOL;
    }
}
