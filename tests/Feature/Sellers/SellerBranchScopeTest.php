<?php

namespace Tests\Feature\Sellers;

use App\Models\Branch;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Alcance por sucursal: visibilidad, filtros, preservación de asignaciones y validaciones.
 */
class SellerBranchScopeTest extends SellerTestCase
{
    private function managerFor(Branch $branch): User
    {
        return $this->makeBranchUser(
            $branch,
            $this->makeRoleWithPermissions(['sellers.manage'])
        );
    }

    /**
     * @return list<int>
     */
    private function branchIdsOf(Seller $seller): array
    {
        return DB::table('branch_seller')
            ->where('seller_id', $seller->id)
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    public function test_listado_y_detalle_solo_muestran_sucursales_permitidas(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        $sellerAB = $this->createSeller($this->admin, [$branchA->id, $branchB->id], ['code' => 'SCOPE-AB']);
        $sellerA = $this->createSeller($this->admin, [$branchA->id], ['code' => 'SCOPE-A']);
        $sellerB = $this->createSeller($this->admin, [$branchB->id], ['code' => 'SCOPE-B']);

        $manager = $this->managerFor($branchA);

        $this->actingAs($manager)
            ->get('/sellers')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($branchA, $sellerB): void {
                $props = $page->toArray()['props'];
                $this->assertSame(2, $props['sellers']['total']);

                $byCode = collect($props['sellers']['data'])->keyBy('code');

                $this->assertTrue($byCode->has('SCOPE-AB'));
                $this->assertTrue($byCode->has('SCOPE-A'));
                $this->assertFalse($byCode->has('SCOPE-B'));
                $this->assertSame([$branchA->id], array_column($byCode['SCOPE-AB']['branches'], 'id'));
                $this->assertNotContains($sellerB->id, array_column($props['sellers']['data'], 'id'));
            });

        $this->actingAs($manager)
            ->get("/sellers/{$sellerAB->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seller.has_hidden_assignments', true)
                ->where('seller.branches', fn ($branches): bool => count($branches) === 1
                    && $branches[0]['code'] === $branchA->code));

        $this->actingAs($manager)->get("/sellers/{$sellerB->id}")->assertNotFound();
    }

    public function test_filtro_de_sucursal_solo_acepta_sucursales_permitidas(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        $this->createSeller($this->admin, [$branchA->id], ['code' => 'FILTER-A']);
        $this->createSeller($this->admin, [$branchB->id], ['code' => 'FILTER-B']);

        $manager = $this->managerFor($branchA);

        $this->actingAs($manager)
            ->get("/sellers?branch_id={$branchA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sellers.total', 1));

        $this->actingAs($manager)
            ->get("/sellers?branch_id={$branchB->id}")
            ->assertSessionHasErrors('branch_id');
    }

    public function test_crear_exige_sucursal_permitida_activa(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $manager = $this->managerFor($branchA);

        $this->actingAs($manager)
            ->post('/sellers', $this->sellerPayload(['code' => 'NO-BRANCH', 'branches' => []]))
            ->assertSessionHasErrors('branches');

        $this->actingAs($manager)
            ->post('/sellers', $this->sellerPayload(['code' => 'OUT-BRANCH', 'branches' => [$branchB->id]]))
            ->assertSessionHasErrors('branches');

        $this->assertSame(0, $this->sellerCount());

        $inactive = $this->makeBranch(['is_active' => false]);
        $this->assignBranch($manager, $inactive, $this->makeRoleWithPermissions(['sellers.manage']));

        $this->actingAs($manager)
            ->post('/sellers', $this->sellerPayload(['code' => 'INACTIVE-BRANCH', 'branches' => [$inactive->id]]))
            ->assertSessionHasErrors('branches');

        $seller = $this->createSeller($manager, [$branchA->id], ['code' => 'IN-BRANCH']);
        $this->assertSame([$branchA->id], $this->branchIdsOf($seller));
    }

    public function test_edicion_preserva_asignaciones_fuera_de_alcance(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $branchC = $this->makeBranch();

        $seller = $this->createSeller($this->admin, [$branchA->id, $branchB->id, $branchC->id], [
            'code' => 'PRESERVE-1',
        ]);

        $manager = $this->managerFor($branchA);

        $this->actingAs($manager)
            ->patch("/sellers/{$seller->id}", [
                'name' => 'Nombre Editado',
                'branches' => [$branchA->id],
            ])
            ->assertRedirect();

        $this->assertSame([$branchA->id, $branchB->id, $branchC->id], $this->branchIdsOf($seller));
        $this->assertSame('Nombre Editado', $seller->refresh()->name);
    }

    public function test_no_puede_agregar_ni_quitar_sucursales_fuera_de_alcance(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        $seller = $this->createSeller($this->admin, [$branchA->id], ['code' => 'OUT-EDIT']);
        $manager = $this->managerFor($branchA);

        $this->actingAs($manager)
            ->patch("/sellers/{$seller->id}", [
                'name' => 'No debe cambiar',
                'branches' => [$branchA->id, $branchB->id],
            ])
            ->assertSessionHasErrors('branches');

        $this->assertSame([$branchA->id], $this->branchIdsOf($seller));

        $sellerWithB = $this->createSeller($this->admin, [$branchA->id, $branchB->id], ['code' => 'PRESERVE-2']);

        $this->actingAs($manager)
            ->patch("/sellers/{$sellerWithB->id}", [
                'name' => $sellerWithB->name,
                'branches' => [],
            ])
            ->assertRedirect();

        // La sucursal fuera de alcance se conserva; solo se quitó la permitida.
        $this->assertSame([$branchB->id], $this->branchIdsOf($sellerWithB));
    }

    public function test_quitar_todas_las_sucursales_permitidas_falla_si_el_conjunto_queda_vacio(): void
    {
        $branchA = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branchA->id], ['code' => 'EMPTY-FINAL']);
        $manager = $this->managerFor($branchA);

        $this->actingAs($manager)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'branches' => [],
            ])
            ->assertSessionHasErrors('branches');

        $this->assertSame([$branchA->id], $this->branchIdsOf($seller));
    }

    public function test_editar_o_cambiar_estado_fuera_de_alcance_es_404(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $manager = $this->managerFor($branchA);
        $sellerB = $this->createSeller($this->admin, [$branchB->id], ['code' => 'OUT-404']);

        $this->actingAs($manager)
            ->patch("/sellers/{$sellerB->id}", ['name' => 'X', 'branches' => [$branchB->id]])
            ->assertNotFound();

        $this->actingAs($manager)
            ->post("/sellers/{$sellerB->id}/activate")
            ->assertNotFound();
    }

    public function test_usuario_vinculado_debe_acceder_a_todas_las_sucursales_finales(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $manager = $this->managerFor($branchA);

        $seller = $this->createSeller($this->admin, [$branchA->id, $branchB->id], ['code' => 'USER-FINAL']);

        $userA = $this->makeBranchUser($branchA, $this->makeRoleWithPermissions([]));

        $this->actingAs($manager)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'user_id' => $userA->id,
                'branches' => [$branchA->id],
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($seller->refresh()->user_id);

        $userAB = $this->makeBranchUser($branchA, $this->makeRoleWithPermissions([]));
        $this->assignBranch($userAB, $branchB, $this->makeRoleWithPermissions([]));

        $this->actingAs($manager)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'user_id' => $userAB->id,
                'branches' => [$branchA->id],
            ])
            ->assertRedirect();

        $this->assertSame($userAB->id, $seller->refresh()->user_id);
    }

    public function test_usuario_vinculado_puede_quedarse_sin_usuario(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], [
            'code' => 'UNLINK-1',
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame($this->admin->id, $seller->user_id);

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'user_id' => null,
                'branches' => [$branch->id],
            ])
            ->assertRedirect();

        $this->assertNull($seller->refresh()->user_id);
    }

    public function test_sucursal_inactiva_o_eliminada_no_puede_ser_asignada(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'BR-VALID']);

        $inactive = $this->makeBranch(['is_active' => false]);
        $trashed = $this->makeBranch();
        $trashed->delete();

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'branches' => [$inactive->id],
            ])
            ->assertSessionHasErrors('branches');

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'branches' => [$trashed->id],
            ])
            ->assertSessionHasErrors('branches');

        $this->assertSame([$branch->id], $this->branchIdsOf($seller));
    }
}
