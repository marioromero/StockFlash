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
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B-04: el aviso de cron en `/setup` no depende de flash ni de la sesión del
 * instalador. Se calcula desde `installation_info` + `is_setup_completed`.
 */
class InstallSetupNoticeTest extends InstallTestCase
{
    private string $originalDefault = '';

    private string $destination = '';

    public function test_setup_muestra_cron_sin_sesion_del_instalador_con_drivers_database(): void
    {
        $this->originalDefault = (string) config('database.default');
        $this->destination = $this->createDestinationDatabase();

        $this->useRealRuntimeConfigurator();
        $database = new InstallDatabaseConfig('sqlite', '127.0.0.1', 3306, $this->destination, '', '');

        try {
            $service = $this->app->make(InstallService::class);
            $outcome = $service->perform($database);

            $this->assertTrue($outcome['ok']);
            $this->assertSame(1, DB::connection('install_runtime')->table('installation_info')->count());

            // Ignora completamente la sesión/file del instalador.
            $this->flushSession();
            config(['session.driver' => 'database', 'cache.default' => 'database']);

            $response = $this->get('/setup');

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Setup/Wizard')
                ->where('install_notice.completed', true)
                ->where('install_notice.cron_command', 'php artisan schedule:run')
                ->where('install_notice.app_version', '9.9.9')
            );
            $response->assertSee('php artisan schedule:run', false);
        } finally {
            $this->restoreDefaultConnection();
        }
    }

    private function useRealRuntimeConfigurator(): void
    {
        $this->app->instance(InstallDatabaseConfigurator::class, new RuntimeDatabaseConfigurator);
    }

    private function createDestinationDatabase(): string
    {
        $path = storage_path('framework/testing/install-setup-'.Str::uuid().'.sqlite');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        touch($path);

        config([
            'database.connections.install_runtime' => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'install_runtime',
        ]);

        DB::purge('install_runtime');

        $this->createRequiredSchema();

        return $path;
    }

    private function createRequiredSchema(): void
    {
        Schema::connection('install_runtime')->create('system_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('config_key', 100)->unique();
            $table->text('config_value')->nullable();
            $table->string('value_type', 20)->default('string');
            $table->string('module_code', 30)->nullable();
            $table->unsignedTinyInteger('is_secret')->default(0);
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('install_runtime')->create('installation_info', function (Blueprint $table): void {
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

        foreach (['users', 'branches', 'roles', 'audit_logs'] as $table) {
            Schema::connection('install_runtime')->create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->timestamps();
            });
        }

        Schema::connection('install_runtime')->create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::connection('install_runtime')->create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        DB::connection('install_runtime')->table('system_configs')->insert([
            'config_key' => 'is_setup_completed',
            'config_value' => '0',
            'value_type' => 'bool',
            'is_secret' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function restoreDefaultConnection(): void
    {
        config(['database.default' => $this->originalDefault]);
        DB::purge('install_runtime');

        if ($this->destination !== '' && is_file($this->destination)) {
            @unlink($this->destination);
        }
    }
}
