<?php

namespace Tests\Feature\Users;

use App\Models\BranchUser;
use Inertia\Testing\AssertableInertia as Assert;

class UserAuthorizationTest extends UserTestCase
{
    public function test_usuario_sin_users_manage_recibe_403_en_todas_las_rutas(): void
    {
        $target = $this->makeUser([
            'email' => 'objetivo@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $sinPermiso = $this->makeUser([
            'email' => 'sin.permiso@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($sinPermiso)->get('/users')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/users/create')->assertForbidden();
        $this->actingAs($sinPermiso)->get("/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($sinPermiso)
            ->post('/users', $this->validUserPayload())
            ->assertForbidden();
        $this->actingAs($sinPermiso)
            ->put("/users/{$target->id}", $this->validUserPayload([
                'name' => $target->name,
                'email' => $target->email,
                'password' => null,
            ]))
            ->assertForbidden();
        $this->actingAs($sinPermiso)->post("/users/{$target->id}/deactivate")->assertForbidden();
        $this->actingAs($sinPermiso)->post("/users/{$target->id}/activate")->assertForbidden();
        $this->actingAs($sinPermiso)->delete("/users/{$target->id}")->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => 'objetivo@prueba.cl',
            'is_active' => 1,
            'deleted_at' => null,
        ]);
    }

    public function test_usuario_por_sucursal_sin_users_manage_recibe_403(): void
    {
        $branch = $this->makeBranch();
        $supervisor = $this->role('supervisor');

        $usuario = $this->makeUser([
            'email' => 'supervisor@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $branch->id,
            'role_id' => $supervisor->id,
        ]);

        $this->actingAs($usuario)->get('/users')->assertForbidden();
        $this->actingAs($usuario)->post('/users', $this->validUserPayload())->assertForbidden();
    }

    public function test_mutacion_por_id_sin_permiso_no_cambia_estado_ni_audita(): void
    {
        $target = $this->makeUser([
            'email' => 'intacto@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => true,
        ]);
        $sinPermiso = $this->makeUser([
            'email' => 'intruso@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($sinPermiso)->delete("/users/{$target->id}")->assertForbidden();
        $this->actingAs($sinPermiso)->post("/users/{$target->id}/deactivate")->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => 1,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'user.deleted',
            'auditable_id' => $target->id,
        ]);
    }

    public function test_admin_con_users_manage_accede_a_todas_las_pantallas(): void
    {
        $target = $this->makeUser([
            'email' => 'visible@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)->get('/users')->assertOk();
        $this->actingAs($this->admin)->get('/users/create')->assertOk();
        $this->actingAs($this->admin)->get("/users/{$target->id}/edit")->assertOk();
    }

    public function test_no_autenticado_redirige_a_login(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->get('/users/create')->assertRedirect('/login');
    }

    public function test_ruta_de_prueba_usuarios_de_auth_001_sigue_disponible_para_admin(): void
    {
        $this->actingAs($this->admin)
            ->get('/usuarios')
            ->assertOk()
            ->assertJson(['ok' => true, 'seccion' => 'usuarios']);
    }

    public function test_dashboard_expone_users_manage_solo_a_quien_lo_tiene(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->contains('users.manage'))
            );

        $cashier = $this->makeUser([
            'email' => 'cajero.dashboard@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($cashier)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.permissions', fn ($permissions): bool => ! collect($permissions)->contains('users.manage'))
            );
    }
}
