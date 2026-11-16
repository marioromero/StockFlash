<?php

namespace App\Support\License;

use App\Contracts\LicenseValidator;
use LogicException;

/**
 * Stub local de licencia para simulación, pruebas y desarrollo (CORE-08).
 *
 * Devuelve límites ilimitados y módulos disponibles SOLO en el contexto de
 * simulación. No sustituye `license_states`, no modifica los guards existentes
 * (`PlanGuard`, RN-67, RN-70) y no concede derechos comerciales. Tampoco
 * convierte una licencia real inválida en válida: la fuente de verdad sigue
 * siendo la fila local aplicada por los guards.
 *
 * Prohibido por diseño: HTTP, cron, heartbeat, cliente Control, claves
 * centrales, firmas criptográficas, tokens y cualquier llamada saliente.
 */
final class LocalLicenseStub implements LicenseValidator
{
    /**
     * Límites ilimitados según la semántica contractual (`0` = ilimitado).
     *
     * @var array<string, int>
     */
    private const UNLIMITED_LIMITS = [
        'max_branches' => 0,
        'max_pos_stations' => 0,
        'max_channels' => 0,
        'max_users' => 0,
        'max_products' => 0,
        'max_monthly_sales' => 0,
    ];

    public function __construct()
    {
        if (app()->environment('production')) {
            throw new LogicException(
                'LocalLicenseStub solo puede usarse en simulación, pruebas o desarrollo; no en producción.'
            );
        }
    }

    public function isValid(): bool
    {
        // Validez simulada: no representa una licencia comercial real.
        return true;
    }

    public function getLicenseInfo(): array
    {
        return [
            'is_simulated' => true,
            'mode' => 'simulation',
            'status' => 'active',
            'plan_code' => 'simulation_unlimited',
            'expires_at' => null,
            'grace_until' => null,
        ];
    }

    public function getLimits(): array
    {
        return self::UNLIMITED_LIMITS;
    }

    public function hasModule(string $module): bool
    {
        // Cualquier módulo técnico declarado está disponible solo en simulación.
        return trim($module) !== '';
    }

    public function getDaysRemaining(): ?int
    {
        // La simulación no tiene vencimiento.
        return null;
    }

    public function reportStatus(InstallationStatusReport $report): void
    {
        // No-op intencional: sin HTTP, cron, heartbeat ni persistencia externa.
    }
}
