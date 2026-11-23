<?php

namespace App\Support\Sellers;

use Illuminate\Support\Facades\DB;

/**
 * Habilitación técnica de vendedores (fail-closed).
 *
 * Regla:
 * - Si existe una fila explícita `sellers` en `installation_modules`, manda
 *   su `is_enabled`.
 * - Si no existe, se considera disponible solo cuando `pos` está habilitado
 *   (vendedores es una capacidad del producto POS).
 * - Sin fila explícita y sin `pos`, queda deshabilitado.
 *
 * Es adicional a RBAC y licencia; nunca reemplaza esas comprobaciones.
 */
final class SellerModuleGate
{
    public function isEnabled(): bool
    {
        $rows = DB::table('installation_modules')
            ->whereIn('module_code', ['sellers', 'pos'])
            ->get(['module_code', 'is_enabled']);

        $enabled = [];

        foreach ($rows as $row) {
            $enabled[(string) $row->module_code] = (bool) $row->is_enabled;
        }

        if (array_key_exists('sellers', $enabled)) {
            return $enabled['sellers'];
        }

        return $enabled['pos'] ?? false;
    }
}
