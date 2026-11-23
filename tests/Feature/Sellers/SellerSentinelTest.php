<?php

namespace Tests\Feature\Sellers;

use App\Models\Seller;
use App\Support\Sellers\SellerService;
use Database\Seeders\SellerSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Fila reservada `NONE` ("Sin asignar"): reproducción, inmutabilidad y ocultamiento.
 */
class SellerSentinelTest extends SellerTestCase
{
    public function test_seeder_crea_el_vendedor_reservado_sin_sucursales(): void
    {
        $this->assertSame(1, Seller::query()->where('code', 'NONE')->count());

        $this->assertDatabaseHas('sellers', [
            'code' => 'NONE',
            'name' => 'Sin asignar',
            'user_id' => null,
            'commission_percent' => 0,
            'is_active' => 1,
            'anonymized_at' => null,
            'deleted_at' => null,
        ]);

        $this->assertSame(0, DB::table('branch_seller')->where('seller_id', $this->sentinel->id)->count());
    }

    public function test_seeder_es_idempotente_y_conserva_created_at(): void
    {
        $createdAt = DB::table('sellers')->where('code', 'NONE')->value('created_at');

        $this->seed(SellerSeeder::class);
        $this->seed(SellerSeeder::class);

        $this->assertSame(1, Seller::query()->where('code', 'NONE')->count());
        $this->assertSame(
            $createdAt,
            DB::table('sellers')->where('code', 'NONE')->value('created_at'),
        );
        $this->assertSame(0, DB::table('branch_seller')->where('seller_id', $this->sentinel->id)->count());
    }

    public function test_sentinel_esta_oculto_del_listado_y_detalle(): void
    {
        $this->actingAs($this->admin)
            ->get('/sellers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sellers/Index')
                ->where('sellers.total', 0)
                ->has('sellers.data', 0));

        $this->actingAs($this->admin)
            ->get("/sellers/{$this->sentinel->id}")
            ->assertNotFound();
    }

    public function test_no_se_puede_crear_un_vendedor_con_codigo_none(): void
    {
        $branch = $this->makeBranch();

        foreach (['NONE', 'none', ' None '] as $code) {
            $this->actingAs($this->admin)
                ->post('/sellers', $this->sellerPayload([
                    'code' => $code,
                    'branches' => [$branch->id],
                ]))
                ->assertSessionHasErrors('code');
        }

        $this->assertSame(1, Seller::query()->where('code', 'NONE')->count());
        $this->assertSame(0, $this->sellerCount());
    }

    public function test_servicio_rechaza_actualizar_o_cambiar_estado_del_sentinel(): void
    {
        $service = app(SellerService::class);

        foreach ([
            fn () => $service->update($this->admin, $this->sentinel, ['name' => 'Cambio']),
            fn () => $service->changeStatus($this->admin, $this->sentinel, false),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Se esperaba rechazo del vendedor reservado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('seller', $exception->errors());
            }
        }

        $this->sentinel->refresh();
        $this->assertSame('Sin asignar', $this->sentinel->name);
        $this->assertTrue($this->sentinel->is_active);
    }

    public function test_http_no_expone_el_sentinel_para_edicion_o_estado(): void
    {
        $this->actingAs($this->admin)
            ->patch("/sellers/{$this->sentinel->id}", ['name' => 'Cambio'])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->post("/sellers/{$this->sentinel->id}/deactivate")
            ->assertNotFound();

        $this->sentinel->refresh();
        $this->assertSame('Sin asignar', $this->sentinel->name);
        $this->assertTrue($this->sentinel->is_active);
    }
}
