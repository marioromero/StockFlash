<?php

namespace App\Support\Install\Contracts;

use App\Support\Install\InstallDatabaseConfig;

/**
 * Persistencia local y atómica de secretos y configuración de BD.
 *
 * La implementación real escribe un archivo temporal y lo renombra. Nunca
 * sobrescribe una clave secreta existente con valor no vacío: la preserva y
 * solo escribe las ausentes o vacías. Las pruebas inyectan un doble que nunca
 * toca el `.env` real.
 */
interface InstallSecretWriter
{
    /**
     * @param  array{app_key: string, blind_index_key: string, backup_encryption_key: string}  $secrets
     * @return array{preserved: list<string>, written: list<string>}
     */
    public function persist(InstallDatabaseConfig $database, array $secrets): array;
}
