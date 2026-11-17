<?php

namespace App\Support\Catalog;

use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guard de escritura del catálogo (RN-70).
 *
 * Reutiliza la semántica ya verificada de `LicenseReadOnlyRule`: una licencia
 * suspendida fuera de gracia bloquea escrituras. Debe invocarse dentro de la
 * transacción de la mutación para serializar la lectura de `license_states`.
 */
final class CatalogWriteGuard
{
    public function assertWritable(): void
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (LicenseReadOnlyRule::isReadOnly($license)) {
            throw ValidationException::withMessages([
                'read_only' => 'La licencia está suspendida fuera del período de gracia; el sistema está en modo solo lectura.',
            ]);
        }
    }

    public function isReadOnly(): bool
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->first();

        return LicenseReadOnlyRule::isReadOnly($license);
    }
}
