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
        // INSTALL-001 · B-02: las rutas del instalador usan sesión/caché
        // dedicadas antes de que exista el esquema. Debe ir antes de
        // StartSession; solo actúa sobre nombres exactos install.*.
        $middleware->prependToGroup('web', \App\Http\Middleware\UseInstallStores::class);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            // SEC-05: el fingerprint debe verificarse antes de cualquier
            // redirección de cambio de contraseña; si no, una sesión invalidada
            // podría navegar a /password/change sin ser rechazada.
            \App\Http\Middleware\VerifySession::class,
            // SETUP-001: el guard de primer inicio va después de verificar la
            // sesión y antes de exigir cambio de contraseña.
            \App\Http\Middleware\EnsureSetupCompleted::class,
            \App\Http\Middleware\RequirePasswordChange::class,
        ]);

        $middleware->alias([
            'branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
            'can.permission' => \App\Http\Middleware\CheckPermission::class,
            'customers.module' => \App\Http\Middleware\EnsureCustomerModuleEnabled::class,
            'password.change' => \App\Http\Middleware\RequirePasswordChange::class,
            'verify.session' => \App\Http\Middleware\VerifySession::class,
        ]);

        // SETUP-001: el guard debe ejecutarse después de VerifySession y antes
        // del middleware `auth` de rutas. Sin esta prioridad, `auth` (que sí
        // está en la lista de prioridad de Laravel) se adelanta al guard y las
        // rutas privadas responderían con el redirect de login.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnsureSetupCompleted::class,
        );
        $middleware->prependToPriorityList(
            \App\Http\Middleware\EnsureSetupCompleted::class,
            \App\Http\Middleware\VerifySession::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // SETUP-001 · M-01: las credenciales del Admin Central nunca deben
        // quedar en el old input/flash de sesión ante un error de validación.
        $exceptions->dontFlash([
            'admin_password',
            'admin_password_confirmation',
            // INSTALL-001: el token de arranque y la contraseña de BD nunca
            // se conservan en old input/flash tras un error de validación.
            'bootstrap_token',
            'db_password',
            // CUSCORE-001: el RUT es PII y no debe quedar en old input/flash
            // tras un error de validación o búsqueda.
            'rut',
        ]);
    })->create();
