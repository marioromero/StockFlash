<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Transiciones de estado y props compartidas de Inertia (BRANCH-001).
 *
 * `is_active` solo cambia por las acciones dedicadas `activate`/`deactivate`;
 * la edición no debe permitir activar ni desactivar una sucursal.
 */
class BranchStateTransitionTest extends BranchTestCase
{
    public function test_props_compartidas_exponen_settings_manage_al_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.permissions', fn ($permissions): bool => collect($permissions)
                ->contains('settings.manage'))
        );
    }

    public function test_props_compartidas_no_exponen_settings_manage_a_usuario_sin_permiso(): void
    {
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();
        $usuario = $this->makeUser(['global_role_id' => $cashierRole->id]);

        $response = $this->actingAs($usuario)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.permissions', fn ($permissions): bool => ! collect($permissions)
                ->contains('settings.manage'))
        );
    }

    public function test_transicion_activo_a_desactivado_por_accion_dedicada(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-T-OFF', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post("/branches/{$branch->id}/deactivate")
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 0]);

        $this->actingAs($this->admin)
            ->get('/branches')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branches/Index')
                ->where('branches.0.id', $branch->id)
                ->where('branches.0.is_active', false)
                ->where('active_count', 0)
            );
    }

    public function test_transicion_inactivo_a_activo_por_accion_dedicada(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-T-ON', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post("/branches/{$branch->id}/activate")
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);

        $this->actingAs($this->admin)
            ->get('/branches')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branches/Index')
                ->where('branches.0.id', $branch->id)
                ->where('branches.0.is_active', true)
                ->where('active_count', 1)
            );
    }

    public function test_update_con_is_active_no_activa_una_sucursal_inactiva(): void
    {
        $branch = $this->makeBranch([
            'code' => 'SUC-T-UPD',
            'name' => 'Nombre Original',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->put("/branches/{$branch->id}", $this->validPayload([
            'code' => 'SUC-T-UPD',
            'name' => 'Nombre No Aplicado',
            'is_active' => true,
        ]));

        $response->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Nombre Original',
            'is_active' => 0,
        ]);
    }

    public function test_editar_sucursal_inactiva_con_is_active_true_es_rechazado_por_regla_prohibited(): void
    {
        $this->setMaxBranches(1);

        $activa = $this->makeBranch(['code' => 'SUC-T-LIM-A', 'is_active' => true]);
        $inactiva = $this->makeBranch(['code' => 'SUC-T-LIM-I', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->put("/branches/{$inactiva->id}", $this->validPayload([
                'code' => 'SUC-T-LIM-I',
                'is_active' => true,
            ]))
            ->assertSessionHasErrors('is_active');

        $this->assertSame(1, Branch::query()->where('is_active', 1)->count());
        $this->assertDatabaseHas('branches', ['id' => $activa->id, 'is_active' => 1]);
        $this->assertDatabaseHas('branches', ['id' => $inactiva->id, 'is_active' => 0]);
    }
}
