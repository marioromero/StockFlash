<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\DB;

/**
 * Habilitacion tecnica del inventario.
 *
 * En la Parte 2 los permisos `inventory.*` pertenecen al modulo funcional
 * `inventory`, pero `installation_modules` registra modulos comerciales
 * (`core`, `pos`, `bodega`, ...). Regla aplicada:
 * - Si existe una fila tecnica `inventory`, manda su `is_enabled`.
 * - Si no existe, el inventario se considera disponible cuando `pos` o
 *   `bodega` estan habilitados (es una funcion de ambos productos).
 * - Sin filas conocidas, queda deshabilitado (fail-closed).
 *
 * Esta comprobacion es adicional a RBAC, sucursal y licencia; nunca la
 * reemplaza.
 */
final class InventoryModuleGate
{
    public function isEnabled(): bool
    {
        $rows = DB::table('installation_modules')
            ->whereIn('module_code', ['inventory', 'pos', 'bodega'])
            ->get(['module_code', 'is_enabled']);

        $enabled = [];

        foreach ($rows as $row) {
            $enabled[(string) $row->module_code] = (bool) $row->is_enabled;
        }

        if (array_key_exists('inventory', $enabled)) {
            return $enabled['inventory'];
        }

        return ($enabled['pos'] ?? false) || ($enabled['bodega'] ?? false);
    }
}
