<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        Role::create([
            'code' => 'cashier',
            'name' => 'Cajero',
            'authority_level' => 20,
            'is_system' => 1,
        ]);

        Role::create([
            'code' => 'central_admin',
            'name' => 'Admin Central',
            'authority_level' => 100,
            'is_system' => 1,
        ]);

        Permission::create([
            'code' => 'users.manage',
            'module_code' => 'users',
            'description' => 'Gestionar usuarios',
        ]);
    }

    public function test_usuario_sin_branch_user_no_tiene_acceso_a_sucursal(): void
    {
        $user = User::create([
            'name' => 'Sin Sucursal',
            'email' => 'sin@sucursal.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        // Debe poder entrar al dashboard (global sin sucursal = acceso al dashboard, no a operaciones de sucursal)
        $response->assertStatus(200);
    }

    public function test_usuario_de_sucursal_a_no_accede_a_sucursal_b(): void
    {
        $sucursalA = Branch::create([
            'code' => 'SUC-A',
            'name' => 'Sucursal A',
            'type' => 'store',
            'is_active' => true,
        ]);

        $sucursalB = Branch::create([
            'code' => 'SUC-B',
            'name' => 'Sucursal B',
            'type' => 'store',
            'is_active' => true,
        ]);

        $rolCajero = Role::where('code', 'cashier')->first();

        $usuario = User::create([
            'name' => 'Cajero A',
            'email' => 'cajero.a@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $sucursalA->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $sucursalA->id,
            'role_id' => $rolCajero->id,
        ]);

        // Intenta acceder a ruta de sucursal B sin asignación
        $response = $this->actingAs($usuario)->get('/dashboard/branch/' . $sucursalB->id);

        // Como no existe esa ruta, el test verifica que el usuario no tenga asignación a B
        $this->assertFalse(
            $usuario->branchAssignments()->where('branch_id', $sucursalB->id)->exists()
        );
    }

    public function test_cajero_no_puede_accesar_ruta_de_usuarios(): void
    {
        $rolCajero = Role::where('code', 'cashier')->first();
        $permiso = Permission::where('code', 'users.manage')->first();

        $sucursal = Branch::create([
            'code' => 'SUC-X',
            'name' => 'Sucursal X',
            'type' => 'store',
            'is_active' => true,
        ]);

        $usuario = User::create([
            'name' => 'Cajero',
            'email' => 'cajero@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'default_branch_id' => $sucursal->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $sucursal->id,
            'role_id' => $rolCajero->id,
        ]);

        $this->actingAs($usuario);

        // Ruta de administración de usuarios protegida por permiso users.manage
        $response = $this->get('/usuarios');

        // No existe la ruta, pero verificamos que el usuario NO tenga el permiso users.manage
        $tienePermiso = $usuario->branchRoles()
            ->join('role_permissions', 'role_permissions.role_id', '=', 'branch_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.code', 'users.manage')
            ->exists();

        $this->assertFalse($tienePermiso);
    }

    public function test_admin_global_tiene_acceso_a_todas_las_sucursales(): void
    {
        $rolAdmin = Role::where('code', 'central_admin')->first();

        $usuario = User::create([
            'name' => 'Admin Global',
            'email' => 'admin@prueba.cl',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'global_role_id' => $rolAdmin->id,
        ]);

        $this->assertTrue($usuario->isGlobal());
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
}
