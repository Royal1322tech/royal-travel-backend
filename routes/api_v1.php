
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
            ->middleware('throttle:3,60')
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
