<?php

use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureB2BApiPermission;
use App\Http\Middleware\EnsureCompanyPermission;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        then: function (): void {

            // Admin Routes
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            // B2B Routes
            Route::middleware('web')
                ->group(base_path('routes/b2b.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: array_filter(explode(',', env('TRUSTED_PROXIES', '172.16.1.0/24')))
        );


        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'company.permission' => EnsureCompanyPermission::class,

            'admin.permission' => EnsureAdminPermission::class,

            'b2b.api.permission' => EnsureB2BApiPermission::class,
        ]);

        $middleware->redirectUsersTo(function ($request) {
            if ($request->is('admin/*')) {
                return route('admin.dashboard');
            }
            return '/';
        });

        $middleware->redirectGuestsTo(function ($request) {

            if ($request->is('admin/*')) {
                return route('admin.login');
            }

            if ($request->is('b2b/*')) {
                return route('b2b.login');
            }

            return route('login');
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
