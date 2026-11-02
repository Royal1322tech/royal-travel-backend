<?php

use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\HotelSearchController;

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

            Route::get('/companies', [
                AdminCompanyController::class, 'index'
            ])
                ->middleware('admin.permission:companies.view')
                ->name('companies.index');

            Route::get('/companies/create', [
                AdminCompanyController::class, 'create'
            ])
                ->middleware('admin.permission:companies.create')
                ->name('companies.create');

            Route::post('/companies', [
                AdminCompanyController::class, 'store'
            ])
                ->middleware('admin.permission:companies.create')
                ->name('companies.store');

            Route::get('/companies/{company}', [
                AdminCompanyController::class, 'show'
            ])
                ->middleware('admin.permission:companies.view')
                ->name('companies.show');

            Route::get('/companies/{company}/edit', [
                AdminCompanyController::class, 'edit'
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.edit');

            Route::put('/companies/{company}', [
                AdminCompanyController::class, 'update'
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.update');

            Route::patch('/companies/{company}/deactivate', [
                AdminCompanyController::class, 'deactivate'
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.deactivate');

            Route::patch('/companies/{company}/activate', [
                AdminCompanyController::class, 'activate'
            ])
                ->middleware('admin.permission:companies.manage')
                ->name('companies.activate');
           Route::patch('/companies/{company}/approve', [
    AdminCompanyController::class,
    'approve',
])




    ->middleware('admin.permission:companies.manage')
    ->name('companies.approve');   
    
    Route::get('/hotels', [
    HotelSearchController::class,
    'index',
])
    ->middleware('admin.permission:hotels.view')
    ->name('hotels.index');

    Route::get('/ratehawk-ssl-diagnostic', function () {
    abort_unless(app()->environment('local'), 404);

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

        });



        Route::get(
    '/hotels/rooms/{token}',
    [\App\Http\Controllers\Admin\HotelRoomsController::class, 'show']
)->name('hotels.rooms');
    });
