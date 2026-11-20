<?php

namespace App\Support\Customers;

use Illuminate\Support\Facades\DB;

/**
 * Habilitación técnica de clientes (fail-closed).
 *
 * Regla:
 * - Si existe una fila explícita `customers` en `installation_modules`, manda
 *   su `is_enabled`.
 * - Si no existe, los clientes se consideran disponibles solo cuando `pos`
 *   está habilitado (los clientes son una capacidad del producto POS).
 * - Sin fila explícita y sin `pos`, queda deshabilitado.
 *
 * Es adicional a RBAC y licencia; nunca reemplaza esas comprobaciones.
 */
final class CustomerModuleGate
{
    public function isEnabled(): bool
    {
        $rows = DB::table('installation_modules')
            ->whereIn('module_code', ['customers', 'pos'])
            ->get(['module_code', 'is_enabled']);

        $enabled = [];

        foreach ($rows as $row) {
            $enabled[(string) $row->module_code] = (bool) $row->is_enabled;
        }

        if (array_key_exists('customers', $enabled)) {
            return $enabled['customers'];
        }

        return $enabled['pos'] ?? false;
    }
}
