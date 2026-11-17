<?php

namespace Tests\Feature\Setup;

use App\Http\Requests\BranchRequest;
use App\Http\Requests\BusinessSettingsRequest;
use App\Http\Requests\GeneralSettingsRequest;
use App\Http\Requests\SetupRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetupValidationTest extends SetupTestCase
{
    public function test_password_de_14_caracteres_es_rechazada(): void
    {
        $password = str_repeat('A', 14);

        $response = $this->postSetup([
            'admin_password' => $password,
            'admin_password_confirmation' => $password,
        ]);

        $response->assertSessionHasErrors([
            'admin_password' => 'La contraseña debe tener al menos 15 caracteres.',
        ]);
        $this->assertSame(0, User::query()->count());
        $this->assertSame('0', $this->flagValue());
    }

    public function test_password_de_15_caracteres_es_aceptada(): void
    {
        $password = str_repeat('A', 15);

        $response = $this->postSetup([
            'admin_password' => $password,
            'admin_password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame('1', $this->flagValue());
        $this->assertSame(1, User::query()->count());
    }

    public function test_password_de_64_caracteres_es_aceptada(): void
    {
        $password = str_repeat('B', 64);

        $response = $this->postSetup([
            'admin_password' => $password,
            'admin_password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(1, User::query()->count());
    }

    public function test_password_de_65_caracteres_es_rechazada(): void
    {
        $password = str_repeat('C', 65);

        $response = $this->postSetup([
            'admin_password' => $password,
            'admin_password_confirmation' => $password,
        ]);

        $response->assertSessionHasErrors([
            'admin_password' => 'La contraseña no puede superar los 64 caracteres.',
        ]);
        $this->assertSame(0, User::query()->count());
    }

    public function test_confirmacion_distinta_es_rechazada(): void
    {
        $response = $this->postSetup([
            'admin_password_confirmation' => 'OtraContrasena12345',
        ]);

        $response->assertSessionHasErrors([
            'admin_password' => 'La confirmación de la contraseña no coincide.',
        ]);
        $this->assertSame(0, User::query()->count());
    }

    public function test_email_invalido_es_rechazado(): void
    {
        $response = $this->postSetup(['admin_email' => 'correo-invalido']);

        $response->assertSessionHasErrors([
            'admin_email' => 'El correo del administrador no es válido.',
        ]);
        $this->assertSame(0, User::query()->count());
    }

    public function test_codigo_de_sucursal_invalido_es_rechazado(): void
    {
        $response = $this->postSetup(['branch_code' => 'SUC 01']);

        $response->assertSessionHasErrors([
            'branch_code' => 'El código solo puede contener letras mayúsculas, números y guiones.',
        ]);
        $this->assertSame(0, Branch::query()->count());
    }

    public function test_locale_timezone_y_tipo_invalidos_son_rechazados(): void
    {
        $response = $this->postSetup([
            'locale' => 'es_CL',
            'timezone' => 'Zona/Inexistente',
            'branch_type' => 'deposito',
        ]);

        $response->assertSessionHasErrors(['locale', 'timezone', 'branch_type']);
        $this->assertSame(0, Branch::query()->count());
        $this->assertSame('0', $this->flagValue());
    }

    public function test_nombre_de_comercio_demasiado_largo_es_rechazado(): void
    {
        $response = $this->postSetup(['business_name' => str_repeat('X', 151)]);

        $response->assertSessionHasErrors([
            'business_name' => 'El nombre del comercio no puede superar los 150 caracteres.',
        ]);
    }

    public function test_claves_extra_producen_422_y_no_escriben_nada(): void
    {
        $forbidden = [
            'is_setup_completed' => '1',
            'is_demo' => '1',
            'global_role_id' => '1',
            'is_active' => '1',
            'license_status' => 'active',
            'campo_extra' => 'x',
            'branches' => [],
        ];

        foreach ($forbidden as $key => $value) {
            $response = $this->postSetup([$key => $value]);

            $response->assertSessionHasErrors([
                'payload' => 'El formulario contiene campos no permitidos.',
            ]);
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Branch::query()->count());
        $this->assertSame('0', $this->flagValue());
    }

    public function test_post_sin_token_de_flujo_responde_419(): void
    {
        $this->get('/setup')->assertOk();

        $payload = $this->validPayload();
        unset($payload['setup_token']);

        $this->post('/setup', $payload)->assertStatus(419);
        $this->assertSame(0, User::query()->count());
    }

    public function test_post_con_token_incorrecto_responde_419(): void
    {
        $this->get('/setup')->assertOk();

        $this->post('/setup', $this->validPayload(['setup_token' => 'token-incorrecto']))
            ->assertStatus(419);

        $this->assertSame(0, User::query()->count());
        $this->assertSame('0', $this->flagValue());
    }

    public function test_normaliza_mayusculas_y_minusculas_antes_de_persistir(): void
    {
        $response = $this->postSetup([
            'branch_code' => '  suc-002  ',
            'admin_email' => '  ADMIN.MAYUS@PRUEBA.TEST ',
            'business_name' => '  Comercio con espacios  ',
        ]);

        $response->assertRedirect(route('login'));

        $branch = Branch::query()->firstOrFail();
        $admin = User::query()->firstOrFail();

        $this->assertSame('SUC-002', $branch->code);
        $this->assertSame('admin.mayus@prueba.test', $admin->email);
        $this->assertSame(
            'Comercio con espacios',
            DB::table('system_configs')->where('config_key', 'business_name')->value('config_value'),
        );
    }

    public function test_reglas_replicadas_no_divergen_de_las_clases_aprobadas(): void
    {
        $setup = (new SetupRequest)->rules();
        $business = (new BusinessSettingsRequest)->rules();
        $general = (new GeneralSettingsRequest)->rules();

        foreach ($business as $key => $rules) {
            $this->assertSame($rules, $setup[$key], "Regla CFG-002 divergente: {$key}");
        }

        foreach ($general as $key => $rules) {
            $this->assertSame($rules, $setup[$key], "Regla CFG-003 divergente: {$key}");
        }

        $branch = (new BranchRequest)->rules();
        $this->assertSame($branch['name'], $setup['branch_name']);
        $this->assertSame((string) $branch['type'][2], (string) $setup['branch_type'][2]);
        $this->assertSame($branch['type'][0], $setup['branch_type'][0]);
        $this->assertSame($branch['type'][1], $setup['branch_type'][1]);
        $this->assertSame($branch['address'], $setup['branch_address']);
        $this->assertSame($branch['commune'], $setup['branch_commune']);
        $this->assertSame($branch['city'], $setup['branch_city']);
        $this->assertSame($branch['region'], $setup['branch_region']);
        $this->assertSame($branch['phone'], $setup['branch_phone']);
        $this->assertSame(array_slice($branch['code'], 0, 4), array_slice($setup['branch_code'], 0, 4));
    }

    public function test_password_admin_no_queda_en_old_input_tras_error_de_validacion(): void
    {
        $password = str_repeat('A', 14);

        $response = $this->postSetup([
            'admin_password' => $password,
            'admin_password_confirmation' => $password,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'admin_password' => 'La contraseña debe tener al menos 15 caracteres.',
        ]);

        $old = session()->get('_old_input');
        $this->assertIsArray($old);
        $this->assertArrayNotHasKey('admin_password', $old);
        $this->assertArrayNotHasKey('admin_password_confirmation', $old);
        $this->assertSame('admin@prueba.test', $old['admin_email'] ?? null);
        $this->assertSame('0', $this->flagValue());
    }
}
