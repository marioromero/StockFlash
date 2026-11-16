<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessSettingsRequest;
use App\Support\AuditLogger;
use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Datos del comercio y contacto responsable (CFG-002 · CORE-09).
 *
 * Solo administra las nueve claves de Parte 1 §8.1 / Parte 2 §3.1:
 * identidad del comercio y contacto responsable. La lectura de los datos del
 * responsable es un acceso a datos personales y se audita antes de responder
 * (fail-closed). La escritura y su auditoría ocurren en la misma transacción.
 */
class BusinessSettingsController extends Controller
{
    /**
     * Claves administradas. No se consulta ni serializa ninguna otra.
     *
     * @var list<string>
     */
    private const KEYS = [
        'business_name',
        'fiscal_id',
        'fiscal_name',
        'commercial_activity',
        'fiscal_address',
        'fiscal_commune',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(): Response
    {
        // PRV-06: el acceso a datos personales se audita SIEMPRE y ANTES de
        // exponer la pantalla. Si la auditoría falla, la lectura queda
        // bloqueada (la excepción impide devolver los datos).
        $this->auditLogger->recordRead(
            'core.business_settings.pii_viewed',
            'system_configs',
            null,
            'core',
        );

        $rows = DB::table('system_configs')
            ->whereIn('config_key', self::KEYS)
            ->pluck('config_value', 'config_key');

        $props = [];

        foreach (self::KEYS as $key) {
            $props[$key] = $this->normalizeValue($rows->get($key));
        }

        $props['read_only'] = $this->licenseIsReadOnly();

        return Inertia::render('Settings/Business', $props);
    }

    public function update(BusinessSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = (int) $request->user()->getKey();

        DB::transaction(function () use ($data, $actorId): void {
            $this->assertLicenseAllowsWrites();

            $existing = DB::table('system_configs')
                ->whereIn('config_key', self::KEYS)
                ->get()
                ->keyBy('config_key');

            $changedKeys = [];

            foreach (self::KEYS as $key) {
                $value = $data[$key] ?? null;
                $row = $existing->get($key);

                if ($row === null) {
                    // Solo se inserta la clave administrada, con los metadatos
                    // contractuales por defecto. Nunca se toca otra fila.
                    DB::table('system_configs')->insert([
                        'config_key' => $key,
                        'config_value' => $value,
                        'value_type' => 'string',
                        'module_code' => null,
                        'is_secret' => 0,
                        'updated_by_user_id' => $actorId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $changedKeys[] = $key;

                    continue;
                }

                if ($this->sameValue($row->config_value, $value)) {
                    continue;
                }

                // Update acotado: se preservan `value_type`, `is_secret`,
                // `module_code`, `description` y el resto de las columnas.
                DB::table('system_configs')
                    ->where('config_key', $key)
                    ->update([
                        'config_value' => $value,
                        'updated_by_user_id' => $actorId,
                        'updated_at' => now(),
                    ]);

                $changedKeys[] = $key;
            }

            if ($changedKeys === []) {
                return;
            }

            // Solo se auditan los nombres de las claves modificadas. Nunca el
            // contenido anterior/nuevo ni datos personales del responsable.
            $this->auditLogger->record(
                'core.business_settings.updated',
                'system_configs',
                null,
                null,
                null,
                ['changed_keys' => $changedKeys],
                'core',
            );
        });

        return redirect()
            ->route('settings.business.edit')
            ->with('success', 'Datos del comercio actualizados correctamente.');
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function sameValue(mixed $current, mixed $next): bool
    {
        if ($current === null || $next === null) {
            return $current === null && $next === null;
        }

        return (string) $current === (string) $next;
    }

    /**
     * Misma semántica que `ReceiptSettingsController::assertLicenseAllowsWrites`
     * (RN-70): una licencia suspendida sin gracia o con gracia vencida deja el
     * sistema en solo lectura. Sin fila de licencia se permite escribir.
     */
    private function assertLicenseAllowsWrites(): void
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $this->licenseRowIsReadOnly($license)) {
            return;
        }

        throw ValidationException::withMessages([
            'read_only' => 'La licencia está suspendida fuera del período de gracia; el sistema está en modo solo lectura.',
        ]);
    }

    private function licenseIsReadOnly(): bool
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->first();

        return $this->licenseRowIsReadOnly($license);
    }

    private function licenseRowIsReadOnly(?object $license): bool
    {
        return LicenseReadOnlyRule::isReadOnly($license);
    }
}
