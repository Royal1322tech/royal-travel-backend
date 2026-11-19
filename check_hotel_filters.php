<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Api\V1\B2C\Hotels\HotelSearchRequest;

$request = new HotelSearchRequest();
$rules = $request->rules();

$base = [
    'checkin' => now()->addDays(20)->format('Y-m-d'),
    'checkout' => now()->addDays(22)->format('Y-m-d'),
    'latitude' => 25.2048,
    'longitude' => 55.2708,
    'residency' => 'eg',
    'currency' => 'USD',
    'guests' => [
        ['adults' => 2, 'children' => []],
    ],
];

$tests = [
    'No filters' => [],
    'Hotel name' => ['filters' => ['hotel_name' => 'Hilton']],
    'Area' => ['filters' => ['area' => 'Downtown']],
    'Stars' => ['filters' => ['stars' => [4, 5]]],
    'Board' => ['filters' => ['board' => ['breakfast', 'half-board']]],
    'Amenities' => ['filters' => ['amenities' => ['gym', 'air_conditioning']]],
    'Package' => ['filters' => ['package_type' => 'with_package']],
    'Sort' => ['sort' => 'stars_desc'],
    'Invalid stars' => ['filters' => ['stars' => [9]]],
    'Invalid board' => ['filters' => ['board' => ['invalid']]],
];

foreach ($tests as $name => $extra) {
    $validator = Validator::make(array_replace_recursive($base, $extra), $rules);
    $valid = !$validator->fails();
    $expected = !str_starts_with($name, 'Invalid');
    echo ($valid === $expected ? 'PASS' : 'FAIL') . ' - ' . $name . PHP_EOL;

    if ($valid !== $expected) {
        echo json_encode($validator->errors()->toArray()) . PHP_EOL;
    }
}
