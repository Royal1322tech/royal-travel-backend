<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
         api: __DIR__.'/../routes/api_v1.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

    $middleware->alias([
        'company.permission' => \App\Http\Middleware\EnsureCompanyPermission::class,
    ]);

    $middleware->redirectGuestsTo(function ($request) {
        if ($request->is('b2b/*')) {
            abort(401, 'Unauthenticated.');
        }

        return route('login');
    });

})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
