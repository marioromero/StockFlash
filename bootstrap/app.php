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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            // SEC-05: el fingerprint debe verificarse antes de cualquier
            // redirección de cambio de contraseña; si no, una sesión invalidada
            // podría navegar a /password/change sin ser rechazada.
            \App\Http\Middleware\VerifySession::class,
            \App\Http\Middleware\RequirePasswordChange::class,
        ]);

        $middleware->alias([
            'branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
            'can.permission' => \App\Http\Middleware\CheckPermission::class,
            'password.change' => \App\Http\Middleware\RequirePasswordChange::class,
            'verify.session' => \App\Http\Middleware\VerifySession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
