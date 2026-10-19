<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        Role::create([
            'code' => 'seller',
            'name' => 'Vendedor',
            'authority_level' => 10,
            'max_discount_percent' => 5.00,
            'is_system' => 1,
        ]);

        Role::create([
            'code' => 'cashier',
            'name' => 'Cajero',
            'authority_level' => 20,
            'max_discount_percent' => 10.00,
            'is_system' => 1,
        ]);

        Role::create([
            'code' => 'central_admin',
            'name' => 'Admin Central',
            'authority_level' => 100,
            'max_discount_percent' => 100.00,
            'is_system' => 1,
        ]);

        Permission::create([
            'code' => 'sales.view',
            'module_code' => 'sales',
            'description' => 'Ver ventas',
        ]);

        Permission::create([
            'code' => 'users.manage',
            'module_code' => 'users',
            'description' => 'Gestionar usuarios',
        ]);
    }

    public function test_login_exitoso_redirige_a_dashboard(): void
    {
        $user = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => 3,
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@prueba.cl',
            'password' => 'ContrasenaSegura123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->is_active);
    }

    public function test_login_contrasena_incorrecta_no_autentica(): void
    {
        User::create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@prueba.cl',
            'password' => 'ContrasenaIncorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cinco_intentos_fallidos_bloquean_cuenta(): void
    {
        $user = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'usuario@prueba.cl',
                'password' => 'ContrasenaIncorrecta',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->isLocked());

        // El siguiente intento debe mostrar cuenta bloqueada
        $response = $this->post('/login', [
            'email' => 'usuario@prueba.cl',
            'password' => 'ContrasenaSegura123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_no_autenticado_redirige_a_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
