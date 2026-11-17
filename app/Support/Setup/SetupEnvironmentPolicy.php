<?php

namespace App\Support\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puerta de onboarding por entorno (SETUP-001 · BKP-08 P1★).
 *
 * El paquete de recuperación verificable BKP-08 no está implementado. En
 * producción no demo el onboarding queda bloqueado con el código explícito
 * `NOT_READY_FOR_PRODUCTION`; no existe bypass silencioso. DEV/test y la
 * modalidad demo pueden completar el asistente. No cambia `is_demo`, licencias
 * ni la política de producción.
 */
class SetupEnvironmentPolicy
{
    public const CODE_NOT_READY = 'NOT_READY_FOR_PRODUCTION';

    /**
     * Evaluación pura, sin acceso a la aplicación ni a la base de datos.
     *
     * @return array{allowed: bool, code: ?string, message: string}
     */
    public function evaluate(
        bool $isDemo,
        string $env,
        bool $productionOnboardingEnabled = false,
        bool $recoveryPackageAvailable = false,
    ): array {
        $environment = strtolower(trim($env));

        $allowed = $environment !== 'production'
            || $isDemo
            || ($productionOnboardingEnabled && $recoveryPackageAvailable);

        if ($allowed) {
            return [
                'allowed' => true,
                'code' => null,
                'message' => '',
            ];
        }

        return [
            'allowed' => false,
            'code' => self::CODE_NOT_READY,
            'message' => 'La instalación en producción requiere el paquete de recuperación verificable BKP-08, que aún no está implementado. No es posible completar el primer inicio en este entorno.',
        ];
    }

    /**
     * Evaluación con el entorno y la modalidad demo reales.
     *
     * @return array{allowed: bool, code: ?string, message: string}
     */
    public function evaluateCurrent(): array
    {
        $isDemo = false;

        if (Schema::hasTable('system_configs')) {
            $value = DB::table('system_configs')
                ->where('config_key', 'is_demo')
                ->value('config_value');

            $isDemo = is_string($value)
                ? in_array(strtolower(trim($value)), ['1', 'true'], true)
                : $value === true;
        }

        return $this->evaluate(
            $isDemo,
            app()->environment(),
            (bool) config('setup.production_onboarding_enabled', false),
            (bool) config('setup.recovery_package_available', false),
        );
    }
}
