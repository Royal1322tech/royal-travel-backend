<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
         api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
         then: function (): void {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(base_path('routes/admin.php'));
    },
    )
    ->withMiddleware(function (Middleware $middleware): void {

    $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);
    $middleware->api(append: [\App\Http\Middleware\SetLocale::class]);

    $middleware->alias([
        'company.permission' => \App\Http\Middleware\EnsureCompanyPermission::class,
        'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
    ]);

    $middleware->redirectGuestsTo(function ($request) {
        if ($request->is('b2b/*')) {
            abort(401, 'Unauthenticated.');
        }

        return route('login');
    });

    $middleware->redirectGuestsTo(function ($request) {
    if ($request->is('b2b/*') || $request->is('admin/*')) {
        abort(401, 'Unauthenticated.');
    }

    return route('login');
});

})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
