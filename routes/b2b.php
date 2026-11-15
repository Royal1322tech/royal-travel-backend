<?php

use App\Http\Controllers\B2B\Auth\PasswordSetupController;
use App\Http\Controllers\B2B\Auth\DashboardLoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\B2B\HotelSearchController;
use App\Http\Controllers\B2B\PricingController;

// Add this group to routes/b2b.php (outside existing route groups):
Route::prefix('b2b')
    ->name('b2b.')
    ->middleware(['auth', 'company.permission:pricing.company.manage'])
    ->group(function (): void {
        Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');
        Route::post('/pricing', [PricingController::class, 'save'])
            ->middleware('throttle:10,1')->name('pricing.save');
    });
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
                ->middleware('throttle:10,1')
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
