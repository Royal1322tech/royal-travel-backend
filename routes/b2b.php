<?php

use App\Http\Controllers\B2B\Auth\PasswordSetupController;
use App\Http\Controllers\B2B\Auth\DashboardLoginController;
use App\Http\Controllers\B2B\HotelSearchController;
use App\Http\Controllers\B2B\PricingController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\B2B\CompanyPortal\AuthController;
use App\Http\Controllers\B2B\CompanyPortal\DashboardController;
use App\Http\Controllers\B2B\CompanyPortal\EmployeeController;
use App\Http\Controllers\B2B\CompanyPortal\MarkupController;
use App\Http\Middleware\EnsureCompanyPortalOwner;
use App\Http\Controllers\B2B\CompanyPortal\BookingController;
use App\Http\Controllers\B2B\CompanyPortal\CustomerController;
use App\Http\Controllers\B2B\CompanyPortal\CompanyProfileController;

/*
|--------------------------------------------------------------------------
| B2B Company Portal
|--------------------------------------------------------------------------
*/

Route::prefix('b2b/company')
    ->name('company-portal.')
    ->group(function () {

        Route::get('/login', [
            AuthController::class,
            'show'
        ])->name('login');

        Route::post('/login', [
            AuthController::class,
            'login'
        ])
            ->middleware('throttle:5,1')
            ->name('login.submit');

        Route::middleware(EnsureCompanyPortalOwner::class)
            ->group(function () {


            /*
|--------------------------------------------------------------------------
| Company Customers
|--------------------------------------------------------------------------
*/

Route::get('/customers', [
    CustomerController::class,
    'index'
])->name('customers.index');

Route::get('/customers/{id}', [
    CustomerController::class,
    'show'
])->whereUuid('id')->name('customers.show');

/*
|--------------------------------------------------------------------------
| Company Profile
|--------------------------------------------------------------------------
*/

Route::get('/profile', [
    CompanyProfileController::class,
    'index'
])->name('profile.index');


            /*
|--------------------------------------------------------------------------
| Company Bookings
|--------------------------------------------------------------------------
*/

Route::middleware('company.permission:bookings.view')
    ->group(function () {

        Route::get('/bookings', [
            BookingController::class,
            'index'
        ])->name('bookings.index');

        Route::get('/bookings/{id}', [
            BookingController::class,
            'show'
        ])
            ->whereUuid('id')
            ->name('bookings.show');

    });

                Route::get('/dashboard', [
                    DashboardController::class,
                    'index'
                ])->name('dashboard');

                Route::get('/employees', [
                    EmployeeController::class,
                    'index'
                ])->name('employees.index');

                Route::post('/employees', [
                    EmployeeController::class,
                    'store'
                ])->name('employees.store');

                /*
                |--------------------------------------------------------------------------
                | Company Markup
                |--------------------------------------------------------------------------
                */

                Route::middleware(
                    'company.permission:pricing.company.manage'
                )->group(function () {

                    Route::get('/markup', [
                        MarkupController::class,
                        'index'
                    ])->name('markup.index');

                    Route::post('/markup', [
                        MarkupController::class,
                        'save'
                    ])
                        ->middleware('throttle:10,1')
                        ->name('markup.save');

                });

                Route::post('/logout', [
                    AuthController::class,
                    'logout'
                ])->name('logout');

            });
    });

/*
|--------------------------------------------------------------------------
| B2B Pricing
|--------------------------------------------------------------------------
*/

Route::prefix('b2b')
    ->name('b2b.')
    ->middleware([
        'auth',
        'company.permission:pricing.company.manage',
    ])
    ->group(function (): void {

        Route::get('/pricing', [
            PricingController::class,
            'index',
        ])->name('pricing.index');

        Route::post('/pricing', [
            PricingController::class,
            'save',
        ])
            ->middleware('throttle:10,1')
            ->name('pricing.save');
    });

/*
|--------------------------------------------------------------------------
| B2B Authentication & Foundation
|--------------------------------------------------------------------------
*/

Route::prefix('b2b')
    ->name('b2b.')
    ->group(function (): void {

        Route::view(
            '/foundation-check',
            'foundation.check',
            ['portal' => 'B2B']
        )->name('foundation.check');

        Route::middleware('guest')->group(function (): void {

            Route::get('/login', [
                DashboardLoginController::class,
                'showLogin',
            ])->name('login');

            Route::post('/login', [
                DashboardLoginController::class,
                'login',
            ])
                ->middleware('throttle:5,1')
                ->name('login.submit');
        });

        Route::middleware('auth')->post(
            '/logout',
            [
                DashboardLoginController::class,
                'logout',
            ]
        )->name('logout');

        Route::middleware('guest')->group(function (): void {

            Route::get('/reset-password/{token}', [
                PasswordSetupController::class,
                'show',
            ])->name('password.reset');

            Route::post('/reset-password', [
                PasswordSetupController::class,
                'update',
            ])
                ->middleware('throttle:10,1')
                ->name('password.update');
        });
    });

/*
|--------------------------------------------------------------------------
| B2B Hotels Dashboard
|--------------------------------------------------------------------------
*/

Route::prefix('b2b')
    ->name('b2b.')
    ->middleware([
        'auth',
        'company.permission:hotels.view',
    ])
    ->group(function (): void {

        Route::get('/hotels', [
            HotelSearchController::class,
            'index',
        ])->name('hotels.index');
    });