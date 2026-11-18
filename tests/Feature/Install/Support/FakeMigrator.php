<?php

namespace Tests\Feature\Install\Support;

use App\Support\Install\Contracts\InstallMigrator;
use RuntimeException;

final class FakeMigrator implements InstallMigrator
{
    public int $migrateCalls = 0;

    public int $seedCalls = 0;

    public ?string $schemaVersion = '2026_11_17_000001_create_test';

    public ?string $migrateException = null;

    public ?string $seedException = null;

    public function migrate(): void
    {
        $this->migrateCalls++;

        if ($this->migrateException !== null) {
            throw new RuntimeException($this->migrateException);
        }
    }

    public function seed(): void
    {
        $this->seedCalls++;

        if ($this->seedException !== null) {
            throw new RuntimeException($this->seedException);
        }
    }

    public function schemaVersion(): ?string
    {
        return $this->schemaVersion;
    }
}
