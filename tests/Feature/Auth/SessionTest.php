<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        // Configuración de sesiones (SEC-05).
        DB::table('system_configs')->insert([
            'config_key' => 'session_absolute_hours',
            'config_value' => '12',
            'value_type' => 'int',
        ]);
        DB::table('system_configs')->insert([
            'config_key' => 'session_idle_minutes_pos',
            'config_value' => '30',
            'value_type' => 'int',
        ]);
        DB::table('system_configs')->insert([
            'config_key' => 'session_idle_minutes_admin',
            'config_value' => '60',
            'value_type' => 'int',
        ]);

        $this->rolAdmin = Role::create([
            'code' => 'central_admin',
            'name' => 'Admin Central',
            'authority_level' => 100,
            'is_system' => 1,
        ]);

        $this->sucursal = Branch::create([
            'code' => 'SUC-1',
            'name' => 'Sucursal 1',
            'type' => 'store',
            'is_active' => true,
        ]);
    }

    public function test_usuario_desactivado_es_denegado_en_request_posterior(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        // Inicia sesión.
        $this->actingAs($usuario)->get('/dashboard')->assertStatus(200);

        // Desactiva el usuario.
        $usuario->update(['is_active' => false]);

        // Request posterior: debe ser denegado y sesión invalidada.
        $response = $this->actingAs($usuario)->get('/dashboard');
        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_sesion_expira_por_duracion_absoluta_usando_fallback_de_ultimo_login(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'last_login_at' => now()->subHours(13), // más de 12h
        ]);

        // Sin session_started_at: se usa last_login_at como fallback.
        $this->actingAs($usuario)->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_sesion_expira_por_duracion_absoluta_aunque_el_ultimo_login_sea_reciente(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'last_login_at' => now(), // login reciente, otra sesión del usuario
        ]);

        $this->actingAs($usuario)
            ->session([
                'session_started_at' => now()->subHours(13), // esta sesión superó 12h
                'last_activity' => now(),
            ])
            ->get('/dashboard')
            ->assertStatus(403);

        $this->assertGuest();
    }

    public function test_sesiones_del_mismo_usuario_son_independientes(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'last_login_at' => now(),
        ]);

        // Sesión A: iniciada hace 13h -> expira.
        $this->actingAs($usuario)
            ->session([
                'session_started_at' => now()->subHours(13),
                'last_activity' => now(),
            ])
            ->get('/dashboard')
            ->assertStatus(403);
        $this->assertGuest();

        // Sesión B: iniciada ahora -> sigue vigente.
        $this->actingAs($usuario)
            ->session([
                'session_started_at' => now(),
                'last_activity' => now(),
            ])
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_login_exitoso_registra_inicio_de_sesion(): void
    {
        User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@prueba.cl',
            'password' => 'ContrasenaSegura123!',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('session_started_at');
        $this->assertAuthenticated();
    }

    public function test_sesion_no_expira_antes_de_duracion_absoluta(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'last_login_at' => now(),
        ]);

        $this->actingAs($usuario)
            ->session([
                'session_started_at' => now()->subHours(11), // menos de 12h
                'last_activity' => now(),
            ])
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_sesion_expira_por_inactividad_pos(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => null, // no global = POS
            'default_branch_id' => $this->sucursal->id,
        ]);

        $this->actingAs($usuario)
            ->session(['last_activity' => now()->subMinutes(31)]) // más de 30 min
            ->get('/dashboard')
            ->assertStatus(403);

        $this->assertGuest();
    }

    public function test_sesion_no_expira_antes_de_inactividad_pos(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => null, // no global = POS
            'default_branch_id' => $this->sucursal->id,
        ]);

        $this->actingAs($usuario)
            ->session(['last_activity' => now()->subMinutes(29)]) // menos de 30 min
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_sesion_expira_por_inactividad_admin(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id, // global = admin
        ]);

        $this->actingAs($usuario)
            ->session(['last_activity' => now()->subMinutes(61)]) // más de 60 min
            ->get('/dashboard')
            ->assertStatus(403);

        $this->assertGuest();
    }

    public function test_sesion_no_expira_antes_de_inactividad_admin(): void
    {
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        $this->actingAs($usuario)
            ->session(['last_activity' => now()->subMinutes(59)]) // menos de 60 min
            ->get('/dashboard')
            ->assertStatus(200);
    }
}
