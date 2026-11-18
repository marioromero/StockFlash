<?php

namespace Tests\Feature\Install\Support;

use App\Support\Install\Contracts\InstallConnectionTester;
use App\Support\Install\InstallDatabaseConfig;

final class FakeConnectionTester implements InstallConnectionTester
{
    /**
     * @var array{ok: bool, message: string}
     */
    public array $result = [
        'ok' => true,
        'message' => 'Conexión de prueba verificada.',
    ];

    public int $calls = 0;

    public ?InstallDatabaseConfig $last = null;

    public function test(InstallDatabaseConfig $database): array
    {
        $this->calls++;
        $this->last = $database;

        return $this->result;
    }

    public function fail(string $message = 'No fue posible conectar con los datos indicados.'): void
    {
        $this->result = ['ok' => false, 'message' => $message];
    }
}
