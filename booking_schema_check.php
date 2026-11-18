<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$tables = [
    'rt_orders',
    'rt_bookings',
    'rt_booking_guests',
    'rt_hotel_booking_details',
    'rt_booking_price_snapshots',
    'rt_idempotency_keys',
];

foreach ($tables as $table) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;

    $columns = Illuminate\Support\Facades\DB::select(
        "SELECT column_name, data_type, is_nullable, column_default
         FROM information_schema.columns
         WHERE table_schema = 'public'
         AND table_name = ?
         ORDER BY ordinal_position",
        [$table]
    );

    foreach ($columns as $column) {
        echo $column->column_name
            . ' | ' . $column->data_type
            . ' | ' . $column->is_nullable
            . ' | ' . ($column->column_default ?? '-')
            . PHP_EOL;
    }
}
