<?php

namespace App\Http\Middleware;

use App\Support\Sellers\SellerModuleGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea todas las rutas de vendedores cuando el módulo está deshabilitado.
 *
 * Es adicional a `sellers.manage` y a la licencia: la habilitación del módulo
 * nunca concede permisos ni derechos comerciales.
 */
class EnsureSellerModuleEnabled
{
    public function __construct(private readonly SellerModuleGate $modules) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de vendedores no está habilitado.');
        }

        return $next($request);
    }
}
