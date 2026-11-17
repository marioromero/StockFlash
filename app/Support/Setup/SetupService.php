<?php

namespace App\Support\Setup;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Estado y utilidades del primer inicio (SETUP-001 · CORE-02).
 *
 * Solo consulta el esquema existente: no migra, no siembra y no escribe
 * configuración. El acceso a datos usa el Query Builder para no introducir
 * modelos nuevos de esquema.
 */
class SetupService
{
    /**
     * Clave de sesión del token de flujo del asistente.
     */
    public const SESSION_KEY = 'setup_flow_token';

    /**
     * Tablas mínimas que deben existir para considerar el esquema preparado.
     *
     * @var list<string>
     */
    private const REQUIRED_TABLES = [
        'system_configs',
        'users',
        'branches',
        'roles',
        'audit_logs',
    ];

    /**
     * Claves de preferencias generales (CFG-003) que el asistente precarga.
     *
     * @var list<string>
     */
    private const PREFERENCE_KEYS = [
        'country',
        'currency_code',
        'currency_decimals',
        'timezone',
        'locale',
        'prices_include_tax',
        'cash_rounding_step',
    ];

    /**
     * ¿Está preparado el esquema de la instalación?
     *
     * No ejecuta migraciones: solo comprueba la existencia de las tablas
     * mínimas aprobadas.
     */
    public function schemaReady(): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * ¿La instalación ya fue configurada por el asistente?
     *
     * Tolera `'1'` y `'true'`. Si la tabla no existe devuelve `false`: el
     * consumidor debe consultar `schemaReady()` antes de decidir el bloqueo.
     */
    public function isCompleted(): bool
    {
        if (! Schema::hasTable('system_configs')) {
            return false;
        }

        $value = DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->value('config_value');

        return $this->isTruthy($value);
    }

    /**
     * ¿La instancia está virgen (sin usuarios ni sucursales)?
     *
     * Incluye registros con soft delete para no reabrir el onboarding sobre
     * datos ya existentes.
     */
    public function isPristine(): bool
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('branches')) {
            return false;
        }

        return DB::table('users')->count() === 0
            && DB::table('branches')->count() === 0;
    }

    /**
     * Defaults contractuales de las siete preferencias generales (CFG-003).
     *
     * Lee los valores existentes y los normaliza; nunca expone datos
     * personales. Si no hay filas usa los defaults del contrato.
     *
     * @return array{country: string, currency_code: string, currency_decimals: int, timezone: string, locale: string, prices_include_tax: bool, cash_rounding_step: int}
     */
    public function initialDefaults(): array
    {
        $defaults = [
            'country' => 'CL',
            'currency_code' => 'CLP',
            'currency_decimals' => 0,
            'timezone' => 'America/Santiago',
            'locale' => 'es-CL',
            'prices_include_tax' => true,
            'cash_rounding_step' => 1,
        ];

        if (! Schema::hasTable('system_configs')) {
            return $defaults;
        }

        $rows = DB::table('system_configs')
            ->whereIn('config_key', self::PREFERENCE_KEYS)
            ->pluck('config_value', 'config_key');

        $country = $this->stringOr($rows->get('country'), 'CL');
        $currency = $this->stringOr($rows->get('currency_code'), 'CLP');

        return [
            'country' => strtoupper($country),
            'currency_code' => strtoupper($currency),
            'currency_decimals' => $this->intOr($rows->get('currency_decimals'), 0),
            'timezone' => $this->stringOr($rows->get('timezone'), 'America/Santiago'),
            'locale' => $this->stringOr($rows->get('locale'), 'es-CL'),
            // El default contractual es `true`; solo se interpreta el valor
            // cuando la fila existe (una fila ausente no equivale a `false`).
            'prices_include_tax' => $rows->has('prices_include_tax')
                ? $this->isTruthy($rows->get('prices_include_tax'))
                : true,
            'cash_rounding_step' => $this->intOr($rows->get('cash_rounding_step'), 1),
        ];
    }

    /**
     * Emite (o reutiliza) el token del flujo guardado en sesión.
     */
    public function issueFlowToken(Request $request): string
    {
        $existing = $request->session()->get(self::SESSION_KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = Str::random(64);
        $request->session()->put(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Verifica el token del flujo con comparación de tiempo constante.
     */
    public function assertFlowToken(Request $request, ?string $token): void
    {
        $expected = $request->session()->get(self::SESSION_KEY);

        if (
            ! is_string($expected)
            || $expected === ''
            || ! is_string($token)
            || $token === ''
            || ! hash_equals($expected, $token)
        ) {
            abort(419, 'La sesión de instalación expiró. Recargue el asistente e intente nuevamente.');
        }
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true'], true);
    }

    private function stringOr(mixed $value, string $fallback): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return trim($value);
    }

    private function intOr(mixed $value, int $fallback): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $fallback;
        }

        return (int) $value;
    }
}
