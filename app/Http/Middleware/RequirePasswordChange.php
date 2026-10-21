<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige al cambio de contraseña cuando el usuario debe cambiarla.
 *
 * La ruta de cambio de contraseña está excluida de la redirección para evitar
 * un bucle infinito cuando must_change_password=true.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            Auth::check()
            && Auth::user()->mustChangePassword()
            && !$request->routeIs('password.change', 'password.update')
        ) {
            return redirect()->route('password.change')->with('warning', 'debe_cambiar_contrasena');
        }

        return $next($request);
    }
}
