<?php

namespace Tests\Feature\Install\Support;

use App\Support\Install\Contracts\InstallSecretWriter;
use App\Support\Install\InstallDatabaseConfig;
use RuntimeException;

final class FakeSecretWriter implements InstallSecretWriter
{
    public int $calls = 0;

    public ?InstallDatabaseConfig $database = null;

    /**
     * @var array{app_key: string, blind_index_key: string, backup_encryption_key: string}|null
     */
    public ?array $secrets = null;

    public ?string $exception = null;

    /**
     * @var array{preserved: list<string>, written: list<string>}
     */
    public array $result = ['preserved' => [], 'written' => []];

    public function persist(InstallDatabaseConfig $database, array $secrets): array
    {
        $this->calls++;
        $this->database = $database;
        $this->secrets = $secrets;

        if ($this->exception !== null) {
            throw new RuntimeException($this->exception);
        }

        return $this->result;
    }
}
