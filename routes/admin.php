<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\Auth\LoginController;


Route::prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::middleware('guest')->group(function () {
            Route::get('/login', [LoginController::class, 'showLoginForm'])
                ->name('login');

            Route::post('/login', [LoginController::class, 'login'])
                ->middleware('throttle:5,1')
                ->name('login.submit');
        });

        Route::middleware('auth')->group(function () {

            Route::post('/logout', [LoginController::class, 'logout'])
                ->name('logout');

            Route::get('/dashboard', [DashboardController::class, 'index'])
                ->middleware('admin.permission:dashboard.view')
                ->name('dashboard');

            Route::get('/bookings', [BookingController::class, 'index'])
                ->middleware('admin.permission:bookings.view')
                ->name('bookings.index');
        });
    });
