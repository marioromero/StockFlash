<?php

namespace Tests\Feature\Setup;

use App\Support\Setup\SetupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemConfigsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base de las pruebas del asistente de primer inicio (SETUP-001).
 *
 * Usa exclusivamente SQLite :memory: vía phpunit.xml. Siembra roles y
 * configuración existentes y deja la instalación pendiente y virgen.
 */
abstract class SetupTestCase extends TestCase
{
    use RefreshDatabase;

    protected SetupService $setup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SystemConfigsSeeder::class);

        // La semilla deja `is_setup_completed='0'`; se fuerza igualmente para
        // no depender del orden de siembra.
        $this->markSetupIncomplete();

        $this->setup = app(SetupService::class);
    }

    protected function getFlowToken(): string
    {
        $response = $this->get('/setup');
        $response->assertOk();

        $token = session(SetupService::SESSION_KEY);
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Comercio Ficticio',
            'fiscal_id' => '76000000-0',
            'fiscal_name' => 'Comercio Ficticio SpA',
            'commercial_activity' => 'Venta minorista ficticia',
            'fiscal_address' => 'Calle Ficticia 123',
            'fiscal_commune' => 'Santiago',
            'responsible_name' => 'Responsable Ficticio',
            'responsible_email' => 'responsable@prueba.test',
            'responsible_phone' => '+56 2 1234 5678',
            'country' => 'CL',
            'currency_code' => 'CLP',
            'currency_decimals' => 0,
            'timezone' => 'America/Santiago',
            'locale' => 'es-CL',
            'prices_include_tax' => true,
            'cash_rounding_step' => 1,
            'branch_code' => 'SUC-01',
            'branch_name' => 'Sucursal Inicial',
            'branch_type' => 'store',
            'branch_address' => 'Avenida Ficticia 456',
            'branch_commune' => 'Providencia',
            'branch_city' => 'Santiago',
            'branch_region' => 'Metropolitana',
            'branch_phone' => '+56 2 8765 4321',
            'admin_name' => 'Admin Central',
            'admin_email' => 'admin@prueba.test',
            'admin_password' => 'ContrasenaSegura123!',
            'admin_password_confirmation' => 'ContrasenaSegura123!',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function postSetup(array $overrides = []): TestResponse
    {
        $token = $this->getFlowToken();

        return $this->post('/setup', $this->validPayload(array_merge(
            ['setup_token' => $token],
            $overrides,
        )));
    }

    protected function flagValue(): ?string
    {
        return \Illuminate\Support\Facades\DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->value('config_value');
    }
}
