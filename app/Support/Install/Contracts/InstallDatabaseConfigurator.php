<?php

namespace App\Support\Install\Contracts;

use App\Support\Install\InstallDatabaseConfig;

/**
 * Reconfigura la conexión en tiempo de ejecución antes de migrar.
 */
interface InstallDatabaseConfigurator
{
    public function configure(InstallDatabaseConfig $database): void;
}
