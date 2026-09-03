<?php

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
        // Appended to the group (not declared on routes) so it also covers
        // Livewire's self-registered POST /livewire/update endpoint — see
        // AppServiceProvider::boot() for why. Limiter defined there.
        $middleware->appendToGroup('web', 'throttle:game');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
