<?php

namespace App\Support\PosStations;

use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guard de escritura de estaciones POS (RN-70).
 *
 * Reutiliza la semántica verificada de los guards de inventario, clientes y
 * vendedores: una licencia suspendida fuera de gracia bloquea mutaciones con
 * 422. Las lecturas no pasan por este guard. Debe invocarse dentro de la
 * transacción de la mutación para serializar la lectura de `license_states`.
 */
final class PosStationWriteGuard
{
    public function assertWritable(): void
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (LicenseReadOnlyRule::isReadOnly($license)) {
            throw ValidationException::withMessages([
                'read_only' => 'La licencia está suspendida fuera del período de gracia; las estaciones POS están en modo solo lectura.',
            ]);
        }
    }

    /**
     * Lectura sin bloqueo para props de pantalla.
     */
    public function isReadOnly(): bool
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->first();

        return LicenseReadOnlyRule::isReadOnly($license);
    }
}
