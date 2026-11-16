<?php

namespace App\Contracts;

use App\Support\License\InstallationStatusReport;

/**
 * Contrato local de licencia (Parte 1 §9.6 · CORE-08).
 *
 * CORE-08 solo define el contrato y un stub de simulación local. El latido
 * saliente, la respuesta de Control, la tolerancia offline y la firma Ed25519
 * pertenecen a LIC-02–LIC-05 y quedan fuera de este alcance.
 *
 * Una implementación NO puede otorgar derechos comerciales por sí sola ni
 * convertir una licencia real inválida en válida. La fuente de verdad sigue
 * siendo la fila local de `license_states` aplicada por los guards existentes.
 */
interface LicenseValidator
{
    /**
     * Indica si la licencia es válida según la implementación.
     *
     * El stub local solo expresa validez SIMULADA para pruebas/desarrollo; no
     * representa una licencia comercial real.
     */
    public function isValid(): bool;

    /**
     * Información de licencia apta para UI. Nunca debe incluir
     * `installation_key`, `signed_payload`, `signature` ni datos personales.
     *
     * @return array<string, mixed>
     */
    public function getLicenseInfo(): array;

    /**
     * Límites del plan. `0` conserva la semántica contractual de ilimitado.
     *
     * @return array<string, int>
     */
    public function getLimits(): array;

    /**
     * Indica si el módulo técnico está incluido por la licencia. La mera
     * presencia del módulo no otorga derechos comerciales (RN-67).
     */
    public function hasModule(string $module): bool;

    /**
     * Días restantes hasta el vencimiento, o null cuando no aplica.
     */
    public function getDaysRemaining(): ?int;

    /**
     * Reporta el estado de la instalación al mecanismo que corresponda.
     *
     * Esta implementación local es un no-op: no abre conexiones, no envía
     * solicitudes y no persiste secretos.
     */
    public function reportStatus(InstallationStatusReport $report): void;
}
