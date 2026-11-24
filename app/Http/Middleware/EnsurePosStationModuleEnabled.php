<?php

namespace App\Http\Middleware;

use App\Support\PosStations\PosStationModuleGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea todas las rutas de estaciones POS cuando el módulo `pos` está
 * deshabilitado. Es adicional a RBAC y licencia: la habilitación técnica nunca
 * concede permisos ni derechos comerciales.
 */
class EnsurePosStationModuleEnabled
{
    public function __construct(private readonly PosStationModuleGate $modules) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo POS no está habilitado.');
        }

        return $next($request);
    }
}
