<?php

namespace App\Support\PosStations;

use Illuminate\Support\Facades\DB;

/**
 * Habilitación técnica de estaciones POS (fail-closed).
 *
 * Las estaciones no son un producto comercial independiente: dependen de la
 * fila explícita `pos` en `installation_modules`. Si la fila no existe o está
 * deshabilitada, todas las rutas quedan bloqueadas. Esta comprobación es
 * adicional a RBAC y licencia; nunca concede permisos ni derechos comerciales.
 */
final class PosStationModuleGate
{
    public function isEnabled(): bool
    {
        $row = DB::table('installation_modules')
            ->where('module_code', 'pos')
            ->first(['is_enabled']);

        return $row !== null && (bool) $row->is_enabled;
    }
}
