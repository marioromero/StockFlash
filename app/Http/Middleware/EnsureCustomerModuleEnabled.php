<?php

namespace App\Http\Middleware;

use App\Support\Customers\CustomerModuleGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea todas las rutas de clientes cuando el módulo está deshabilitado.
 *
 * Es adicional a `can.permission` y a la licencia: la habilitación del módulo
 * nunca concede permisos ni derechos comerciales.
 */
class EnsureCustomerModuleEnabled
{
    public function __construct(private readonly CustomerModuleGate $modules) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de clientes no está habilitado.');
        }

        return $next($request);
    }
}
