<?php

namespace Tests\Feature\Install;

use App\Support\Install\DefaultInstallPreflightChecker;
use App\Support\Install\InstallService;
use Illuminate\Support\Facades\Schema;

class InstallPreflightTest extends InstallTestCase
{
    public function test_preflight_exitoso_queda_en_sesion_y_se_muestra(): void
    {
        $token = $this->passPreflight();

        $this->assertSame(1, $this->preflight->calls);
        $stored = session(InstallService::SESSION_PREFLIGHT);
        $this->assertIsArray($stored);
        $this->assertTrue($stored['ok']);
        $this->assertSame('VIRGIN', app(InstallService::class)->state()['code']);

        $this->get('/install')->assertOk()->assertInertia(
            fn ($page) => $page
                ->component('Install/Wizard')
                ->where('preflight.ok', true)
                ->has('preflight.checks', 4)
        );

        $this->assertSame(0, $this->migrator->migrateCalls);
    }

    public function test_preflight_fallido_no_instala_y_reporta_requisitos(): void
    {
        $this->preflight->fail('Sin escritura en storage/framework.');
        $token = $this->getFlowToken();

        $response = $this->from('/install')->post('/install/preflight', [
            'install_token' => $token,
        ]);

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('preflight');
        $stored = session(InstallService::SESSION_PREFLIGHT);
        $this->assertIsArray($stored);
        $this->assertFalse($stored['ok']);
        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_preflight_sin_token_de_flujo_responde_419(): void
    {
        $this->authorizeBootstrap();

        $this->post('/install/preflight', ['install_token' => 'token-invalido'])->assertStatus(419);
    }

    public function test_checker_real_detecta_version_de_php_insuficiente(): void
    {
        config(['install.minimum_php_version' => '99.0.0']);

        $result = (new DefaultInstallPreflightChecker(phpVersion: '8.3.0'))->check();

        $this->assertFalse($result['ok']);
        $this->assertSame('fail', $result['checks'][0]['status']);
        $this->assertSame('php_version', $result['checks'][0]['id']);
    }

    public function test_checker_real_detecta_extension_faltante(): void
    {
        config(['install.required_extensions' => ['pdo', 'extension_ficticia']]);

        $result = (new DefaultInstallPreflightChecker(
            extensions: ['pdo' => true, 'extension_ficticia' => false],
        ))->check();

        $this->assertFalse($result['ok']);
        $this->assertSame('fail', $result['checks'][1]['status']);
        $this->assertStringContainsString('extension_ficticia', $result['checks'][1]['detail']);
    }

    public function test_checker_real_detecta_permisos_y_dependencias_faltantes(): void
    {
        config([
            'install.required_files' => ['archivo_ficticio.php'],
            'install.writable_paths' => ['ruta_ficticia'],
        ]);

        $result = (new DefaultInstallPreflightChecker(
            files: ['archivo_ficticio.php' => false],
            paths: ['ruta_ficticia' => false],
        ))->check();

        $this->assertFalse($result['ok']);

        $statuses = array_column($result['checks'], 'status', 'id');
        $this->assertSame('fail', $statuses['dependencies']);
        $this->assertSame('fail', $statuses['writable']);
    }

    public function test_checker_real_reporta_storage_del_instalador_no_escribible(): void
    {
        config(['install.runtime_storage_unavailable' => true]);

        $result = app(DefaultInstallPreflightChecker::class)->check();

        $this->assertFalse($result['ok']);
        $writable = collect($result['checks'])->firstWhere('id', 'writable');
        $this->assertIsArray($writable);
        $this->assertSame('fail', $writable['status']);
        $this->assertStringContainsString('tiendas temporales', $writable['detail']);
    }

    public function test_checker_real_no_toca_la_base_de_datos(): void
    {
        $before = Schema::hasTable('installation_info');
        $result = app(DefaultInstallPreflightChecker::class)->check();

        $this->assertTrue($before);
        $this->assertSame($before, Schema::hasTable('installation_info'));
        $this->assertIsBool($result['ok']);
        $this->assertSame(0, $this->installationInfoCount());
    }
}
