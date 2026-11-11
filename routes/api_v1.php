<?php

use App\Http\Controllers\Api\V1\B2B\Auth\B2BLoginController;
use App\Http\Controllers\Api\V1\B2B\Auth\CompanyRegistrationController;
use App\Http\Controllers\Api\V1\B2B\B2BProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Royal Travel API V1 - B2B
|--------------------------------------------------------------------------
|
| Base URL: /api/v1
|
| Authentication:
| POST /b2b/auth/register
| POST /b2b/auth/login
| POST /b2b/auth/verify-otp
| POST /b2b/auth/logout
|
| Company Profile:
| GET /b2b/me
|
*/

/*
|--------------------------------------------------------------------------
| B2B Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('b2b/auth')
    ->name('api.v1.b2b.auth.')
    ->group(function (): void {

        Route::post('/register', [
            CompanyRegistrationController::class,
            'register',
        ])
            ->middleware('throttle:10,60')
            ->name('register');

        Route::post('/login', [
            B2BLoginController::class,
            'login',
        ])
            ->middleware('throttle:5,1')
            ->name('login');

        Route::post('/verify-otp', [
            B2BLoginController::class,
            'verifyOtp',
        ])
            ->middleware('throttle:5,1')
            ->name('verify-otp');

        Route::post('/logout', [
            B2BLoginController::class,
            'logout',
        ])
            ->middleware('auth:sanctum')
            ->name('logout');
    });

/*
|--------------------------------------------------------------------------
| B2B Authenticated Profile
|--------------------------------------------------------------------------
*/

Route::prefix('b2b')
    ->name('api.v1.b2b.')
    ->middleware('auth:sanctum')
    ->group(function (): void {

        Route::get('/me', [
            B2BProfileController::class,
            'me',
        ])->name('me');
    });


// Foundation health and localization check.
Route::get('/foundation-check', function (\Illuminate\Http\Request $request) {
    $locale = $request->header('X-Locale', 'en');
    $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

    return response()->json([
        'locale' => $locale,
        'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
    ]);
})->name('api.v1.foundation-check');







/*
|--------------------------------------------------------------------------
| Hotel Search API - B2C
|--------------------------------------------------------------------------
*/

Route::post('/b2c/hotels/search', [
    \App\Http\Controllers\Api\V1\B2C\Hotels\HotelSearchController::class,
    '__invoke',
])
    ->middleware('throttle:10,1')
    ->name('api.v1.b2c.hotels.search');


/*
|--------------------------------------------------------------------------
| Hotel Search API - B2B
|--------------------------------------------------------------------------
*/

Route::post('/b2b/hotels/search', [
    \App\Http\Controllers\Api\V1\B2B\Hotels\HotelSearchController::class,
    '__invoke',
])
    ->middleware([
        'auth:sanctum',
        'b2b.api.permission:hotels.view',
        'throttle:20,1',
    ])
    ->name('api.v1.b2b.hotels.search');

