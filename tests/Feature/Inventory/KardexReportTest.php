<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Reporte Kardex de solo lectura por sucursal (INV-08, RN-21).
 */
class KardexReportTest extends InventoryTestCase
{
    /**
     * @param  array<string, mixed>  $query
     */
    private function kardexUrl(Branch $branch, array $query = []): string
    {
        $url = "/inventory/stock/{$branch->id}/kardex";

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    /**
     * @return list<int>
     */
    private function movementIds(TestResponse $response): array
    {
        return array_map(
            fn (array $row): int => (int) $row['id'],
            $this->pageProps($response)['movements']['data'],
        );
    }

    private function relativeUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }

    public function test_lectura_autorizada_muestra_solo_la_sucursal_con_saldo_registrado_y_reversa_visible(): void
    {
        $branch = $this->makeBranch();
        $other = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('KARDEX-AUT-001');
        [, $otherVariantId] = $this->makeProductVariant('KARDEX-AUT-002');

        $initial = $this->recordMovement($branch->id, $variantId, 'initial', '10.000', [
            'occurred_at' => '2026-11-10 09:00:00',
        ]);
        $adjustment = $this->recordMovement($branch->id, $variantId, 'adjustment', '-2.500', [
            'occurred_at' => '2026-11-11 09:00:00',
        ]);
        $reversal = $this->ledger()->reverseMovement(
            (int) $adjustment->id,
            (int) $this->admin->id,
            null,
            null,
            Carbon::parse('2026-11-12 09:00:00'),
        );
        $sale = $this->recordMovement($branch->id, $variantId, 'sale_pos', '-1.500', [
            'occurred_at' => '2026-11-13 09:00:00',
        ]);
        $this->recordMovement($other->id, $otherVariantId, 'initial', '5.000', [
            'occurred_at' => '2026-11-10 09:00:00',
        ]);

        $adjustmentId = (int) $adjustment->id;
        $response = $this->actingAs($this->admin)->get($this->kardexUrl($branch));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->where('branch.id', $branch->id)
            ->where('branch.code', $branch->code)
            ->where('filters.q', '')
            ->where('filters.variant_id', null)
            ->where('filters.from', null)
            ->where('filters.to', null)
            ->where('filters.per_page', 25)
            ->where('read_only', false)
            ->where('can_view_inventory', true)
            ->has('movements.data', 4)
            ->where('movements.data.0.id', (int) $sale->id)
            ->where('movements.data.0.type_label', 'Venta POS')
            ->where('movements.data.0.quantity', '-1.500')
            ->where('movements.data.0.balance_after', '8.500')
            ->where('movements.data.0.is_reversal', false)
            ->where('movements.data.0.reverses_movement_id', null)
            ->where('movements.data.1.id', (int) $reversal->id)
            ->where('movements.data.1.type_label', 'Corrección (reversa)')
            ->where('movements.data.1.quantity', '2.500')
            ->where('movements.data.1.balance_after', '10.000')
            ->where('movements.data.1.is_reversal', true)
            ->where('movements.data.1.reverses_movement_id', $adjustmentId)
            ->where('movements.data.2.id', $adjustmentId)
            ->where('movements.data.2.type_label', 'Ajuste')
            ->where('movements.data.3.id', (int) $initial->id)
            ->where('movements.data.3.type_label', 'Carga inicial')
            ->missing('movements.data.0.unit_cost')
            ->missing('movements.data.0.cost_price')
            ->missing('movements.data.0.price')
            ->missing('movements.data.0.margin')
            ->missing('movements.data.0.user_id')
            ->missing('movements.data.0.authorizer_id')
            ->missing('movements.data.0.pin')
            ->missing('movements.data.0.note')
            ->missing('movements.data.0.source_type')
            ->missing('movements.data.0.source_id')
            ->missing('movements.data.0.reason_code_id')
            ->missing('movements.data.0.reservation_id')
        );

        $props = $this->pageProps($response);

        foreach ($props['movements']['data'] as $row) {
            $this->assertSame('KARDEX-AUT-001', $row['sku']);
            $this->assertArrayNotHasKey('unit_cost', $row);
            $this->assertArrayNotHasKey('cost_price', $row);
            $this->assertArrayNotHasKey('price', $row);
            $this->assertArrayNotHasKey('margin', $row);
            $this->assertArrayNotHasKey('user_id', $row);
            $this->assertArrayNotHasKey('authorizer_id', $row);
            $this->assertArrayNotHasKey('pin', $row);
        }
    }

    public function test_orden_por_id_descendente_ignora_fechas_retroactivas_y_empates(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('KARDEX-ORDEN');

        $first = $this->recordMovement($branch->id, $variantId, 'initial', '10.000', [
            'occurred_at' => '2026-11-20 10:00:00',
        ]);
        $backdated = $this->recordMovement($branch->id, $variantId, 'adjustment', '-1.000', [
            'occurred_at' => '2026-11-01 10:00:00',
        ]);
        $tie = $this->recordMovement($branch->id, $variantId, 'waste', '-0.500', [
            'occurred_at' => '2026-11-20 10:00:00',
        ]);

        $response = $this->actingAs($this->admin)->get($this->kardexUrl($branch));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 3)
            ->where('movements.data.0.id', (int) $tie->id)
            ->where('movements.data.0.occurred_at', '2026-11-20 10:00:00')
            ->where('movements.data.0.balance_after', '8.500')
            ->where('movements.data.1.id', (int) $backdated->id)
            ->where('movements.data.1.occurred_at', '2026-11-01 10:00:00')
            ->where('movements.data.1.balance_after', '9.000')
            ->where('movements.data.2.id', (int) $first->id)
            ->where('movements.data.2.balance_after', '10.000')
        );
    }

    public function test_aislamiento_de_sucursal_y_rechazo_de_sucursales_no_autorizadas_o_inactivas(): void
    {
        $own = $this->makeBranch();
        $other = $this->makeBranch();
        $inactive = $this->makeBranch(['is_active' => false]);
        [, $ownVariant] = $this->makeProductVariant('KARDEX-OWN');
        [, $otherVariant] = $this->makeProductVariant('KARDEX-OTHER');

        $this->recordMovement($own->id, $ownVariant, 'initial', '2.000');
        $this->recordMovement($other->id, $otherVariant, 'initial', '7.000');

        $forgedVariant = $this->actingAs($this->admin)->get($this->kardexUrl($own, [
            'branch_id' => $other->id,
            'variant_id' => $otherVariant,
            'cost' => '1',
        ]));

        $forgedVariant->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 0)
            ->where('branch.id', $own->id)
        );

        $forgedBranch = $this->actingAs($this->admin)->get($this->kardexUrl($own, [
            'branch_id' => $other->id,
        ]));

        $forgedBranch->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.sku', 'KARDEX-OWN')
            ->where('branch.id', $own->id)
        );

        $role = $this->makeRoleWithPermissions(['inventory.view']);
        $user = $this->makeBranchUser($own, $role);

        $this->actingAs($user)->get($this->kardexUrl($own))->assertOk();
        $this->actingAs($user)->get($this->kardexUrl($other))->assertForbidden();

        $this->actingAs($this->admin)->get($this->kardexUrl($inactive))->assertForbidden();
        $this->actingAs($this->admin)->get('/inventory/stock/999999/kardex')->assertForbidden();
    }

    public function test_codigo_numerico_legal_resuelve_el_mismo_alcance_que_el_middleware(): void
    {
        // Sucursal A primero => ID 1. Sucursal B con codigo legal "1E0":
        // is_numeric("1E0") es verdadero, por lo que middleware y controlador
        // deben resolver el ID 1 (A) y nunca la sucursal cuyo codigo es "1E0".
        $own = $this->makeBranch();
        $this->assertSame(1, (int) $own->id, 'La primera sucursal del test debe tener ID 1.');

        $other = $this->makeBranch(['code' => '1E0']);
        [, $ownVariant] = $this->makeProductVariant('KARDEX-1E0-OWN');
        [, $otherVariant] = $this->makeProductVariant('KARDEX-1E0-OTHER');

        $ownMovement = $this->recordMovement($own->id, $ownVariant, 'initial', '2.000');
        $otherMovement = $this->recordMovement($other->id, $otherVariant, 'initial', '9.000');

        $role = $this->makeRoleWithPermissions(['inventory.view']);
        $user = $this->makeBranchUser($own, $role);

        $response = $this->actingAs($user)->get('/inventory/stock/1E0/kardex');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('branch.id', 1)
            ->where('branch.code', $own->code)
        );

        $props = $this->pageProps($response);
        $this->assertSame(1, $props['branch']['id']);
        $this->assertSame($own->code, $props['branch']['code']);

        $movementIds = $this->movementIds($response);
        $this->assertContains((int) $ownMovement->id, $movementIds);
        $this->assertNotContains((int) $otherMovement->id, $movementIds);

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('KARDEX-1E0-OTHER', $content);
        $this->assertStringNotContainsString($other->name, $content);
    }

    public function test_permiso_de_ajuste_o_ajeno_no_basta_y_modulo_deshabilitado_bloquea(): void
    {
        $branch = $this->makeBranch();

        $adjustRole = $this->makeRoleWithPermissions(['inventory.adjust']);
        $adjustUser = $this->makeBranchUser($branch, $adjustRole);
        $this->actingAs($adjustUser)->get($this->kardexUrl($branch))->assertForbidden();

        $otherRole = $this->makeRoleWithPermissions(['inventory.view']);
        $otherUser = $this->makeBranchUser($this->makeBranch(), $otherRole);
        $this->actingAs($otherUser)->get($this->kardexUrl($branch))->assertForbidden();

        $this->disableInventoryModule();
        $this->actingAs($this->admin)->get($this->kardexUrl($branch))->assertForbidden();
    }

    public function test_licencia_suspendida_fuera_de_gracia_no_bloquea_la_lectura(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('KARDEX-LICENCIA');
        $this->recordMovement($branch->id, $variantId, 'initial', '1.000');
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('read_only', true)
                ->has('movements.data', 1)
            );
    }

    public function test_filtros_por_sku_producto_variante_y_rango_de_fechas_inclusivo(): void
    {
        $branch = $this->makeBranch();
        [$productA, $variantA] = $this->makeProductVariant('KARDEX-FILTRO-ARROZ');
        [$productB, $variantB] = $this->makeProductVariant('KARDEX-FILTRO-FIDEO');
        DB::table('products')->where('id', $productA)->update(['name' => 'Arroz integral']);
        DB::table('products')->where('id', $productB)->update(['name' => 'Fideos largos']);

        $this->recordMovement($branch->id, $variantA, 'initial', '10.000', [
            'occurred_at' => '2026-11-19 00:00:00',
        ]);
        $this->recordMovement($branch->id, $variantA, 'adjustment', '-1.000', [
            'occurred_at' => '2026-11-19 23:59:59',
        ]);
        $this->recordMovement($branch->id, $variantB, 'initial', '4.000', [
            'occurred_at' => '2026-11-20 00:00:00',
        ]);

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['q' => 'FILTRO-ARROZ']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 2));

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['q' => 'Arroz']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 2));

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['variant_id' => $variantA]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 2)
                ->where('filters.variant_id', $variantA)
            );

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['variant_id' => $variantB]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 1));

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['from' => '2026-11-19', 'to' => '2026-11-19']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 2)
                ->where('filters.from', '2026-11-19')
                ->where('filters.to', '2026-11-19')
            );

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['from' => '2026-11-20']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.occurred_at', '2026-11-20 00:00:00')
            );

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch, ['to' => '2026-11-18']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 0));
    }

    public function test_parametros_invalidos_responden_422_antes_de_consultar(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('KARDEX-VALIDACION');
        $this->recordMovement($branch->id, $variantId, 'initial', '1.000');

        $cases = [
            ['from' => '2026-11-20', 'to' => '2026-11-19'],
            ['from' => '19/11/2026'],
            ['to' => '2026-11-19T00:00:00'],
            ['per_page' => '0'],
            ['per_page' => '101'],
            ['variant_id' => '0'],
            ['cursor' => 'not-a-valid-cursor'],
            ['q' => str_repeat('a', 101)],
        ];

        foreach ($cases as $query) {
            $this->actingAs($this->admin)
                ->getJson($this->kardexUrl($branch, $query))
                ->assertStatus(422);
        }
    }

    public function test_paginacion_por_cursor_sin_duplicados_ni_omisiones_y_conserva_filtros(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('KARDEX-PAGE');

        $created = [];

        for ($i = 0; $i < 5; $i++) {
            $created[] = (int) $this->recordMovement($branch->id, $variantId, 'adjustment', '1.000')->id;
        }

        $expected = array_reverse($created);

        $first = $this->actingAs($this->admin)->get($this->kardexUrl($branch, [
            'q' => 'KARDEX-PAGE',
            'per_page' => 2,
        ]));
        $first->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 2)
            ->where('movements.data.0.id', $expected[0])
            ->where('movements.data.1.id', $expected[1])
            ->where('filters.q', 'KARDEX-PAGE')
            ->where('filters.per_page', 2)
        );

        $firstProps = $this->pageProps($first);
        $nextUrl = $firstProps['movements']['next_page_url'];
        $this->assertIsString($nextUrl);

        parse_str(parse_url($nextUrl, PHP_URL_QUERY) ?? '', $nextQuery);
        $this->assertSame('KARDEX-PAGE', $nextQuery['q'] ?? null);
        $this->assertSame('2', (string) ($nextQuery['per_page'] ?? ''));
        $this->assertArrayHasKey('cursor', $nextQuery);

        $second = $this->actingAs($this->admin)->get($this->relativeUrl($nextUrl));
        $second->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 2)
            ->where('movements.data.0.id', $expected[2])
            ->where('movements.data.1.id', $expected[3])
            ->where('filters.q', 'KARDEX-PAGE')
            ->where('filters.per_page', 2)
        );

        $secondProps = $this->pageProps($second);
        $this->assertIsString($secondProps['movements']['previous_page_url']);
        $this->assertIsString($secondProps['movements']['next_page_url']);

        $third = $this->actingAs($this->admin)->get($this->relativeUrl($secondProps['movements']['next_page_url']));
        $third->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $expected[4])
        );

        $thirdProps = $this->pageProps($third);
        $this->assertNull($thirdProps['movements']['next_page_url']);

        $seen = array_merge(
            $this->movementIds($first),
            $this->movementIds($second),
            $this->movementIds($third),
        );

        $this->assertSame($expected, $seen);
        $this->assertSame(count($seen), count(array_unique($seen)));
    }

    public function test_variante_y_producto_inactivos_o_eliminados_siguen_visibles(): void
    {
        $branch = $this->makeBranch();
        [$productId, $variantId] = $this->makeProductVariant('KARDEX-HISTORICO');
        $movement = $this->recordMovement($branch->id, $variantId, 'initial', '3.000');

        DB::table('product_variants')->where('id', $variantId)->update([
            'is_active' => 0,
            'deleted_at' => now(),
        ]);
        DB::table('products')->where('id', $productId)->update([
            'is_active' => 0,
            'deleted_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.id', (int) $movement->id)
                ->where('movements.data.0.sku', 'KARDEX-HISTORICO')
                ->where('movements.data.0.quantity', '3.000')
            );
    }

    public function test_consulta_get_no_escribe_ni_crea_auditoria_y_solo_existe_la_ruta_get(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('KARDEX-SIN-EFECTOS');
        $this->recordMovement($branch->id, $variantId, 'initial', '2.000');

        $movementCount = DB::table('stock_movements')->count();
        $stockCount = DB::table('current_stock')->count();
        $auditCount = DB::table('audit_logs')->count();

        $this->actingAs($this->admin)->get($this->kardexUrl($branch))->assertOk();

        $this->assertSame($movementCount, DB::table('stock_movements')->count());
        $this->assertSame($stockCount, DB::table('current_stock')->count());
        $this->assertSame($auditCount, DB::table('audit_logs')->count());

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->getName() === 'inventory.kardex.index');

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertNotContains($method, $route->methods());
        }

        $kardexRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'kardex'));

        $this->assertCount(1, $kardexRoutes);
    }

    public function test_sucursal_sin_movimientos_devuelve_estado_vacio(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)
            ->get($this->kardexUrl($branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 0)
                ->where('movements.next_page_url', null)
                ->where('movements.previous_page_url', null)
            );
    }
}
