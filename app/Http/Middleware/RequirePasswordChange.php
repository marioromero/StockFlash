<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige al cambio de contraseña cuando el usuario debe cambiarla.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->mustChangePassword()) {
            return redirect()->route('password.change')->with('warning', 'debe_cambiar_contrasena');
        }

        return $next($request);
    }
}
