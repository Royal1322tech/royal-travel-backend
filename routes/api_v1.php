<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\B2B\LoyaltyWalletController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\B2B\Auth\B2BLoginController;
use App\Http\Controllers\Api\V1\B2B\Auth\CompanyRegistrationController;
use App\Http\Controllers\Api\V1\B2B\Auth\CompanyEmailVerificationController;
use App\Http\Controllers\Api\V1\B2B\B2BProfileController;
use App\Http\Controllers\Api\V1\B2B\Hotels\TopRatedController;
use App\Http\Controllers\Api\V1\B2B\Hotels\HotelSearchController as B2BHotelSearchController;
use App\Http\Controllers\Api\V1\B2C\Hotels\HotelSearchController as B2CHotelSearchController;
use App\Http\Controllers\Api\V1\B2B\Hotels\HotelRoomsApiController;
use App\Http\Controllers\Api\V1\B2B\Bookings\BookingDraftController;

/*
|--------------------------------------------------------------------------
| Royal Travel API V1
|--------------------------------------------------------------------------
|
| Base URL: /api/v1
|
| B2B Authentication:
| POST /b2b/auth/register
| POST /b2b/auth/login
| POST /b2b/auth/verify-otp
| POST /b2b/auth/resend-verification-otp
| POST /b2b/auth/logout
|
| B2B Profile:
| GET   /b2b/me
| PATCH /b2b/me
|
| B2B Hotels:
| POST /b2b/hotels/search
| GET  /b2b/destinations
| POST /b2b/hotels/{hid}/rooms
| POST /b2b/hotels/rooms/select
| POST /b2b/hotels/rooms/prebook
|
| B2B Bookings:
| POST /b2b/bookings/draft
|
| B2C Hotels:
| POST /b2c/hotels/search
|
| Other:
| GET /topRated
| GET /foundation-check
|
*/

/*
|--------------------------------------------------------------------------
| Top Rated Hotels
|--------------------------------------------------------------------------
*/

Route::get('/topRated', [
    TopRatedController::class,
    'topRated',
])->name('topRated');

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
            ->middleware('throttle:b2b-register')
            ->name('register');

        Route::post('/login', [
            B2BLoginController::class,
            'login',
        ])
            ->middleware('throttle:b2b-login')
            ->name('login');

        Route::post('/verify-otp', [
            CompanyEmailVerificationController::class,
            'verify',
        ])
            ->middleware('throttle:b2b-verify-otp')
            ->name('verify-email');

        Route::post('/resend-verification-otp', [
            CompanyEmailVerificationController::class,
            'resend',
        ])
            ->middleware('throttle:b2b-resend-otp')
            ->name('resend-verification-otp');

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

        Route::patch('/me', [
            B2BProfileController::class,
            'update',
        ])->name('profile.update');
    });

/*
|--------------------------------------------------------------------------
| Foundation Health & Localization
|--------------------------------------------------------------------------
*/

Route::get('/foundation-check', function (Request $request) {
    $locale = $request->header('X-Locale', 'en');

    $locale = in_array($locale, ['ar', 'en'], true)
        ? $locale
        : 'en';

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
    B2CHotelSearchController::class,
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
    B2BHotelSearchController::class,
    '__invoke',
])
    ->middleware([
        'auth:sanctum',
        'b2b.api.permission:hotels.view',
        'throttle:20,1',
    ])
    ->name('api.v1.b2b.hotels.search');

/*
|--------------------------------------------------------------------------
| Destination Autocomplete - B2B
|--------------------------------------------------------------------------
| GET /api/v1/b2b/destinations?q=dub&language=en&limit=10
| Returns destination ids accepted by POST /b2b/hotels/search ("destination").
| Results are cached server-side; clients should still debounce keystrokes.
*/
Route::get('/b2b/destinations', \App\Http\Controllers\Api\V1\B2B\Hotels\DestinationController::class)
    ->middleware([
        'auth:sanctum',
        'b2b.api.permission:hotels.view',
        'throttle:30,1',
    ])
    ->name('api.v1.b2b.destinations.index');

/*
|--------------------------------------------------------------------------
| Hotel Rooms API - B2B
|--------------------------------------------------------------------------
*/

Route::prefix('b2b/hotels')
    ->name('api.v1.b2b.hotels.')
    ->middleware([
        'auth:sanctum',
        'b2b.api.permission:hotels.view',
        'throttle:20,1',
    ])
    ->group(function (): void {

        /*
        |--------------------------------------------------------------
        | Select Room
        |--------------------------------------------------------------
        */

        Route::post('/rooms/select', [
            HotelRoomsApiController::class,
            'select',
        ])->name('rooms.select');

        /*
        |--------------------------------------------------------------
        | Prebook Room
        |--------------------------------------------------------------
        */

        Route::post('/rooms/prebook', [
            HotelRoomsApiController::class,
            'prebook',
        ])->name('rooms.prebook');

        /*
        |--------------------------------------------------------------
        | Get Hotel Rooms
        |--------------------------------------------------------------
        */

        Route::post('/{hid}/rooms', [
            HotelRoomsApiController::class,
            'rooms',
        ])
            ->whereNumber('hid')
            ->name('rooms.index');
    });

/*
|--------------------------------------------------------------------------
| Booking Draft API - B2B
|--------------------------------------------------------------------------
|
| Creates a draft booking from a validated prebook review token.
| No payment or supplier booking confirmation occurs here.
|
*/

Route::prefix('b2b/bookings')
    ->name('api.v1.b2b.bookings.')
    ->middleware([
        'auth:sanctum',
        'b2b.api.permission:hotels.view',
        'throttle:20,1',
    ])
    ->group(function (): void {

        Route::post('/draft', [
            BookingDraftController::class,
            'store',
        ])->name('draft');
    });

/* Price reduction requests: B2B authenticated, tenant-scoped. */
Route::prefix('b2b/price-reduction-requests')
    ->name('api.v1.b2b.price-reduction.')
    ->middleware(['auth:sanctum','b2b.api.permission:hotels.view','throttle:10,1'])
    ->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Api\V1\B2B\Hotels\PriceReductionRequestController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\V1\B2B\Hotels\PriceReductionRequestController::class, 'store'])->name('store');
    });

// B2B company loyalty wallet: read-only, no redemption.
Route::get('/b2b/loyalty', [LoyaltyWalletController::class, 'index'])
    ->middleware(['auth:sanctum', 'b2b.api.permission:dashboard.view', 'throttle:20,1'])
    ->name('api.v1.b2b.loyalty.index');

// Read-only B2B financial account: authenticated and company-scoped inside controller.
Route::get('/b2b/finance', [\App\Http\Controllers\Api\V1\B2B\B2BFinanceController::class,'show'])
    ->middleware(['auth:sanctum','b2b.api.permission:payments.view','throttle:30,1'])->name('api.v1.b2b.finance.show');

// Customer directory. Company scope is resolved server-side, never from request input.
Route::prefix('b2b/customers')
    ->name('api.v1.b2b.customers.')
    ->middleware(['auth:sanctum', 'b2b.api.permission:hotels.view', 'throttle:20,1'])
    ->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Api\V1\B2B\Customers\CustomerController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\V1\B2B\Customers\CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [\App\Http\Controllers\Api\V1\B2B\Customers\CustomerController::class, 'show'])->name('show');
    });

// Company bookings, read-only (drafts and later states). Company scope is resolved server-side.
Route::prefix('b2b/bookings')
    ->name('api.v1.b2b.bookings.read.')
    ->middleware(['auth:sanctum', 'b2b.api.permission:bookings.view', 'throttle:30,1'])
    ->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Api\V1\B2B\Bookings\BookingReadController::class, 'index'])->name('index');
        Route::get('/my-bookings', [\App\Http\Controllers\Api\V1\B2B\Bookings\BookingReadController::class, 'myBookings'])->name('my-bookings');
        Route::get('/my-incomplete', [\App\Http\Controllers\Api\V1\B2B\Bookings\BookingReadController::class, 'myIncomplete'])->name('my-incomplete');
        Route::get('/{booking}', [\App\Http\Controllers\Api\V1\B2B\Bookings\BookingReadController::class, 'show'])->name('show');
    });

Route::post('/b2b/bookings/{booking}/confirm', [\App\Http\Controllers\Api\V1\B2B\Bookings\BookingConfirmationController::class,'confirm'])
    ->middleware(['auth:sanctum','b2b.api.permission:bookings.create','throttle:5,1'])->name('api.v1.b2b.bookings.confirm');
Route::get('/b2b/bookings/{booking}/status', [\App\Http\Controllers\Api\V1\B2B\Bookings\BookingConfirmationController::class,'status'])
    ->middleware(['auth:sanctum','b2b.api.permission:bookings.view','throttle:30,1'])->name('api.v1.b2b.bookings.status');

// Booking support: employee-owned tickets only.
Route::middleware(['auth:sanctum','b2b.api.permission:bookings.view','throttle:30,1'])->group(function(){
 $c=\App\Http\Controllers\Api\V1\B2B\Bookings\SupportTicketController::class;
 Route::get('/b2b/tickets/categories',[$c,'categories']);
 Route::get('/b2b/tickets',[$c,'index']);
 Route::get('/b2b/tickets/{ticket}',[$c,'show'])->whereUuid('ticket');
 Route::get('/b2b/bookings/{booking}/tickets',[$c,'forBooking'])->whereUuid('booking');
 Route::post('/b2b/bookings/{booking}/tickets',[$c,'store'])->whereUuid('booking')->middleware('throttle:5,1');
 Route::post('/b2b/tickets/{ticket}/messages',[$c,'reply'])->whereUuid('ticket')->middleware('throttle:10,1');
});

