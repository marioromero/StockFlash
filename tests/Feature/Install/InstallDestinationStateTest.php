<?php

namespace Tests\Feature\Install;

use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use App\Support\Install\InstallDatabaseConfig;
use App\Support\Install\InstallService;
use App\Support\Install\RuntimeDatabaseConfigurator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * B-03: la virginidad se evalúa contra la BD destino configurada en runtime,
 * nunca contra la conexión por defecto de `.env`. Usa el configurador real.
 */
class InstallDestinationStateTest extends InstallTestCase
{
    private string $originalDefaultConnection = '';

    private string $destinationPath = '';

    private string $destinationConnection = 'install_runtime';

    public function test_destino_ya_instalado_bloquea_antes_de_migrar_o_escribir_secretos(): void
    {
        $this->originalDefaultConnection = (string) config('database.default');
        $this->createInstalledDestination();

        $this->app->instance(InstallDatabaseConfigurator::class, new RuntimeDatabaseConfigurator);

        $database = new InstallDatabaseConfig('sqlite', '127.0.0.1', 3306, $this->destinationPath, '', '');

        try {
            $service = $this->app->make(InstallService::class);
            $outcome = $service->perform($database);

            $this->assertFalse($outcome['ok']);
            $this->assertTrue($outcome['blocked'] ?? false);
            $this->assertSame(0, $this->migrator->migrateCalls);
            $this->assertSame(0, $this->migrator->seedCalls);
            $this->assertSame(0, $this->secretWriter->calls);
            $this->assertSame(1, DB::connection($this->destinationConnection)->table('installation_info')->count());
            $this->assertSame(1, DB::connection($this->destinationConnection)->table('users')->count());
            $this->assertSame(0, DB::connection($this->originalDefaultConnection)->table('installation_info')->count());
        } finally {
            $this->restoreDefaultConnection();
        }
    }

    private function createInstalledDestination(): void
    {
        $this->destinationPath = storage_path('framework/testing/install-destination-'.Str::uuid().'.sqlite');

        if (! is_dir(dirname($this->destinationPath))) {
            mkdir(dirname($this->destinationPath), 0777, true);
        }

        if (! is_file($this->destinationPath)) {
            touch($this->destinationPath);
        }

        config([
            'database.connections.'.$this->destinationConnection => [
                'driver' => 'sqlite',
                'database' => $this->destinationPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => $this->destinationConnection,
        ]);

        DB::purge($this->destinationConnection);

        Schema::connection($this->destinationConnection)->create('installation_info', function (Blueprint $table): void {
            $table->id();
            $table->char('installation_id', 36)->unique();
            $table->string('app_version', 20);
            $table->string('schema_version', 40)->nullable();
            $table->enum('release_channel', ['stable', 'beta'])->default('stable');
            $table->dateTime('installed_at');
            $table->dateTime('last_update_at')->nullable();
            $table->enum('last_update_status', ['none', 'ok', 'failed', 'rolled_back'])->default('none');
            $table->dateTime('recovery_package_exported_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->destinationConnection)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection($this->destinationConnection)->create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->timestamps();
        });

        $now = now();

        DB::connection($this->destinationConnection)->table('installation_info')->insert([
            'installation_id' => (string) Str::uuid(),
            'app_version' => '1.0.0',
            'schema_version' => '2026_10_16_000020',
            'release_channel' => 'stable',
            'installed_at' => $now,
            'last_update_at' => null,
            'last_update_status' => 'none',
            'recovery_package_exported_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection($this->destinationConnection)->table('users')->insert([
            'name' => 'Usuario Destino',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function restoreDefaultConnection(): void
    {
        config(['database.default' => $this->originalDefaultConnection]);
        DB::purge($this->destinationConnection);

        if ($this->destinationPath !== '' && is_file($this->destinationPath)) {
            @unlink($this->destinationPath);
        }
    }
}
