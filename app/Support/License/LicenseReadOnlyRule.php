<?php

namespace App\Support\License;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Regla pura de solo lectura por licencia (RN-70).
 *
 * Centraliza exactamente la semántica ya verificada en CFG-001/002/003,
 * sucursales y usuarios: solo `status = suspended` activa la regla; sin
 * `grace_until` es solo lectura inmediata; con gracia, el bloqueo comienza
 * después del fin del día de `grace_until`. Nunca infiere suspensión desde
 * `expires_at`.
 *
 * No abre conexiones ni modifica datos; puede usarse dentro de transacciones
 * sin alterar `lockForUpdate` ni el flujo de permisos.
 */
final class LicenseReadOnlyRule
{
    public static function isReadOnly(?object $license, ?CarbonInterface $now = null): bool
    {
        if ($license === null || (string) ($license->status ?? '') !== 'suspended') {
            return false;
        }

        $graceUntil = $license->grace_until ?? null;

        if ($graceUntil === null) {
            return true;
        }

        $graceEnd = Carbon::parse((string) $graceUntil)->endOfDay();

        return $now === null ? $graceEnd->isPast() : $graceEnd->lt($now);
    }

    /**
     * Suspensión todavía dentro del período de gracia (aviso, sin bloqueo).
     */
    public static function isInGrace(?object $license, ?CarbonInterface $now = null): bool
    {
        if ($license === null || (string) ($license->status ?? '') !== 'suspended') {
            return false;
        }

        if (($license->grace_until ?? null) === null) {
            return false;
        }

        return ! self::isReadOnly($license, $now);
    }
}
