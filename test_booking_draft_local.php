<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

use App\Services\Bookings\B2BBookingDraftService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$companyId = 'c03c7e8f-f8c2-4196-b274-1bb2a571e616';
$userId = 2;

$token = bin2hex(random_bytes(24));
$cacheKey = 'b2b_ratehawk_review:' . hash('sha256', $token);

$review = [
    'environment' => (string) config('services.ratehawk.environment', 'sandbox'),
    'user_id' => $userId,
    'company_id' => $companyId,
    'hid' => 123456,
    'search' => [
        'checkin' => '2027-02-10',
        'checkout' => '2027-02-12',
        'guests' => [
            ['adults' => 2, 'children' => []],
        ],
    ],
    'rate' => [
        'book_hash' => 'LOCAL-TEST-NOT-A-SUPPLIER-OFFER',
    ],
    'price' => [
        'verified' => true,
        'currency' => 'USD',
        'selling_price' => '285.52',
    ],
];

$guests = [
    [
        'first_name' => 'Test',
        'last_name' => 'GuestOne',
        'guest_type' => 'adult',
        'date_of_birth' => null,
        'nationality_code' => 'EG',
        'is_lead' => true,
    ],
    [
        'first_name' => 'Test',
        'last_name' => 'GuestTwo',
        'guest_type' => 'adult',
        'date_of_birth' => null,
        'nationality_code' => 'EG',
        'is_lead' => false,
    ],
];

$transactionStarted = false;

try {
    Cache::put($cacheKey, $review, now()->addMinutes(10));

    DB::beginTransaction();
    $transactionStarted = true;

    $service = app(B2BBookingDraftService::class);

    $data = [
        'review_token' => $token,
        'guests' => $guests,
    ];

    $idempotencyKey = 'local-test-' . bin2hex(random_bytes(16));

    $result = $service->create(
        $userId,
        $companyId,
        $data,
        $idempotencyKey
    );

    echo "FIRST CREATE: SUCCESS\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

    $bookingId = $result['booking_id'];
    $orderId = $result['order_id'];

    $checks = [
        'Order' => DB::table('rt_orders')
            ->where('id', $orderId)->count(),

        'Booking' => DB::table('rt_bookings')
            ->where('id', $bookingId)->count(),

        'Hotel details' => DB::table('rt_hotel_booking_details')
            ->where('booking_id', $bookingId)->count(),

        'Guests' => DB::table('rt_booking_guests')
            ->where('booking_id', $bookingId)->count(),

        'Price snapshots' => DB::table('rt_booking_price_snapshots')
            ->where('booking_id', $bookingId)->count(),
    ];

    echo "\nDATABASE CHECKS:\n";

    foreach ($checks as $name => $count) {
        echo "$name: $count\n";
    }

    $expected = [
        'Order' => 1,
        'Booking' => 1,
        'Hotel details' => 1,
        'Guests' => 2,
        'Price snapshots' => 1,
    ];

    if ($checks !== $expected) {
        throw new RuntimeException('Database record counts do not match.');
    }

    $retry = $service->create(
        $userId,
        $companyId,
        $data,
        $idempotencyKey
    );

    if ($retry !== $result) {
        throw new RuntimeException('Idempotency retry returned a different response.');
    }

    echo "\nIDEMPOTENCY RETRY: PASS\n";
    echo "DATABASE CHECKS: PASS\n";
    echo "BOOKING DRAFT SERVICE: PASS\n";

} catch (Throwable $e) {
    echo "\nTEST FAILED\n";
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;

} finally {
    if ($transactionStarted) {
        DB::rollBack();
        echo "\nDATABASE ROLLBACK: DONE\n";
    }

    Cache::forget($cacheKey);
    echo "TEST CACHE CLEANUP: DONE\n";
}
