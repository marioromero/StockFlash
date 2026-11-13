<?php

namespace Tests\Feature\Branches;

use Illuminate\Support\Facades\DB;

class BranchLicenseGuardTest extends BranchTestCase
{
    public function test_sin_licencia_bloquea_crear_sucursales(): void
    {
        DB::table('license_states')->delete();
        $this->makeBranch(['code' => 'SUC-PREVIA']);

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-SIN-LIC']))
            ->assertSessionHasErrors('max_branches');

        $this->assertDatabaseMissing('branches', ['code' => 'SUC-SIN-LIC']);
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_sin_licencia_bloquea_crear_sucursal_inactiva(): void
    {
        DB::table('license_states')->delete();

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload([
                'code' => 'SUC-INACT-SIN-LIC',
                'is_active' => false,
            ]))
            ->assertSessionHasErrors('max_branches');

        $this->assertDatabaseMissing('branches', ['code' => 'SUC-INACT-SIN-LIC']);
    }

    public function test_sin_licencia_bloquea_reactivar_sucursales(): void
    {
        $inactiva = $this->makeBranch(['code' => 'SUC-REACT', 'is_active' => false]);
        DB::table('license_states')->delete();

        $this->actingAs($this->admin)
            ->post("/branches/{$inactiva->id}/activate")
            ->assertSessionHasErrors('max_branches');

        $this->assertDatabaseHas('branches', ['id' => $inactiva->id, 'is_active' => 0]);
    }

    public function test_licencia_registrada_con_cero_es_ilimitada(): void
    {
        $this->setMaxBranches(0);

        for ($i = 1; $i <= 4; $i++) {
            $this->actingAs($this->admin)
                ->post('/branches', $this->validPayload(['code' => "SUC-ILIM{$i}"]))
                ->assertRedirect(route('branches.index'));
        }

        $this->assertDatabaseCount('branches', 4);
    }

    public function test_limite_de_plan_se_respeta_en_altas_y_reactivaciones(): void
    {
        $this->setMaxBranches(1);

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-LIM-1']))
            ->assertRedirect(route('branches.index'));

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-LIM-2']))
            ->assertSessionHasErrors('max_branches');

        $inactiva = $this->makeBranch(['code' => 'SUC-LIM-OFF', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post("/branches/{$inactiva->id}/activate")
            ->assertSessionHasErrors('max_branches');

        $this->assertDatabaseHas('branches', ['id' => $inactiva->id, 'is_active' => 0]);
        $this->assertSame(1, DB::table('branches')->where('is_active', 1)->whereNull('deleted_at')->count());
    }

    public function test_suspension_fuera_de_gracia_bloquea_altas_y_reactivaciones(): void
    {
        config(['app.timezone' => 'America/Santiago']);

        $inactiva = $this->makeBranch(['code' => 'SUC-SUSP-OFF', 'is_active' => false]);

        DB::table('license_states')->update([
            'status' => 'suspended',
            'grace_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-SUSP-NEW']))
            ->assertSessionHasErrors('max_branches');

        $this->actingAs($this->admin)
            ->post("/branches/{$inactiva->id}/activate")
            ->assertSessionHasErrors('max_branches');

        $this->assertDatabaseMissing('branches', ['code' => 'SUC-SUSP-NEW']);
        $this->assertDatabaseHas('branches', ['id' => $inactiva->id, 'is_active' => 0]);
    }

    public function test_suspension_dentro_de_gracia_permite_operar(): void
    {
        DB::table('license_states')->update([
            'status' => 'suspended',
            'grace_until' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload(['code' => 'SUC-GRACIA']))
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['code' => 'SUC-GRACIA']);
    }
}
