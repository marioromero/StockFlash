<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        Role::create([
            'code' => 'central_admin',
            'name' => 'Admin Central',
            'authority_level' => 100,
            'max_discount_percent' => 100.00,
            'is_system' => 1,
        ]);

        Permission::create([
            'code' => 'users.manage',
            'module_code' => 'users',
            'description' => 'Gestionar usuarios',
        ]);
    }

    public function test_cambio_de_contrasena_exitoso(): void
    {
        $user = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => 1,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->put('/password/change', [
            'current_password' => 'ContrasenaSegura123!',
            'password' => 'NuevaContrasenaSegura456!',
            'password_confirmation' => 'NuevaContrasenaSegura456!',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();

        $this->assertTrue(
            Hash::check('NuevaContrasenaSegura456!', $user->fresh()->password)
        );
    }

    public function test_contrasena_actual_incorrecta_no_cambia(): void
    {
        $user = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put('/password/change', [
            'current_password' => 'Incorrecta',
            'password' => 'NuevaContrasenaSegura456!',
            'password_confirmation' => 'NuevaContrasenaSegura456!',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_contrasena_nueva_muy_corta_no_cambia(): void
    {
        $user = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put('/password/change', [
            'current_password' => 'ContrasenaSegura123!',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_no_autenticado_no_puede_cambiar_contrasena(): void
    {
        $response = $this->put('/password/change', [
            'current_password' => 'ContrasenaSegura123!',
            'password' => 'NuevaContrasenaSegura456!',
            'password_confirmation' => 'NuevaContrasenaSegura456!',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
