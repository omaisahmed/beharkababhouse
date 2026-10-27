<?php

use App\Http\Middleware\ApplyRestaurantTimezone;
use App\Http\Middleware\EnsureApplicationIsInstalled;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            append: [ApplyRestaurantTimezone::class],
            prepend: [EnsureApplicationIsInstalled::class],
        );

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureApplicationIsInstalled::class,
        );

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
