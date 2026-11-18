<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed del instalador (INSTALL-001 · CORE-17).
 *
 * Se ejecuta antes de resolver el FormRequest y del controlador para que una
 * instalación deshabilitada responda 404 incluso ante payloads inválidos.
 */
class EnsureInstallerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('install.bootstrap_token');
        $minimum = max(1, (int) config('install.min_bootstrap_token_length', 32));

        if (
            ! (bool) config('install.enabled', false)
            || ! is_string($token)
            || strlen(trim($token)) < $minimum
        ) {
            abort(404);
        }

        return $next($request);
    }
}
