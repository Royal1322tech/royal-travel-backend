<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LoyaltyController;

use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminRoleManagementController;
use App\Http\Controllers\Admin\AdminUserManagementController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\Admin\HotelRoomsController;
use App\Http\Controllers\Admin\HotelSearchController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\SupplierController;

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Guest Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('guest')->group(function () {

            Route::get('/login', [
                LoginController::class,
                'showLoginForm',
            ])->name('login');

            Route::post('/login', [
                LoginController::class,
                'login',
            ])
                ->middleware('throttle:5,1')
                ->name('login.submit');
        });

        /*
        |--------------------------------------------------------------------------
        | Authenticated Admin Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('auth')->group(function () {

            Route::post('/logout', [
                LoginController::class,
                'logout',
            ])->name('logout');

            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [
                DashboardController::class,
                'index',
            ])
                ->middleware('admin.permission:dashboard.view')
                ->name('dashboard');

            /*
            |--------------------------------------------------------------------------
            | Bookings
            |--------------------------------------------------------------------------
            */

            Route::get('/bookings', [
                BookingController::class,
                'index',
            ])
                ->middleware('admin.permission:bookings.view')
                ->name('bookings.index');

            /* Price reduction request administration. */
            Route::get('/price-reduction-requests',
                [\App\Http\Controllers\Admin\PriceReductionRequestController::class,'index'])
                ->middleware('admin.permission:price_requests.view')
                ->name('price-reduction.index');
            Route::patch('/price-reduction-requests/{id}',
                [\App\Http\Controllers\Admin\PriceReductionRequestController::class,'update'])
                ->whereUuid('id')
                ->middleware('admin.permission:price_requests.manage')
                ->name('price-reduction.update');
            Route::get('/price-reduction-requests/{id}/attachment',
                [\App\Http\Controllers\Admin\PriceReductionRequestController::class,'attachment'])
                ->whereUuid('id')
                ->middleware('admin.permission:price_requests.view')
                ->name('price-reduction.attachment');

            /*
            |--------------------------------------------------------------------------
            | Suppliers
            |--------------------------------------------------------------------------
            */

            // End customers: company_id omitted selects direct Royal Travel customers.
            Route::get('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])
                ->middleware('admin.permission:bookings.view')->name('customers.index');
            Route::post('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'store'])
                ->middleware(['admin.permission:bookings.manage', 'throttle:20,1'])->name('customers.store');

            Route::get('/suppliers', [
                SupplierController::class,
                'index',
            ])
                ->middleware('admin.permission:suppliers.view')
                ->name('suppliers.index');

            Route::get('/suppliers/{supplier}', [
                SupplierController::class,
                'show',
            ])
                ->middleware('admin.permission:suppliers.view')
                ->name('suppliers.show');

            Route::post('/suppliers/{supplier}/test-connection', [
                SupplierController::class,
                'testConnection',
            ])
                ->middleware([
                    'admin.permission:suppliers.manage',
                    'throttle:3,1',
                ])
                ->name('suppliers.test-connection');

            /*
            |--------------------------------------------------------------------------
            | Companies
            |--------------------------------------------------------------------------
            */

            Route::get('/companies', [
                AdminCompanyController::class,
                'index',
            ])
                ->middleware('admin.permission:companies.view')
                ->name('companies.index');

            Route::get('/companies/create', [
                AdminCompanyController::class,
                'create',
            ])
                ->middleware('admin.permission:companies.create')
                ->name('companies.create');

            Route::post('/companies', [
                AdminCompanyController::class,
                'store',
            ])
                ->middleware('admin.permission:companies.create')
                ->name('companies.store');

            Route::post('/companies/{company}/finance', [\App\Http\Controllers\Admin\B2BFinanceController::class,'update'])
                ->middleware('admin.permission:companies.manage')->name('companies.finance.update');

            Route::get('/companies/{company}', [
                AdminCompanyController::class,
                'show',
            ])
                ->middleware('admin.permission:companies.view')
                ->name('companies.show');

            Route::get('/companies/{company}/edit', [
                AdminCompanyController::class,
                'edit',
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.edit');

            Route::put('/companies/{company}', [
                AdminCompanyController::class,
                'update',
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.update');

            Route::patch('/companies/{company}/deactivate', [
                AdminCompanyController::class,
                'deactivate',
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.deactivate');

            Route::patch('/companies/{company}/activate', [
                AdminCompanyController::class,
                'activate',
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.activate');

            Route::patch('/companies/{company}/approve', [
                AdminCompanyController::class,
                'approve',
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.approve');

            /*
            |--------------------------------------------------------------------------
            | Hotels
            |--------------------------------------------------------------------------
            */

            Route::get('/hotels', [
                HotelSearchController::class,
                'index',
            ])
                ->middleware('admin.permission:hotels.view')
                ->name('hotels.index');

            // Destination autocomplete (JSON) for the Admin hotel search.
            Route::get('/hotels/destinations', \App\Http\Controllers\Admin\HotelDestinationController::class)
                ->middleware(['admin.permission:hotels.view', 'throttle:30,1'])
                ->name('hotels.destinations');

            Route::get('/hotels/rooms/{token}', [
                HotelRoomsController::class,
                'show',
            ])
                ->middleware('admin.permission:hotels.view')
                ->name('hotels.rooms');


            Route::post('/hotels/rooms/{token}/select', [
                HotelRoomsController::class,
                'select',
            ])
                ->middleware('admin.permission:hotels.view')
                ->name('hotels.rooms.select');



Route::get(
    '/hotels/rooms/review/{token}',
    [HotelRoomsController::class, 'review']
)
    ->middleware('admin.permission:hotels.view')
    ->name('hotels.rooms.review');

            // Save a draft only; no wallet debit or supplier booking occurs here.
            Route::post('/hotels/rooms/review/{token}/draft', [
    HotelRoomsController::class,
    'saveDraft',
])
    ->middleware([
        'admin.permission:bookings.create',
        'throttle:5,1',
    ])
    ->name('hotels.rooms.draft');

            // Confirm a hotel booking only through the protected booking workflow.
            // The controller's confirm() method and supplier orchestration must
            // be implemented and tested before enabling the UI action.
            Route::post('/hotels/rooms/review/{token}/confirm', [
                HotelRoomsController::class,
                'confirm',
            ])
                ->middleware([
                    'admin.permission:bookings.manage',
                    'throttle:5,1',
                ])
                ->name('hotels.rooms.confirm');


            /*
            |--------------------------------------------------------------------------
            | Pricing & Commissions
            |--------------------------------------------------------------------------
            */

            // B2B loyalty management (independent of payment and wallet)
            Route::get('/loyalty', [LoyaltyController::class, 'index'])
                ->middleware('admin.permission:loyalty.view')->name('loyalty.index');
            Route::post('/loyalty/rules', [LoyaltyController::class, 'saveRule'])
                ->middleware(['admin.permission:loyalty.manage', 'throttle:10,1'])->name('loyalty.rules.save');
            Route::post('/loyalty/adjust', [LoyaltyController::class, 'adjust'])
                ->middleware(['admin.permission:loyalty.manage', 'throttle:10,1'])->name('loyalty.adjust');

            Route::get('/pricing', [
                PricingController::class,
                'index',
            ])
                ->middleware('admin.permission:pricing.royal.manage')
                ->name('pricing.index');

            Route::post('/pricing', [
                PricingController::class,
                'save',
            ])
                ->middleware('admin.permission:pricing.royal.manage')
                ->name('pricing.save');

            /*
            |--------------------------------------------------------------------------
            | Exchange Rates
            |--------------------------------------------------------------------------
            */

            Route::get('/exchange-rates', [
                ExchangeRateController::class,
                'index',
            ])
                ->middleware('admin.permission:fx.view')
                ->name('exchange-rates.index');

            Route::post('/exchange-rates', [
                ExchangeRateController::class,
                'save',
            ])
                ->middleware('admin.permission:fx.manage')
                ->name('exchange-rates.save');

            /*
            |--------------------------------------------------------------------------
            | Admin Users Management
            |--------------------------------------------------------------------------
            */

            Route::get('/users', [
                AdminUserManagementController::class,
                'index',
            ])
                ->middleware('admin.permission:users.view')
                ->name('users.index');

            Route::get('/users/create', [
                AdminUserManagementController::class,
                'create',
            ])
                ->middleware('admin.permission:users.manage')
                ->name('users.create');

            Route::post('/users', [
                AdminUserManagementController::class,
                'store',
            ])
                ->middleware('admin.permission:users.manage')
                ->name('users.store');

            Route::get('/users/{user}/edit', [
                AdminUserManagementController::class,
                'edit',
            ])
                ->middleware('admin.permission:users.manage')
                ->name('users.edit');

            Route::put('/users/{user}', [
                AdminUserManagementController::class,
                'update',
            ])
                ->middleware('admin.permission:users.manage')
                ->name('users.update');

            /*
            |--------------------------------------------------------------------------
            | Admin Roles & Permissions
            |--------------------------------------------------------------------------
            */

            Route::get('/roles', [
                AdminRoleManagementController::class,
                'index',
            ])
                ->middleware('admin.permission:roles.view')
                ->name('roles.index');

            Route::post('/roles', [
                AdminRoleManagementController::class,
                'store',
            ])
                ->middleware('admin.permission:roles.manage')
                ->name('roles.store');

            Route::put('/roles/{role}', [
                AdminRoleManagementController::class,
                'update',
            ])
                ->middleware('admin.permission:roles.manage')
                ->name('roles.update');

            /*
            |--------------------------------------------------------------------------
            | Local RateHawk SSL Diagnostic
            |--------------------------------------------------------------------------
            */

            Route::get('/ratehawk-ssl-diagnostic', function () {

                abort_unless(
                    app()->environment('local'),
                    404
                );

                $caPath = 'C:/certificates/cacert.pem';

                return response()->json([
                    'php_version' => PHP_VERSION,
                    'php_ini' => php_ini_loaded_file(),
                    'curl_cainfo' => ini_get('curl.cainfo'),
                    'openssl_cafile' => ini_get('openssl.cafile'),
                    'ca_file_exists' => is_file($caPath),
                    'ca_file_readable' => is_readable($caPath),
                ]);
            })
                ->middleware('admin.permission:suppliers.manage')
                ->name('ratehawk.ssl-diagnostic');




            Route::post('/hotels/rooms/{token}/prebook', [
                HotelRoomsController::class,
                'prebook',
            ])
                ->middleware('admin.permission:hotels.view')
                ->name('hotels.rooms.prebook');

        });
    });
