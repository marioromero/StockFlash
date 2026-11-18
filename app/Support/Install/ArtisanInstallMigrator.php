<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallMigrator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Migraciones normales y semillas contractuales existentes.
 *
 * Prohibido por diseño: fresh, wipe, reset o cualquier operación destructiva.
 * Las semillas son las de `DatabaseSeeder` (§13.1) y son idempotentes; no se
 * crean datos demo ni licencias comerciales.
 */
final class ArtisanInstallMigrator implements InstallMigrator
{
    public function migrate(): void
    {
        $exit = Artisan::call('migrate', ['--force' => true]);

        if ($exit !== 0) {
            throw new RuntimeException('Las migraciones no finalizaron correctamente.');
        }
    }

    public function seed(): void
    {
        $exit = Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException('Las semillas no finalizaron correctamente.');
        }
    }

    public function schemaVersion(): ?string
    {
        if (! Schema::hasTable('migrations')) {
            return null;
        }

        $name = DB::table('migrations')->orderByDesc('id')->value('migration');

        return is_string($name) && $name !== '' ? substr($name, 0, 40) : null;
    }
}
