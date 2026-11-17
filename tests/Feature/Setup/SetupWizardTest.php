<?php

namespace Tests\Feature\Setup;

use App\Models\Branch;
use App\Models\User;
use App\Support\Setup\SetupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

class SetupWizardTest extends SetupTestCase
{
    public function test_get_inicial_muestra_asistente_con_defaults_y_token(): void
    {
        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Wizard')
            ->where('flow_token', session(SetupService::SESSION_KEY))
            ->where('defaults.country', 'CL')
            ->where('defaults.currency_code', 'CLP')
            ->where('defaults.currency_decimals', 0)
            ->where('defaults.timezone', 'America/Santiago')
            ->where('defaults.locale', 'es-CL')
            ->where('defaults.prices_include_tax', true)
            ->where('defaults.cash_rounding_step', 1)
            ->has('business_fields.business_name')
            ->has('branch_types', 3)
            ->missing('admin_password')
        );

        $this->assertFalse($this->setup->isCompleted());
        $this->assertTrue($this->setup->isPristine());
    }

    public function test_get_reutiliza_el_mismo_token_de_flujo(): void
    {
        $this->get('/setup')->assertOk();
        $first = session(SetupService::SESSION_KEY);

        $this->get('/setup')->assertOk();
        $second = session(SetupService::SESSION_KEY);

        $this->assertIsString($first);
        $this->assertSame($first, $second);
    }

    public function test_get_cuando_ya_esta_completado_redirige_al_login_sin_props(): void
    {
        $this->markSetupCompleted();

        $response = $this->get('/setup');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('setup_flow_token');
    }

    public function test_get_con_usuario_preexistente_muestra_pantalla_informativa_sin_formulario(): void
    {
        User::create([
            'name' => 'Usuario Existente',
            'email' => 'existente@prueba.test',
            'password' => 'ContrasenaSegura123!',
            'global_role_id' => DB::table('roles')->where('code', 'central_admin')->value('id'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Unavailable')
            ->where('reason_code', 'INSTANCE_NOT_PRISTINE')
            ->missing('flow_token')
            ->missing('defaults')
        );
    }

    public function test_get_con_sucursal_preexistente_muestra_pantalla_informativa_sin_formulario(): void
    {
        Branch::create([
            'code' => 'SUC-EXISTENTE',
            'name' => 'Sucursal Existente',
            'type' => 'store',
            'is_active' => true,
            'requires_cash_control' => true,
        ]);

        $response = $this->get('/setup');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Unavailable')
            ->where('reason_code', 'INSTANCE_NOT_PRISTINE')
            ->missing('flow_token')
        );
    }

    public function test_schema_no_preparado_responde_503_sin_ejecutar_migraciones(): void
    {
        Schema::drop('system_configs');

        $response = $this->get('/setup');

        $response->assertStatus(503);
        $response->assertSee('no ejecuta migraciones automáticamente', false);
        $this->assertFalse(Schema::hasTable('system_configs'));
        $this->assertFalse($this->setup->schemaReady());
    }

    public function test_post_con_schema_no_preparado_responde_503_controlado(): void
    {
        Schema::drop('system_configs');

        $response = $this->post('/setup', $this->validPayload(['setup_token' => 'x']));

        $response->assertStatus(503);
        $this->assertFalse(Schema::hasTable('system_configs'));
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Branch::query()->count());
    }

    public function test_default_contractual_de_prices_include_tax_si_falta_la_fila(): void
    {
        DB::table('system_configs')
            ->where('config_key', 'prices_include_tax')
            ->delete();

        $defaults = $this->setup->initialDefaults();

        $this->assertTrue($defaults['prices_include_tax']);
        $this->assertSame('CL', $defaults['country']);
        $this->assertSame('CLP', $defaults['currency_code']);
        $this->assertSame(0, $defaults['currency_decimals']);
        $this->assertSame('America/Santiago', $defaults['timezone']);
        $this->assertSame('es-CL', $defaults['locale']);
        $this->assertSame(1, $defaults['cash_rounding_step']);
    }
}
