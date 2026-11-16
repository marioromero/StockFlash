<?php

namespace App\Support\License;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Resolver de solo lectura del estado local de licencia (CORE-08 · RN-70).
 *
 * Lee una única fila de `license_states` seleccionando solo las columnas
 * necesarias para estado y fechas. No expone `installation_key`,
 * `signed_payload`, `signature`, límites ni datos personales, y jamás crea o
 * modifica la fila.
 *
 * - Sin fila: estado informativo «sin licencia configurada»; NO concede
 *   permisos de creación (los guards existentes siguen bloqueando altas).
 * - `active`: sin banner.
 * - `expiring_soon`: aviso, sin bloqueo.
 * - `suspended` con gracia futura: aviso, sin bloqueo todavía.
 * - `suspended` sin gracia o con gracia vencida: solo lectura.
 * - `expires_at` nunca se usa para inferir suspensión.
 */
class LicenseStateResolver
{
    /**
     * Columnas seleccionadas: solo estado y fechas, nunca secretos.
     *
     * @var list<string>
     */
    private const COLUMNS = ['status', 'expires_at', 'grace_until'];

    public function resolve(?CarbonInterface $now = null): LicenseSnapshot
    {
        $row = DB::table('license_states')
            ->orderBy('id')
            ->first(self::COLUMNS);

        if ($row === null) {
            return new LicenseSnapshot(false, null, false, false, null, null);
        }

        $license = (object) [
            'status' => (string) $row->status,
            'expires_at' => $row->expires_at,
            'grace_until' => $row->grace_until,
        ];

        return new LicenseSnapshot(
            true,
            $license->status,
            LicenseReadOnlyRule::isReadOnly($license, $now),
            LicenseReadOnlyRule::isInGrace($license, $now),
            $this->date($license->expires_at),
            $this->date($license->grace_until),
        );
    }

    public function isReadOnly(?CarbonInterface $now = null): bool
    {
        return $this->resolve($now)->readOnly;
    }

    /**
     * Aviso sanitizado para el dashboard, o null cuando no corresponde banner.
     *
     * @return array{type: string, severity: string, message: string, date: ?string, date_label: ?string}|null
     */
    public function dashboardNotice(?CarbonInterface $now = null): ?array
    {
        $snapshot = $this->resolve($now);

        if (! $snapshot->configured) {
            return [
                'type' => 'unconfigured',
                'severity' => 'warning',
                'message' => 'No hay una licencia configurada. Las altas de usuarios y sucursales permanecen bloqueadas hasta registrar una licencia.',
                'date' => null,
                'date_label' => null,
            ];
        }

        if ($snapshot->status === 'expiring_soon') {
            return [
                'type' => 'expiring_soon',
                'severity' => 'warning',
                'message' => 'La licencia está próxima a vencer. Revise la vigencia para evitar el modo solo lectura.',
                'date' => $snapshot->expiresAt,
                'date_label' => $snapshot->expiresAt === null ? null : 'Fecha de término',
            ];
        }

        if ($snapshot->status === 'suspended' && $snapshot->readOnly) {
            return [
                'type' => 'suspended_read_only',
                'severity' => 'danger',
                'message' => 'La licencia está suspendida fuera del período de gracia. El sistema está en modo solo lectura; la consulta y la exportación de datos siguen disponibles.',
                'date' => $snapshot->graceUntil,
                'date_label' => $snapshot->graceUntil === null ? null : 'Fin del período de gracia',
            ];
        }

        if ($snapshot->status === 'suspended' && $snapshot->inGrace) {
            return [
                'type' => 'suspended_grace',
                'severity' => 'warning',
                'message' => 'La licencia está suspendida. La operación continúa hasta el fin del período de gracia.',
                'date' => $snapshot->graceUntil,
                'date_label' => 'Fin del período de gracia',
            ];
        }

        return null;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
