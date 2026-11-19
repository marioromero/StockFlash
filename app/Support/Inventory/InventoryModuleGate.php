<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\DB;

/**
 * Habilitacion tecnica del inventario.
 *
 * En la Parte 2 los permisos `inventory.*` pertenecen al modulo funcional
 * `inventory`, y `App\Support\Modules\ModuleCatalog` define ese codigo tecnico
 * `inventory`. Sin embargo, el seed aprobado de `installation_modules` escribe
 * codigos de producto comercial (`core`, `pos`, `bodega`, `syncwp`, `fiscal`,
 * `compras`, `analitica`, `control`) y no crea una fila comercial `inventory`.
 * Regla aplicada, fail-closed:
 * - Si existe una fila explicita `inventory` (por ejemplo, habilitada por
 *   pruebas o por una instalacion que la agregue), manda su `is_enabled`.
 * - Si no existe, el inventario se considera disponible cuando `pos` o
 *   `bodega` estan habilitados (es una funcion de ambos productos).
 * - Si no hay ninguna de esas filas, queda deshabilitado.
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
