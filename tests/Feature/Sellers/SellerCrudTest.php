<?php

namespace Tests\Feature\Sellers;

use App\Models\Seller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * CRUD, código inmutable, comisión decimal y campos forjados (SEL-01).
 */
class SellerCrudTest extends SellerTestCase
{
    public function test_crear_normaliza_codigo_y_guarda_defaults_de_servidor(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser(['is_active' => true]);
        $this->assignBranch($user, $branch, $this->role('seller'));

        $this->actingAs($this->admin)
            ->post('/sellers', [
                'code' => '  ven.a_1-2  ',
                'name' => '  Vendedor Uno  ',
                'user_id' => $user->id,
                'commission_percent' => '12.5',
                'branches' => [$branch->id],
            ])
            ->assertRedirect();

        $seller = Seller::query()->where('code', 'VEN.A_1-2')->firstOrFail();

        $this->assertSame('Vendedor Uno', $seller->name);
        $this->assertSame('12.50', (string) $seller->commission_percent);
        $this->assertTrue($seller->is_active);
        $this->assertNull($seller->anonymized_at);
        $this->assertNull($seller->deleted_at);
        $this->assertSame($user->id, $seller->user_id);
        $this->assertDatabaseHas('branch_seller', [
            'seller_id' => $seller->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_usuario_vinculado_es_opcional_y_un_usuario_global_es_aceptado(): void
    {
        $branch = $this->makeBranch();

        $sinUsuario = $this->createSeller($this->admin, [$branch->id], [
            'code' => 'SIN-USER',
            'user_id' => null,
        ]);

        $conGlobal = $this->createSeller($this->admin, [$branch->id], [
            'code' => 'CON-GLOBAL',
            'user_id' => $this->admin->id,
        ]);

        $this->assertNull($sinUsuario->user_id);
        $this->assertSame($this->admin->id, $conGlobal->user_id);
    }

    public function test_codigo_normaliza_mayusculas_rechaza_formato_y_es_unico(): void
    {
        $branch = $this->makeBranch();

        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'abc.1_2-3']);
        $this->assertSame('ABC.1_2-3', $seller->code);

        foreach (['', 'CODIGO CON ESPACIO', str_repeat('X', 21), 'ñandú'] as $invalid) {
            $this->actingAs($this->admin)
                ->post('/sellers', $this->sellerPayload([
                    'code' => $invalid,
                    'branches' => [$branch->id],
                ]))
                ->assertSessionHasErrors('code');
        }

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'abc.1_2-3',
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_codigo_unico_incluye_variantes_de_caja_y_eliminados_logicamente(): void
    {
        $branch = $this->makeBranch();

        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'DUP-1']);

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'dup-1',
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('code');

        $seller->delete();

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'DUP-1',
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Seller::withTrashed()->where('code', 'DUP-1')->count());
    }

    public function test_la_base_rechaza_duplicados_aunque_el_prechequeo_se_omita(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'RACE-1']);

        try {
            DB::table('sellers')->insert([
                'code' => $seller->code,
                'name' => 'Duplicado',
                'commission_percent' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Se esperaba una violación de unicidad en la base.');
        } catch (QueryException) {
            $this->assertSame(1, Seller::withTrashed()->where('code', 'RACE-1')->count());
        }
    }

    public function test_codigo_es_inmutable_en_la_edicion(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'IMM-1']);

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'code' => 'OTRO-CODIGO',
                'name' => 'Nombre Nuevo',
                'branches' => [$branch->id],
            ])
            ->assertRedirect();

        $seller->refresh();
        $this->assertSame('IMM-1', $seller->code);
        $this->assertSame('Nombre Nuevo', $seller->name);

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'code' => 'bad code!',
                'name' => 'Nombre Nuevo',
                'branches' => [$branch->id],
            ])
            ->assertRedirect();

        $this->assertSame('IMM-1', $seller->refresh()->code);
    }

    public function test_comision_decimal_canonica_y_limites(): void
    {
        $branch = $this->makeBranch();

        foreach ([['0', '0.00'], ['100', '100.00'], ['12.5', '12.50'], [null, '0.00']] as $index => [$input, $expected]) {
            $seller = $this->createSeller($this->admin, [$branch->id], [
                'code' => 'COM-'.$index,
                'commission_percent' => $input,
            ]);

            $this->assertSame($expected, (string) $seller->commission_percent);
        }

        foreach (['-1', '100.01', 'abc', '12.345', 10.5] as $index => $invalid) {
            $this->actingAs($this->admin)
                ->post('/sellers', $this->sellerPayload([
                    'code' => 'BAD-COM-'.$index,
                    'commission_percent' => $invalid,
                    'branches' => [$branch->id],
                ]))
                ->assertSessionHasErrors('commission_percent');
        }

        $this->assertDatabaseMissing('sellers', ['code' => 'BAD-COM-0']);
    }

    public function test_campos_forjados_no_alteran_el_estado(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'FORGED-1',
                'branches' => [$branch->id],
                'id' => 999999,
                'is_active' => false,
                'anonymized_at' => '2000-01-01 00:00:00',
                'deleted_at' => '2000-01-02 00:00:00',
                'user' => ['id' => 999999],
            ]))
            ->assertRedirect();

        $seller = Seller::query()->where('code', 'FORGED-1')->firstOrFail();

        $this->assertNotSame(999999, $seller->id);
        $this->assertTrue($seller->is_active);
        $this->assertNull($seller->anonymized_at);
        $this->assertNull($seller->deleted_at);

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => $seller->name,
                'branches' => [$branch->id],
                'is_active' => false,
                'anonymized_at' => '2000-01-01 00:00:00',
                'deleted_at' => '2000-01-02 00:00:00',
            ])
            ->assertRedirect();

        $seller->refresh();
        $this->assertTrue($seller->is_active);
        $this->assertNull($seller->anonymized_at);
        $this->assertNull($seller->deleted_at);

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'FORGED-2',
                'user_id' => 999999,
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('user_id');
    }

    public function test_sucursales_obligatorias_existentes_activas_y_sin_duplicados(): void
    {
        $branch = $this->makeBranch();
        $inactive = $this->makeBranch(['is_active' => false]);
        $trashed = $this->makeBranch();
        $trashed->delete();

        $invalidSets = [
            [],
            [$inactive->id],
            [$trashed->id],
            [999999],
            [$branch->id, $branch->id],
        ];

        foreach ($invalidSets as $index => $branches) {
            $this->actingAs($this->admin)
                ->post('/sellers', $this->sellerPayload([
                    'code' => 'BR-'.$index,
                    'branches' => $branches,
                ]))
                ->assertSessionHasErrors('branches');
        }

        $this->assertSame(0, $this->sellerCount());
    }

    public function test_usuario_inactivo_o_eliminado_es_rechazado(): void
    {
        $branch = $this->makeBranch();
        $inactive = $this->makeUser(['is_active' => false]);
        $trashed = $this->makeUser();
        $trashed->delete();

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'USER-INACTIVE',
                'user_id' => $inactive->id,
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('user_id');

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'USER-TRASHED',
                'user_id' => $trashed->id,
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('user_id');
    }

    public function test_usuario_por_sucursal_debe_tener_acceso_a_todas_las_sucursales_asignadas(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $role = $this->makeRoleWithPermissions(['sellers.manage']);
        $user = $this->makeBranchUser($branchA, $role);

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'USER-A-ONLY',
                'user_id' => $user->id,
                'branches' => [$branchA->id, $branchB->id],
            ]))
            ->assertSessionHasErrors('user_id');

        $this->assignBranch($user, $branchB, $role);

        $seller = $this->createSeller($this->admin, [$branchA->id, $branchB->id], [
            'code' => 'USER-A-B',
            'user_id' => $user->id,
        ]);

        $this->assertSame($user->id, $seller->user_id);
    }

    public function test_activar_y_desactivar_conservan_codigo_y_no_borran(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'STATE-1']);

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/deactivate")
            ->assertRedirect();

        $seller->refresh();
        $this->assertFalse($seller->is_active);
        $this->assertNull($seller->deleted_at);
        $this->assertSame('STATE-1', $seller->code);

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/activate")
            ->assertRedirect();

        $this->assertTrue($seller->refresh()->is_active);
    }

    public function test_anonimizado_es_inmutable(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'ANON-1']);

        DB::table('sellers')->where('id', $seller->id)->update(['anonymized_at' => now()]);
        $originalName = (string) $seller->refresh()->name;

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => 'No permitido',
                'branches' => [$branch->id],
            ])
            ->assertSessionHasErrors('seller');

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/activate")
            ->assertSessionHasErrors('seller');

        $seller->refresh();
        $this->assertSame($originalName, $seller->name);
        $this->assertTrue($seller->is_active);
        $this->assertNotNull($seller->anonymized_at);
    }

    public function test_no_existe_borrado_fisico(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'NO-DELETE']);

        $this->actingAs($this->admin)
            ->delete("/sellers/{$seller->id}")
            ->assertStatus(405);

        $this->assertNull($seller->refresh()->deleted_at);
    }

    public function test_sesion_y_csrf_pertenecen_al_grupo_web(): void
    {
        $this->get('/sellers')->assertRedirect('/login');
        $this->post('/sellers', $this->sellerPayload())->assertRedirect('/login');

        $route = app('router')->getRoutes()->getByName('sellers.store');
        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
    }
}
