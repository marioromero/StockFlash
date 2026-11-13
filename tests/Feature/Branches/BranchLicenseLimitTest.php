<?php

namespace Tests\Feature\Branches;

use Illuminate\Support\Facades\DB;

class BranchLicenseLimitTest extends BranchTestCase
{
    public function test_limite_de_plan_permite_hasta_el_maximo_y_rechaza_el_siguiente(): void
    {
        $this->setMaxBranches(2);

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-L1']))
            ->assertRedirect(route('branches.index'));

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-L2']))
            ->assertRedirect(route('branches.index'));

        $response = $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-L3']));

        $response->assertSessionHasErrors('max_branches');
        $this->assertDatabaseMissing('branches', ['code' => 'SUC-L3']);
        $this->assertDatabaseCount('branches', 2);
    }

    public function test_max_branches_cero_significa_ilimitado(): void
    {
        $this->setMaxBranches(0);

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($this->admin)
                ->post('/branches', $this->validPayload(['code' => "SUC-IL{$i}"]))
                ->assertRedirect(route('branches.index'));
        }

        $this->assertDatabaseCount('branches', 3);
    }

    public function test_sin_fila_de_licencia_se_bloquea_crear_sucursales(): void
    {
        DB::table('license_states')->delete();
        $this->makeBranch(['code' => 'SUC-PRE']);

        $response = $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-NL']));

        $response->assertSessionHasErrors('max_branches');
        $this->assertDatabaseMissing('branches', ['code' => 'SUC-NL']);
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_reactivar_sucursal_respeta_el_limite_de_plan(): void
    {
        $this->setMaxBranches(1);

        $activa = $this->makeBranch(['code' => 'SUC-RA', 'is_active' => true]);
        $inactiva = $this->makeBranch(['code' => 'SUC-RI', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->post("/branches/{$inactiva->id}/activate");

        $response->assertSessionHasErrors('max_branches');
        $this->assertDatabaseHas('branches', ['id' => $inactiva->id, 'is_active' => 0]);
        $this->assertDatabaseHas('branches', ['id' => $activa->id, 'is_active' => 1]);

        // Al liberar un cupo, la reactivación es aceptada.
        $this->actingAs($this->admin)
            ->post("/branches/{$activa->id}/deactivate")
            ->assertRedirect(route('branches.index'));

        $this->actingAs($this->admin)
            ->post("/branches/{$inactiva->id}/activate")
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['id' => $inactiva->id, 'is_active' => 1]);
    }

    public function test_crear_sucursal_inactiva_no_consume_cupo_del_plan(): void
    {
        $this->setMaxBranches(1);
        $this->makeBranch(['code' => 'SUC-ACT', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'SUC-INACT',
            'is_active' => false,
        ]));

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseHas('branches', ['code' => 'SUC-INACT', 'is_active' => 0]);
    }
}
