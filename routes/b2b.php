<?php

use App\Http\Controllers\B2B\Auth\PasswordSetupController;
use App\Http\Controllers\B2B\Auth\DashboardLoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\B2B\HotelSearchController;

Route::prefix('b2b')
    ->name('b2b.')
    ->group(function (): void {

        Route::view(
            '/foundation-check',
            'foundation.check',
            ['portal' => 'B2B']
        )->name('foundation.check');

        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [DashboardLoginController::class, 'showLogin'])
                ->name('login');

            Route::post('/login', [DashboardLoginController::class, 'login'])
                ->middleware('throttle:5,1')
                ->name('login.submit');

            Route::get('/verify-otp', [DashboardLoginController::class, 'showOtp'])
                ->name('otp.show');

            Route::post('/verify-otp', [DashboardLoginController::class, 'verifyOtp'])
                ->middleware('throttle:5,1')
                ->name('otp.verify');
        });

        Route::middleware('auth')->post(
            '/logout',
            [DashboardLoginController::class, 'logout']
        )->name('logout');

        Route::middleware('guest')->group(function (): void {

            Route::get(
                '/reset-password/{token}',
                [PasswordSetupController::class, 'show']
            )->name('password.reset');

            Route::post(
                '/reset-password',
                [PasswordSetupController::class, 'update']
            )
                ->middleware('throttle:5,1')
                ->name('password.update');
        });
    });







Route::prefix('b2b')
    ->name('b2b.')
    ->middleware(['auth', 'company.permission:hotels.view'])
    ->group(function (): void {
        Route::get('/hotels', [
            HotelSearchController::class,
            'index',
        ])->name('hotels.index');
    });
