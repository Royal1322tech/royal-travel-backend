<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$results = Illuminate\Support\Facades\DB::table('rt_companies')
    ->select('status')
    ->selectRaw('COUNT(*) as total')
    ->groupBy('status')
    ->get();

foreach ($results as $row) {
    echo $row->status . ' : ' . $row->total . PHP_EOL;
}
