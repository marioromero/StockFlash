<?php

namespace Tests\Feature\Branches;

use App\Models\BranchUser;
use App\Models\Role;

class BranchAuthorizationTest extends BranchTestCase
{
    public function test_usuario_sin_settings_manage_recibe_403_en_todas_las_operaciones(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-AUTH']);
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();
        $sinPermiso = $this->makeUser(['global_role_id' => $cashierRole->id]);

        $this->actingAs($sinPermiso)->get('/branches')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/branches/create')->assertForbidden();
        $this->actingAs($sinPermiso)->get("/branches/{$branch->id}/edit")->assertForbidden();
        $this->actingAs($sinPermiso)
            ->post('/branches', $this->validPayload(['code' => 'SUC-NO']))
            ->assertForbidden();
        $this->actingAs($sinPermiso)
            ->put("/branches/{$branch->id}", $this->validPayload(['code' => 'SUC-AUTH']))
            ->assertForbidden();
        $this->actingAs($sinPermiso)
            ->post("/branches/{$branch->id}/deactivate")
            ->assertForbidden();
        $this->actingAs($sinPermiso)
            ->post("/branches/{$branch->id}/activate")
            ->assertForbidden();
        $this->actingAs($sinPermiso)
            ->delete("/branches/{$branch->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
        $this->assertDatabaseMissing('branches', ['code' => 'SUC-NO']);
    }

    public function test_usuario_por_sucursal_sin_settings_manage_recibe_403_en_index(): void
    {
        $this->makeBranch(['code' => 'SUC-AUTH2']);
        $usuario = $this->makeUser(['global_role_id' => null]);

        $this->actingAs($usuario)->get('/branches')->assertForbidden();
    }

    public function test_usuario_por_sucursal_sin_settings_manage_recibe_403_en_edicion_y_mutaciones(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-AUTH3']);
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $usuario = $this->makeUser([
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $branch->id,
            'role_id' => $cashierRole->id,
        ]);

        $this->actingAs($usuario)->get("/branches/{$branch->id}/edit")->assertForbidden();
        $this->actingAs($usuario)
            ->put("/branches/{$branch->id}", $this->validPayload(['code' => 'SUC-AUTH3']))
            ->assertForbidden();
        $this->actingAs($usuario)
            ->post("/branches/{$branch->id}/deactivate")
            ->assertForbidden();
        $this->actingAs($usuario)
            ->post("/branches/{$branch->id}/activate")
            ->assertForbidden();
        $this->actingAs($usuario)
            ->delete("/branches/{$branch->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
    }

    public function test_sucursal_desactivada_responde_403_en_branch_access_incluso_para_global(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-OFF', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get("/dashboard/branch/{$branch->code}")
            ->assertForbidden();

        // El dashboard global y la administración siguen accesibles.
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $this->actingAs($this->admin)->get('/branches')->assertOk();
    }

    public function test_sucursal_desactivada_responde_403_para_usuario_por_sucursal(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-OFF2', 'is_active' => false]);
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $usuario = $this->makeUser([
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $branch->id,
            'role_id' => $cashierRole->id,
        ]);

        $this->actingAs($usuario)
            ->get("/dashboard/branch/{$branch->code}")
            ->assertForbidden();
    }

    public function test_codigo_numerico_de_sucursal_se_resuelve_por_codigo_cuando_no_existe_ese_id(): void
    {
        // El código es puramente numérico y no coincide con el ID de ninguna
        // sucursal: debe resolverse por código y no interpretarse solo como ID.
        $branch = $this->makeBranch(['code' => '123', 'name' => 'Sucursal Numérica']);

        $this->assertNotSame('123', (string) $branch->id);

        $this->actingAs($this->admin)
            ->get('/dashboard/branch/123')
            ->assertOk();
    }

    public function test_sucursal_activa_con_acceso_asignado_sigue_permitiendo_operar(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-ON']);
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();

        $usuario = $this->makeUser([
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $branch->id,
            'role_id' => $cashierRole->id,
        ]);

        $this->actingAs($usuario)
            ->get("/dashboard/branch/{$branch->code}")
            ->assertOk();
    }
}
