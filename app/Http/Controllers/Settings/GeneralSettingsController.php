<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralSettingsRequest;
use App\Support\AuditLogger;
use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Preferencias generales del comercio (CFG-003 · CORE-09).
 *
 * Solo administra las siete claves contractuales de Parte 2 §3.1:
 * país, moneda, decimales, zona horaria, idioma, precios con impuesto y paso
 * de redondeo de efectivo. La escritura y su auditoría ocurren en la misma
 * transacción; si la auditoría falla, los valores quedan intactos.
 */
class GeneralSettingsController extends Controller
{
    /**
     * Claves administradas. No se consulta ni serializa ninguna otra.
     *
     * @var list<string>
     */
    private const KEYS = [
        'country',
        'currency_code',
        'currency_decimals',
        'timezone',
        'locale',
        'prices_include_tax',
        'cash_rounding_step',
    ];

    /**
     * Defectos contractuales (Parte 2 §3.1) usados solo para lectura cuando la
     * fila no existe. Una lectura nunca crea ni modifica filas.
     *
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'country' => 'CL',
        'currency_code' => 'CLP',
        'currency_decimals' => '0',
        'timezone' => 'America/Santiago',
        'locale' => 'es-CL',
        'prices_include_tax' => '1',
        'cash_rounding_step' => '1',
    ];

    /**
     * Tipos contractuales usados únicamente al crear una fila faltante.
     *
     * @var array<string, string>
     */
    private const VALUE_TYPES = [
        'country' => 'string',
        'currency_code' => 'string',
        'currency_decimals' => 'int',
        'timezone' => 'string',
        'locale' => 'string',
        'prices_include_tax' => 'bool',
        'cash_rounding_step' => 'int',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(): Response
    {
        $rows = DB::table('system_configs')
            ->whereIn('config_key', self::KEYS)
            ->pluck('config_value', 'config_key');

        $props = [];

        foreach (self::KEYS as $key) {
            $props[$key] = $this->normalizeForDisplay($key, $rows->get($key));
        }

        $props['read_only'] = $this->licenseIsReadOnly();

        return Inertia::render('Settings/General', $props);
    }

    public function update(GeneralSettingsRequest $request): RedirectResponse
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
                $value = $this->normalizeForStorage($key, $data[$key]);
                $row = $existing->get($key);

                if ($row === null) {
                    // Solo se inserta la clave administrada, con los metadatos
                    // contractuales por defecto. Nunca se toca otra fila.
                    DB::table('system_configs')->insert([
                        'config_key' => $key,
                        'config_value' => $value,
                        'value_type' => self::VALUE_TYPES[$key],
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

            // Solo se auditan los nombres de las claves modificadas; nunca sus
            // valores ni datos ajenos a esta pantalla.
            $this->auditLogger->record(
                'core.general_settings.updated',
                'system_configs',
                null,
                null,
                null,
                ['changed_keys' => $changedKeys],
                'core',
            );
        });

        return redirect()
            ->route('settings.general.edit')
            ->with('success', 'Preferencias generales actualizadas correctamente.');
    }

    private function normalizeForDisplay(string $key, mixed $value): string|int|bool
    {
        $raw = $value === null ? self::DEFAULTS[$key] : (string) $value;

        return match ($key) {
            'prices_include_tax' => $this->toBool($raw),
            'currency_decimals', 'cash_rounding_step' => is_numeric($raw)
                ? (int) $raw
                : (int) self::DEFAULTS[$key],
            default => $raw,
        };
    }

    private function normalizeForStorage(string $key, mixed $value): string
    {
        return match ($key) {
            'prices_include_tax' => $value ? '1' : '0',
            'currency_decimals', 'cash_rounding_step' => (string) (int) $value,
            default => (string) $value,
        };
    }

    private function toBool(string $value): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($parsed !== null) {
            return $parsed;
        }

        return self::DEFAULTS['prices_include_tax'] === '1';
    }

    private function sameValue(mixed $current, mixed $next): bool
    {
        if ($current === null || $next === null) {
            return $current === null && $next === null;
        }

        return (string) $current === (string) $next;
    }

    /**
     * Guard RN-70 compartido por las pantallas de configuración: una licencia
     * suspendida sin gracia o con gracia vencida deja el sistema en solo
     * lectura. Sin fila de licencia se permite escribir.
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
