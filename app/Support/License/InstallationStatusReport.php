<?php

namespace App\Support\License;

/**
 * Reporte mínimo de estado de instalación para `LicenseValidator::reportStatus`.
 *
 * Contiene solo metadatos técnicos no sensibles. No incluye clientes, ventas,
 * RUT, documentos, contactos, `installation_key` ni información comercial.
 * El envío real (latido LIC-02) queda fuera de CORE-08.
 */
final class InstallationStatusReport
{
    /**
     * @param  list<string>  $activeModules
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public readonly string $appVersion,
        public readonly string $schemaVersion,
        public readonly array $activeModules = [],
        public readonly ?string $versionChannel = null,
        public readonly ?string $lastUpdatedAt = null,
        public readonly array $extensions = [],
    ) {}

    /**
     * Representación serializable del reporte (sin secretos).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'app_version' => $this->appVersion,
            'schema_version' => $this->schemaVersion,
            'active_modules' => array_values($this->activeModules),
            'version_channel' => $this->versionChannel,
            'last_updated_at' => $this->lastUpdatedAt,
            'extensions' => $this->extensions,
        ];
    }
}
