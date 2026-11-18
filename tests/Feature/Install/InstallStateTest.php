<?php

namespace Tests\Feature\Install;

use App\Support\Install\InstallService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

class InstallStateTest extends InstallTestCase
{
    public function test_instancia_virgen_muestra_el_asistente(): void
    {
        $this->authorizeBootstrap();

        $this->get('/install')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Install/Wizard')
                ->where('authorized', true)
                ->where('state.code', 'VIRGIN')
        );
    }

    public function test_instalacion_existente_bloquea_sin_borrar_datos(): void
    {
        $this->makeInstalled();

        $response = $this->get('/install');

        $response->assertStatus(423);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Install/Unavailable')
            ->where('reason_code', 'ALREADY_INSTALLED')
            ->missing('flow_token')
            ->missing('preflight')
        );

        $this->assertSame(1, $this->installationInfoCount());
        $this->assertSame(0, $this->usersCount());
        $this->assertSame(0, $this->branchesCount());

        // El bootstrap ya no evalúa virginidad contra la conexión default;
        // la revalidación real ocurre contra la BD destino al ejecutar.
        $this->post('/install/bootstrap', ['bootstrap_token' => self::BOOTSTRAP_TOKEN])->assertRedirect(route('install.show'));
    }

    public function test_usuario_existente_bloquea_como_instancia_no_virgen(): void
    {
        $this->makeExistingUser();

        $response = $this->get('/install');

        $response->assertStatus(423);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Install/Unavailable')
            ->where('reason_code', 'INSTANCE_NOT_PRISTINE')
        );

        $this->assertSame(1, $this->usersCount());
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_sucursal_existente_bloquea_como_instancia_no_virgen(): void
    {
        $this->makeExistingBranch();

        $response = $this->get('/install');

        $response->assertStatus(423);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Install/Unavailable')
            ->where('reason_code', 'INSTANCE_NOT_PRISTINE')
        );

        $this->assertSame(1, $this->branchesCount());
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_esquema_incompatible_se_bloquea_sin_modificar_datos(): void
    {
        Schema::drop('installation_info');
        Schema::create('installation_info', function ($table): void {
            $table->id();
        });

        $response = $this->get('/install');

        $response->assertStatus(423);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Install/Unavailable')
            ->where('reason_code', 'SCHEMA_INCONSISTENT')
        );

        $this->assertTrue(Schema::hasTable('installation_info'));
        $this->assertFalse(Schema::hasColumn('installation_info', 'installation_id'));
    }

    public function test_estado_instalado_bloquea_el_flujo_real_de_run_sin_migrar(): void
    {
        $this->makeInstalled();

        $this->post('/install/bootstrap', ['bootstrap_token' => self::BOOTSTRAP_TOKEN])
            ->assertRedirect(route('install.show'));

        // Se inyecta el token de flujo para ejercer el guard de estado real,
        // aunque `show()` ya muestra Unavailable para la instancia instalada.
        $token = 'token-de-flujo-para-guard-de-estado';
        $this->session([InstallService::SESSION_FLOW => $token]);

        $this->post('/install/preflight', ['install_token' => $token])
            ->assertRedirect(route('install.show'));

        $this->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(),
        ))->assertRedirect(route('install.show'));

        $this->post('/install/run', $this->validRunPayload($token))->assertStatus(423);

        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->migrator->seedCalls);
        $this->assertSame(0, $this->secretWriter->calls);
        $this->assertSame(1, $this->installationInfoCount());
        $this->assertSame(1, DB::table('installation_info')->count());
    }

    public function test_session_de_instalacion_no_incluye_secretos_generados(): void
    {
        $this->makeInstalled();

        $this->get('/install')->assertStatus(423);

        $session = $this->sessionJson();
        $this->assertStringNotContainsString('APP_KEY', $session);
        $this->assertStringNotContainsString('BLIND_INDEX_KEY', $session);
        $this->assertStringNotContainsString('BACKUP_ENCRYPTION_KEY', $session);
        $this->assertFalse(session()->has(InstallService::SESSION_FLOW));
    }
}
