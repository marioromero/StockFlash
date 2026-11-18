<?php

namespace Tests\Feature\Install;

use App\Support\Install\ArtisanInstallMigrator;
use App\Support\Install\AtomicEnvSecretWriter;
use App\Support\Install\Contracts\InstallConnectionTester;
use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use App\Support\Install\Contracts\InstallMigrator;
use App\Support\Install\Contracts\InstallPreflightChecker;
use App\Support\Install\Contracts\InstallSecretWriter;
use App\Support\Install\DefaultInstallPreflightChecker;
use App\Support\Install\InstallService;
use App\Support\Install\PdoInstallConnectionTester;
use App\Support\Install\RuntimeDatabaseConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * B-01: el contenedor real debe cablear todas las interfaces del instalador.
 * No se inyectan fakes en esta clase.
 */
class InstallWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'install.enabled' => true,
            'install.bootstrap_token' => 'wiring-bootstrap-token-0123456789abcdef0123456789',
        ]);
    }

    public function test_contenedor_real_resuelve_el_servicio_y_las_cinco_interfaces(): void
    {
        $this->assertInstanceOf(InstallService::class, app(InstallService::class));
        $this->assertInstanceOf(DefaultInstallPreflightChecker::class, app(InstallPreflightChecker::class));
        $this->assertInstanceOf(PdoInstallConnectionTester::class, app(InstallConnectionTester::class));
        $this->assertInstanceOf(ArtisanInstallMigrator::class, app(InstallMigrator::class));
        $this->assertInstanceOf(AtomicEnvSecretWriter::class, app(InstallSecretWriter::class));
        $this->assertInstanceOf(RuntimeDatabaseConfigurator::class, app(InstallDatabaseConfigurator::class));
    }

    public function test_get_install_funciona_con_el_contenedor_normal_sin_fakes(): void
    {
        $this->get('/install')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Install/Wizard')
                ->where('authorized', false)
                ->missing('flow_token')
        );
    }
}
