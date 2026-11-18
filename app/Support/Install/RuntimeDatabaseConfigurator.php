<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use Illuminate\Support\Facades\DB;

/**
 * Configura la conexión de la ejecución actual con los datos elegidos.
 *
 * No escribe archivos ni registra credenciales. La persistencia definitiva la
 * realiza el escritor atómico de secretos.
 */
final class RuntimeDatabaseConfigurator implements InstallDatabaseConfigurator
{
    private const CONNECTION = 'install_runtime';

    public function configure(InstallDatabaseConfig $database): void
    {
        config([
            'database.connections.'.self::CONNECTION => $database->toConnectionConfig(),
        ]);

        config(['database.default' => self::CONNECTION]);

        DB::purge(self::CONNECTION);
    }
}
