

<?php

Route::get('/', fn () => response()->view('welcome'));

use Illuminate\Support\Facades\Route;

Route::get('/admin/foundation-check', function () {
    return response()->view('foundation.check', [
        'locale' => app()->getLocale(),
        'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
    ]);
});


Route::get('/bookings-test', function () {
        return response()->json([
            'success' => true,
            'message' => 'Permission granted.',
        ]);
    });

   Route::middleware([
    'auth',
    'company.permission:bookings.view',
])->prefix('b2b')->group(function () {
    Route::get('/bookings-test', function () {
        return response()->json([
            'success' => true,
            'message' => 'B2B route registered successfully',
        ]);
    });
});

// Temporary B2B Dashboard UI preview — dummy data only.
Route::view('/ui-preview/b2b/dashboard', 'b2b.dashboard.index');

// Temporary Hotel Offline Requests UI preview — dummy data only.
Route::view('/ui-preview/b2b/bookings', 'b2b.bookings.index');

// Temporary Booking Detail UI preview — dummy data only.
Route::get('/ui-preview/b2b/bookings/{bookingId}', function (string $bookingId) {
    abort_unless(
        in_array($bookingId, ['RB-1048', 'RB-1049', 'RB-1050', 'RB-1051', 'RB-1052'], true),
        404
    );

    return view('b2b.bookings.show', [
        'bookingId' => $bookingId,
    ]);
});

// Temporary Main Bookings UI preview — dummy data only.
Route::view('/ui-preview/b2b/all-bookings', 'b2b.bookings.all');

// Temporary Hotels UI preview — dummy data only.
Route::view('/ui-preview/b2b/hotels', 'b2b.hotels.index');
