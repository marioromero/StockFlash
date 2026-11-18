<?php

namespace App\Providers;

use App\Support\Install\ArtisanInstallMigrator;
use App\Support\Install\AtomicEnvSecretWriter;
use App\Support\Install\Contracts\InstallConnectionTester;
use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use App\Support\Install\Contracts\InstallMigrator;
use App\Support\Install\Contracts\InstallPreflightChecker;
use App\Support\Install\Contracts\InstallSecretWriter;
use App\Support\Install\DefaultInstallPreflightChecker;
use App\Support\Install\PdoInstallConnectionTester;
use App\Support\Install\RuntimeDatabaseConfigurator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // INSTALL-001: cableado real del instalador. Las pruebas pueden
        // sustituir estos bindings por dobles, pero el entorno real nunca
        // debe depender de ellos para resolver el flujo.
        $this->app->singleton(InstallPreflightChecker::class, DefaultInstallPreflightChecker::class);
        $this->app->singleton(InstallConnectionTester::class, PdoInstallConnectionTester::class);
        $this->app->singleton(InstallMigrator::class, ArtisanInstallMigrator::class);
        $this->app->singleton(InstallSecretWriter::class, AtomicEnvSecretWriter::class);
        $this->app->singleton(InstallDatabaseConfigurator::class, RuntimeDatabaseConfigurator::class);

        // Tienda de instalación disponible también fuera del middleware (por
        // ejemplo, servicio invocado directamente en pruebas o CLI).
        $installStore = trim((string) config('install.cache.store', 'install'));

        if ($installStore !== '') {
            $path = (string) config('install.cache.path', storage_path('framework/cache/install'));

            config([
                'cache.stores.'.$installStore => [
                    'driver' => (string) config('install.cache.driver', 'file'),
                    'path' => $path,
                    'lock_path' => (string) config('install.cache.lock_path', $path),
                ],
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
