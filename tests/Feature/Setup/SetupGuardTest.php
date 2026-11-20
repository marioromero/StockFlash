<?php

namespace Tests\Feature\Setup;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupGuardTest extends SetupTestCase
{
    public function test_rutas_privadas_redirigen_al_asistente_antes_del_setup(): void
    {
        foreach (['/dashboard', '/users', '/settings/general', '/catalog/products'] as $path) {
            $this->get($path)->assertRedirect(route('setup.show'));
        }
    }

    public function test_mutaciones_privadas_son_bloqueadas_con_423_antes_del_setup(): void
    {
        $this->post('/users', [])->assertStatus(423);
        $this->put('/settings/general', [])->assertStatus(423);
    }

    public function test_lectura_privada_con_expectativa_json_es_bloqueada_con_423(): void
    {
        $this->getJson('/dashboard')->assertStatus(423);
    }

    public function test_login_post_sigue_disponible_antes_del_setup(): void
    {
        $response = $this->post('/login', [
            'email' => 'nadie@prueba.test',
            'password' => 'ContrasenaSegura123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse($response->isRedirect(route('setup.show')));
    }

    public function test_home_docs_y_roadmap_siguen_disponibles_antes_del_setup(): void
    {
        $this->get('/')->assertOk();
        $this->get('/docs/')->assertOk();
        $this->get('/roadmap')->assertOk();
    }

    public function test_no_hay_bypass_por_query_ni_por_cabeceras(): void
    {
        $this->get('/dashboard?is_setup_completed=1')->assertRedirect(route('setup.show'));
        $this->get('/dashboard', ['X-Setup-Completed' => '1'])->assertRedirect(route('setup.show'));
        $this->withHeader('X-Setup-Completed', '1')
            ->get('/dashboard')
            ->assertRedirect(route('setup.show'));
    }

    public function test_tras_completar_setup_el_asistente_no_permite_reonboarding(): void
    {
        $this->markSetupCompleted();

        $this->get('/setup')->assertRedirect(route('login'));
        $this->post('/setup', $this->validPayload())->assertStatus(404);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, DB::table('branches')->count());
    }

    public function test_tras_completar_setup_las_rutas_privadas_retoman_el_flujo_normal(): void
    {
        $this->markSetupCompleted();

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_schema_no_preparado_bloquea_rutas_privadas_con_503(): void
    {
        Schema::drop('system_configs');

        $response = $this->get('/dashboard');

        $response->assertStatus(503);
        $response->assertSee('El esquema de la base de datos no está preparado', false);
        $this->assertFalse(Schema::hasTable('system_configs'));
    }

    public function test_schema_no_preparado_bloquea_roadmap_con_503_controlado(): void
    {
        Schema::drop('system_configs');

        $response = $this->get('/roadmap');

        $response->assertStatus(503);
        $response->assertSee('El esquema de la base de datos no está preparado', false);
        $this->assertFalse(Schema::hasTable('system_configs'));
    }
}
