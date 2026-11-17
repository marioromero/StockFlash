<?php

namespace Tests\Feature\Setup;

use App\Models\Branch;
use App\Models\User;
use App\Support\Setup\SetupEnvironmentPolicy;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class SetupEnvironmentGateTest extends SetupTestCase
{
    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        config([
            'setup.production_onboarding_enabled' => false,
            'setup.recovery_package_available' => false,
        ]);

        parent::tearDown();
    }

    public function test_politica_pura_permite_dev_test_local_y_demo(): void
    {
        $policy = new SetupEnvironmentPolicy;

        foreach (['local', 'testing', 'staging', 'development'] as $env) {
            $result = $policy->evaluate(false, $env);
            $this->assertTrue($result['allowed'], "Entorno {$env} debería permitir onboarding");
            $this->assertNull($result['code']);
            $this->assertSame('', $result['message']);
        }

        $demo = $policy->evaluate(true, 'production');
        $this->assertTrue($demo['allowed']);
        $this->assertNull($demo['code']);
    }

    public function test_politica_pura_bloquea_produccion_no_demo_con_codigo_explicito(): void
    {
        $policy = new SetupEnvironmentPolicy;

        $result = $policy->evaluate(false, 'production');

        $this->assertFalse($result['allowed']);
        $this->assertSame('NOT_READY_FOR_PRODUCTION', $result['code']);
        $this->assertNotSame('', $result['message']);
        $this->assertStringContainsString('BKP-08', $result['message']);

        $this->assertFalse($policy->evaluate(false, 'production', true, false)['allowed']);
        $this->assertFalse($policy->evaluate(false, 'production', false, true)['allowed']);
        $this->assertTrue($policy->evaluate(false, 'production', true, true)['allowed']);
    }

    public function test_banderas_de_configuracion_por_defecto_estan_apagadas(): void
    {
        $this->assertFalse((bool) config('setup.production_onboarding_enabled'));
        $this->assertFalse((bool) config('setup.recovery_package_available'));
    }

    public function test_produccion_no_demo_muestra_bloqueo_bkp08_sin_formulario(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Unavailable')
            ->where('reason_code', 'NOT_READY_FOR_PRODUCTION')
            ->missing('flow_token')
            ->missing('defaults')
        );
        $this->assertNull(session('setup_flow_token'));
        $this->assertSame('0', $this->flagValue());
        $this->assertFalse(app(SetupEnvironmentPolicy::class)->evaluateCurrent()['allowed']);
    }

    public function test_produccion_no_demo_bloquea_post_sin_marcar_ni_escribir(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // En producción simulada, Laravel deja de omitir CSRF en pruebas.
        // Se envía un token de sesión real para llegar a la política BKP-08.
        $this->withSession(['_token' => 'csrf-test-token']);

        $response = $this->post('/setup', $this->validPayload([
            'setup_token' => 'token',
            '_token' => 'csrf-test-token',
        ]));

        $response->assertStatus(423);
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Branch::query()->count());
        $this->assertSame('0', $this->flagValue());
        $this->assertNull(
            DB::table('system_configs')->where('config_key', 'business_name')->value('config_value'),
        );
    }

    public function test_produccion_en_demo_permite_el_asistente(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        DB::table('system_configs')->where('config_key', 'is_demo')->update(['config_value' => '1']);

        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Setup/Wizard')->has('flow_token'));
        $this->assertTrue(app(SetupEnvironmentPolicy::class)->evaluateCurrent()['allowed']);
    }

    public function test_produccion_con_banderas_y_paquete_declarados_permite_el_asistente(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'setup.production_onboarding_enabled' => true,
            'setup.recovery_package_available' => true,
        ]);

        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Setup/Wizard'));
    }

    public function test_entorno_testing_permite_el_asistente_por_defecto(): void
    {
        $this->assertSame('testing', app()->environment());

        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Setup/Wizard'));
    }
}
