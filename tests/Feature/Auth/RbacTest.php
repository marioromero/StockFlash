<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        // Roles de sistema.
        $this->rolCajero = Role::create([
            'code' => 'cashier',
            'name' => 'Cajero',
            'authority_level' => 20,
            'is_system' => 1,
        ]);

        $this->rolAdmin = Role::create([
            'code' => 'central_admin',
            'name' => 'Admin Central',
            'authority_level' => 100,
            'is_system' => 1,
        ]);

        // Permisos.
        $this->permUsuarios = Permission::create([
            'code' => 'users.manage',
            'module_code' => 'users',
            'description' => 'Gestionar usuarios',
        ]);

        // Sucursales.
        $this->sucursalA = Branch::create([
            'code' => 'SUC-A',
            'name' => 'Sucursal A',
            'type' => 'store',
            'is_active' => true,
        ]);

        $this->sucursalB = Branch::create([
            'code' => 'SUC-B',
            'name' => 'Sucursal B',
            'type' => 'store',
            'is_active' => true,
        ]);
    }

    private function asignarPermisoARol(int $rolId, int $permisoId): void
    {
        DB::table('role_permissions')->insert([
            'role_id' => $rolId,
            'permission_id' => $permisoId,
        ]);
    }

    private function revocarPermisoDeRol(int $rolId, int $permisoId): void
    {
        DB::table('role_permissions')
            ->where('role_id', $rolId)
            ->where('permission_id', $permisoId)
            ->delete();
    }

    public function test_cajero_sin_permiso_usuarios_recibe_403(): void
    {
        // El cajero NO tiene permiso users.manage.
        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $response = $this->actingAs($usuario)->get('/usuarios');

        $response->assertStatus(403);
    }

    public function test_admin_global_con_permiso_usuarios_recibe_200(): void
    {
        // El admin global tiene el permiso a través de su rol global.
        $this->asignarPermisoARol($this->rolAdmin->id, $this->permUsuarios->id);

        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        $response = $this->actingAs($usuario)->get('/usuarios');

        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'seccion' => 'usuarios']);
    }

    public function test_usuario_sucursal_a_no_accede_a_sucursal_b(): void
    {
        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        // Solo tiene asignación en sucursal A.
        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        // Intenta acceder a la sucursal B: debe recibir 403.
        $response = $this->actingAs($usuario)->get('/dashboard/branch/' . $this->sucursalB->code);

        $response->assertStatus(403);
    }

    public function test_usuario_sucursal_a_accede_a_sucursal_a(): void
    {
        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $response = $this->actingAs($usuario)->get('/dashboard/branch/' . $this->sucursalA->code);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'sucursal' => 'SUC-A']);
    }

    public function test_admin_global_sin_sucursal_accede_a_dashboard(): void
    {
        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        // No hay filas en branch_user, ni sucursales asignadas.
        $response = $this->actingAs($usuario)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_revocacion_de_permiso_deniega_acceso(): void
    {
        // Admin global con permiso.
        $this->asignarPermisoARol($this->rolAdmin->id, $this->permUsuarios->id);

        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        $response = $this->actingAs($usuario)->get('/usuarios');
        $response->assertStatus(200);

        // Revoca el permiso.
        $this->revocarPermisoDeRol($this->rolAdmin->id, $this->permUsuarios->id);

        $response = $this->actingAs($usuario)->get('/usuarios');
        $response->assertStatus(403);
    }

    public function test_cuenta_desactivada_no_puede_autenticarse(): void
    {
        User::create([
            'name' => 'Inactivo',
            'email' => 'inactivo@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactivo@prueba.cl',
            'password' => 'ContrasenaSegura123!',
        ]);

        $this->assertGuest();
    }

    public function test_cambio_de_rol_revoca_permiso_anterior(): void
    {
        // Usuario inicialmente cajero (sin users.manage).
        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'usuario@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $response = $this->actingAs($usuario)->get('/usuarios');
        $response->assertStatus(403);

        // Cambia el rol a admin global con permiso users.manage.
        $this->asignarPermisoARol($this->rolAdmin->id, $this->permUsuarios->id);
        $usuario->update(['global_role_id' => $this->rolAdmin->id]);
        $usuario->branchAssignments()->delete();

        $response = $this->actingAs($usuario)->get('/usuarios');
        $response->assertStatus(200);
    }

    public function test_usuario_sin_sucursal_no_accede_a_ruta_con_sucursal(): void
    {
        // Usuario sin sucursal asignada.
        $usuario = User::create([
            'name' => 'Sin Sucursal',
            'email' => 'sin.sucursal@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => null,
            'global_role_id' => null,
        ]);

        // Intenta acceder a una ruta con contexto de sucursal: debe recibir 403.
        $response = $this->actingAs($usuario)->get('/dashboard/branch/' . $this->sucursalA->code);

        $response->assertStatus(403);
    }

    public function test_usuario_sin_sucursal_y_sin_parametro_recibe_403(): void
    {
        Route::middleware(['web', 'auth', 'branch.access'])
            ->get('/test/branch-access', fn () => response('ok'));

        $usuario = User::create([
            'name' => 'Sin Sucursal',
            'email' => 'sin.sucursal@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => null,
            'global_role_id' => null,
        ]);

        $this->actingAs($usuario)
            ->get('/test/branch-access')
            ->assertStatus(403);
    }

    public function test_sucursal_inexistente_por_id_recibe_403(): void
    {
        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard/branch/999999')
            ->assertStatus(403);
    }

    public function test_sucursal_inexistente_por_codigo_recibe_403(): void
    {
        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard/branch/NO-EXISTE')
            ->assertStatus(403);
    }

    public function test_sucursal_valida_por_id_permite_acceso(): void
    {
        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard/branch/' . $this->sucursalA->id)
            ->assertStatus(200);
    }

    public function test_admin_global_accede_a_sucursal_valida(): void
    {
        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard/branch/' . $this->sucursalB->code)
            ->assertStatus(200);
    }

    public function test_admin_global_con_codigo_inexistente_recibe_403(): void
    {
        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'default_branch_id' => null,
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard/branch/NO-EXISTE')
            ->assertStatus(403);
    }

    public function test_admin_global_con_id_inexistente_recibe_403(): void
    {
        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'default_branch_id' => null,
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard/branch/999999')
            ->assertStatus(403);
    }

    public function test_admin_global_sin_contexto_de_sucursal_recibe_403(): void
    {
        Route::middleware(['web', 'auth', 'branch.access'])
            ->get('/test/branch-global-sin-contexto', fn () => response('ok'));

        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $this->rolAdmin->id,
            'default_branch_id' => null,
        ]);

        $this->actingAs($usuario)
            ->get('/test/branch-global-sin-contexto')
            ->assertStatus(403);
    }

    public function test_parametro_de_middleware_acepta_codigo_de_sucursal(): void
    {
        Route::middleware(['web', 'auth', 'branch.access:SUC-A'])
            ->get('/test/branch-middleware-code', fn () => response('ok'));

        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalA->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalA->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $this->actingAs($usuario)
            ->get('/test/branch-middleware-code')
            ->assertStatus(200);
    }

    public function test_sucursal_soft_deleted_recibe_403(): void
    {
        $usuario = User::create([
            'name' => 'Cajero B',
            'email' => 'cajero.b@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $this->sucursalB->id,
            'global_role_id' => null,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $this->sucursalB->id,
            'role_id' => $this->rolCajero->id,
        ]);

        $this->sucursalB->delete();

        $this->actingAs($usuario)
            ->get('/dashboard/branch/' . $this->sucursalB->code)
            ->assertStatus(403);
    }
}
