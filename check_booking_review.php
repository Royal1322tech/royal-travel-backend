<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use Illuminate\Support\Facades\Cache;

echo 'Enter review token: ';
$token = trim(fgets(STDIN));

if (!preg_match('/^[a-f0-9]{48}$/D', $token)) {
    exit("INVALID TOKEN FORMAT\n");
}

$key = 'b2b_ratehawk_review:' . hash('sha256', $token);
$review = Cache::get($key);

if (!is_array($review)) {
    exit("REVIEW EXPIRED OR NOT FOUND\n");
}

echo "REVIEW FOUND\n";
echo 'Search present: ' . (isset($review['search']) ? 'YES' : 'NO') . PHP_EOL;
echo 'Verified price: ' . (($review['price']['verified'] ?? false) === true ? 'YES' : 'NO') . PHP_EOL;
echo 'Room count: ' . count($review['search']['guests'] ?? []) . PHP_EOL;
echo 'Environment matches: ' . (
    ($review['environment'] ?? null) === config('services.ratehawk.environment', 'sandbox')
        ? 'YES' : 'NO'
) . PHP_EOL;
