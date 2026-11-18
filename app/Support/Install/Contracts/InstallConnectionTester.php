<?php

namespace App\Support\Install\Contracts;

use App\Support\Install\InstallDatabaseConfig;

/**
 * Prueba de conexión contra la base de datos elegida.
 *
 * La implementación real usa PDO. El password no se incluye en el resultado
 * ni en los mensajes de error.
 */
interface InstallConnectionTester
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function test(InstallDatabaseConfig $database): array;
}
