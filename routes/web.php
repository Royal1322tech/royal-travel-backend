<?php

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
