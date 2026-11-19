<?php

namespace App\Support\Inventory;

use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guard de escritura de inventario (RN-70).
 *
 * Copia la semantica ya verificada de CatalogWriteGuard: una licencia
 * suspendida fuera de gracia bloquea mutaciones. Debe invocarse dentro de la
 * transaccion de la mutacion para serializar la lectura de `license_states`.
 * La lectura de stock no pasa por este guard.
 */
final class InventoryWriteGuard
{
    public function assertWritable(): void
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (LicenseReadOnlyRule::isReadOnly($license)) {
            throw ValidationException::withMessages([
                'read_only' => 'La licencia está suspendida fuera del período de gracia; el inventario está en modo solo lectura.',
            ]);
        }
    }

    /**
     * Lectura sin bloqueo: se usa para props de pantalla y no debe abrir un
     * lock fuera de una transaccion de escritura.
     */
    public function isReadOnly(): bool
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->first();

        return LicenseReadOnlyRule::isReadOnly($license);
    }
}
