<?php

namespace Tests\Feature\Install\Support;

use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use App\Support\Install\InstallDatabaseConfig;
use Closure;

final class FakeDatabaseConfigurator implements InstallDatabaseConfigurator
{
    public int $calls = 0;

    public ?InstallDatabaseConfig $last = null;

    public ?Closure $onConfigure = null;

    public function configure(InstallDatabaseConfig $database): void
    {
        $this->calls++;
        $this->last = $database;

        if ($this->onConfigure !== null) {
            ($this->onConfigure)($database);
        }
    }
}
