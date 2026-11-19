<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Panel de reposición y stock crítico por sucursal (INVREORDER-001 · INV-07).
 */
class ReorderDashboardTest extends InventoryTestCase
{
    /**
     * @param  array<string, mixed>  $query
     */
    private function reorderUrl(Branch $branch, array $query = []): string
    {
        $url = "/inventory/stock/{$branch->id}/reorder";

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
     * @return array<string, array<string, mixed>>
     */
    private function rowsBySku(TestResponse $response): array
    {
        $rows = $this->pageProps($response)['rows']['data'];
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['sku']] = $row;
        }

        return $result;
    }

    private function relativeUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }

    public function test_panel_muestra_stock_y_configuracion_sin_campos_sensibles(): void
    {
        $branch = $this->makeBranch();
        [$productA, $variantA] = $this->makeProductVariant('REORDER-PANEL-A');
        [$productB, $variantB] = $this->makeProductVariant('REORDER-PANEL-B');
        DB::table('products')->where('id', $productA)->update(['name' => 'A Producto']);
        DB::table('products')->where('id', $productB)->update(['name' => 'B Producto']);

        $this->createStockRow($branch->id, $variantA, '10.000', '2.000');
        $this->createReorderSettings($branch->id, $variantA, '5.000', '3.000', '50.000');

        $response = $this->actingAs($this->admin)->get($this->reorderUrl($branch));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Reorder')
            ->where('branch.id', $branch->id)
            ->where('branch.code', $branch->code)
            ->where('filters.q', '')
            ->where('filters.critical', false)
            ->where('filters.per_page', 25)
            ->where('critical_count', 0)
            ->where('can_adjust', true)
            ->where('can_view_inventory', true)
            ->where('read_only', false)
            ->has('rows.data', 2)
        );

        $rows = $this->rowsBySku($response);

        $this->assertSame('10.000', $rows['REORDER-PANEL-A']['on_hand']);
        $this->assertSame('2.000', $rows['REORDER-PANEL-A']['reserved']);
        $this->assertSame('8.000', $rows['REORDER-PANEL-A']['available']);
        $this->assertSame('5.000', $rows['REORDER-PANEL-A']['reorder_point']);
        $this->assertSame('3.000', $rows['REORDER-PANEL-A']['reorder_qty']);
        $this->assertSame('50.000', $rows['REORDER-PANEL-A']['max_stock']);
        $this->assertTrue($rows['REORDER-PANEL-A']['has_settings']);
        $this->assertTrue($rows['REORDER-PANEL-A']['can_edit']);
        $this->assertFalse($rows['REORDER-PANEL-A']['is_critical']);

        $this->assertSame('0.000', $rows['REORDER-PANEL-B']['on_hand']);
        $this->assertSame('0.000', $rows['REORDER-PANEL-B']['reserved']);
        $this->assertSame('0.000', $rows['REORDER-PANEL-B']['available']);
        $this->assertSame('0.000', $rows['REORDER-PANEL-B']['reorder_point']);
        $this->assertNull($rows['REORDER-PANEL-B']['reorder_qty']);
        $this->assertNull($rows['REORDER-PANEL-B']['max_stock']);
        $this->assertFalse($rows['REORDER-PANEL-B']['has_settings']);
        $this->assertFalse($rows['REORDER-PANEL-B']['is_critical']);

        $forbidden = [
            'unit_cost', 'cost', 'cost_price', 'price', 'margin', 'safety_stock',
            'location_label', 'location', 'user_id', 'email', 'authorizer_id', 'pin',
        ];

        foreach ($rows as $row) {
            foreach ($forbidden as $key) {
                $this->assertArrayNotHasKey($key, $row);
            }
        }

        $content = (string) $response->getContent();

        foreach (['unit_cost', 'cost_price', 'safety_stock', 'location_label'] as $key) {
            $this->assertStringNotContainsString($key, $content);
        }
    }

    public function test_regla_critica_frontera_punto_cero_negativo_y_sin_fila(): void
    {
        $branch = $this->makeBranch();

        [, $exact] = $this->makeProductVariant('CRIT-EXACT');
        $this->createStockRow($branch->id, $exact, '5.000', '0.000');
        $this->createReorderSettings($branch->id, $exact, '5.000');

        [, $below] = $this->makeProductVariant('CRIT-BELOW');
        $this->createStockRow($branch->id, $below, '4.000');
        $this->createReorderSettings($branch->id, $below, '5.000');

        [, $above] = $this->makeProductVariant('CRIT-ABOVE');
        $this->createStockRow($branch->id, $above, '6.000');
        $this->createReorderSettings($branch->id, $above, '5.000');

        [, $zeroPoint] = $this->makeProductVariant('CRIT-ZERO-POINT');
        $this->createStockRow($branch->id, $zeroPoint, '0.000');
        $this->createReorderSettings($branch->id, $zeroPoint, '0.000');

        [, $noSettings] = $this->makeProductVariant('CRIT-NO-SETTINGS');
        $this->createStockRow($branch->id, $noSettings, '0.000');

        [, $negative] = $this->makeProductVariant('CRIT-NEGATIVE');
        $this->createStockRow($branch->id, $negative, '1.000', '3.000');
        $this->createReorderSettings($branch->id, $negative, '2.000');

        [, $missingStock] = $this->makeProductVariant('CRIT-MISSING-STOCK');
        $this->createReorderSettings($branch->id, $missingStock, '3.000');

        $response = $this->actingAs($this->admin)->get($this->reorderUrl($branch));
        $rows = $this->rowsBySku($response);

        $this->assertTrue($rows['CRIT-EXACT']['is_critical']);
        $this->assertTrue($rows['CRIT-BELOW']['is_critical']);
        $this->assertFalse($rows['CRIT-ABOVE']['is_critical']);
        $this->assertFalse($rows['CRIT-ZERO-POINT']['is_critical']);
        $this->assertFalse($rows['CRIT-NO-SETTINGS']['is_critical']);
        $this->assertTrue($rows['CRIT-NEGATIVE']['is_critical']);
        $this->assertSame('-2.000', $rows['CRIT-NEGATIVE']['available']);
        $this->assertTrue($rows['CRIT-MISSING-STOCK']['is_critical']);
        $this->assertSame('0.000', $rows['CRIT-MISSING-STOCK']['available']);
        $this->assertSame(4, $this->pageProps($response)['critical_count']);
    }

    public function test_reservado_reduce_disponible_y_puede_volver_critico(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('CRIT-RESERVED');

        $this->createStockRow($branch->id, $variantId, '10.000', '8.000');
        $this->createReorderSettings($branch->id, $variantId, '3.000');

        $response = $this->actingAs($this->admin)->get($this->reorderUrl($branch));
        $row = $this->rowsBySku($response)['CRIT-RESERVED'];

        $this->assertSame('10.000', $row['on_hand']);
        $this->assertSame('8.000', $row['reserved']);
        $this->assertSame('2.000', $row['available']);
        $this->assertTrue($row['is_critical']);
    }

    public function test_filtro_critico_y_conteo_no_dependen_de_la_busqueda(): void
    {
        $branch = $this->makeBranch();

        [, $critical] = $this->makeProductVariant('FILTER-CRITICAL');
        $this->createStockRow($branch->id, $critical, '1.000');
        $this->createReorderSettings($branch->id, $critical, '2.000');

        [, $safe] = $this->makeProductVariant('FILTER-SAFE');
        $this->createStockRow($branch->id, $safe, '10.000');
        $this->createReorderSettings($branch->id, $safe, '2.000');

        [, $unconfigured] = $this->makeProductVariant('FILTER-UNCONFIGURED');
        $this->createStockRow($branch->id, $unconfigured, '0.000');

        $filtered = $this->actingAs($this->admin)
            ->get($this->reorderUrl($branch, ['critical' => '1']));

        $filtered->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.critical', true)
            ->where('critical_count', 1)
            ->has('rows.data', 1)
            ->where('rows.data.0.sku', 'FILTER-CRITICAL')
        );

        $searched = $this->actingAs($this->admin)
            ->get($this->reorderUrl($branch, ['q' => 'FILTER-SAFE']));

        $searched->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'FILTER-SAFE')
            ->where('critical_count', 1)
            ->has('rows.data', 1)
            ->where('rows.data.0.sku', 'FILTER-SAFE')
        );

        $all = $this->actingAs($this->admin)->get($this->reorderUrl($branch));
        $all->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 3));

        $zero = $this->actingAs($this->admin)
            ->get($this->reorderUrl($branch, ['critical' => '0']));
        $zero->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 3));
    }

    public function test_busqueda_y_paginacion_acotada_conserva_filtros(): void
    {
        $branch = $this->makeBranch();

        for ($i = 1; $i <= 3; $i++) {
            [$productId] = $this->makeProductVariant('PAGE-REORDER-'.$i);
            DB::table('products')->where('id', $productId)->update(['name' => 'Producto paginado '.$i]);
        }

        $first = $this->actingAs($this->admin)->get($this->reorderUrl($branch, [
            'q' => 'Producto paginado',
            'per_page' => 2,
        ]));

        $first->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)
            ->where('filters.q', 'Producto paginado')
            ->where('filters.per_page', 2)
            ->where('rows.total', 3)
            ->where('rows.current_page', 1)
            ->where('rows.last_page', 2)
        );

        $props = $this->pageProps($first);
        $this->assertIsString($props['rows']['next_page_url']);

        parse_str(parse_url($props['rows']['next_page_url'], PHP_URL_QUERY) ?? '', $query);
        $this->assertSame('Producto paginado', $query['q'] ?? null);
        $this->assertSame('2', (string) ($query['per_page'] ?? ''));

        $second = $this->actingAs($this->admin)
            ->get($this->relativeUrl($props['rows']['next_page_url']));

        $second->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
            ->where('filters.q', 'Producto paginado')
            ->where('filters.per_page', 2)
            ->where('rows.current_page', 2)
        );

        foreach ([
            ['per_page' => '0'],
            ['per_page' => '101'],
            ['per_page' => 'abc'],
            ['q' => str_repeat('a', 101)],
            ['critical' => 'no-es-booleano'],
        ] as $invalid) {
            $this->actingAs($this->admin)
                ->getJson($this->reorderUrl($branch, $invalid))
                ->assertStatus(422);
        }
    }

    public function test_fila_historica_inactiva_visible_con_can_edit_false(): void
    {
        $branch = $this->makeBranch();
        [$productId, $variantId] = $this->makeProductVariant('REORDER-HISTORIC');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $this->createReorderSettings($branch->id, $variantId, '2.000', '1.000', '10.000');

        [, $inactiveWithoutSettings] = $this->makeProductVariant('REORDER-INACTIVE-NO-SETTINGS');
        DB::table('product_variants')
            ->where('id', $inactiveWithoutSettings)
            ->update(['is_active' => 0]);

        DB::table('product_variants')->where('id', $variantId)->update([
            'is_active' => 0,
            'deleted_at' => now(),
        ]);
        DB::table('products')->where('id', $productId)->update([
            'is_active' => 0,
            'deleted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get($this->reorderUrl($branch));
        $rows = $this->rowsBySku($response);

        $this->assertArrayHasKey('REORDER-HISTORIC', $rows);
        $this->assertFalse($rows['REORDER-HISTORIC']['can_edit']);
        $this->assertTrue($rows['REORDER-HISTORIC']['has_settings']);
        $this->assertSame('2.000', $rows['REORDER-HISTORIC']['reorder_point']);
        $this->assertTrue($rows['REORDER-HISTORIC']['is_critical']);

        $this->assertArrayNotHasKey('REORDER-INACTIVE-NO-SETTINGS', $rows);
    }

    public function test_licencia_solo_lectura_muestra_panel_200(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-LICENSE-READ');
        $this->createStockRow($branch->id, $variantId, '2.000');
        $this->createReorderSettings($branch->id, $variantId, '3.000');
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get($this->reorderUrl($branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('read_only', true)
                ->has('rows.data', 1)
                ->where('rows.data.0.is_critical', true)
            );
    }

    public function test_permisos_modulo_y_sucursal_fail_closed(): void
    {
        $own = $this->makeBranch();
        $other = $this->makeBranch();
        $inactive = $this->makeBranch(['is_active' => false]);

        $viewRole = $this->makeRoleWithPermissions(['inventory.view']);
        $viewUser = $this->makeBranchUser($own, $viewRole);

        $this->actingAs($viewUser)
            ->get($this->reorderUrl($own))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_adjust', false)
                ->where('can_view_inventory', true)
            );

        $this->actingAs($viewUser)->get($this->reorderUrl($other))->assertForbidden();

        $adjustRole = $this->makeRoleWithPermissions(['inventory.adjust']);
        $adjustUser = $this->makeBranchUser($own, $adjustRole);
        $this->actingAs($adjustUser)->get($this->reorderUrl($own))->assertForbidden();

        $noPermissionRole = $this->makeRoleWithPermissions(['catalog.view']);
        $noPermissionUser = $this->makeBranchUser($own, $noPermissionRole);
        $this->actingAs($noPermissionUser)->get($this->reorderUrl($own))->assertForbidden();

        $this->actingAs($this->admin)->get($this->reorderUrl($inactive))->assertForbidden();
        $this->actingAs($this->admin)->get('/inventory/stock/999999/reorder')->assertForbidden();

        $this->disableInventoryModule();
        $this->actingAs($this->admin)->get($this->reorderUrl($own))->assertForbidden();
    }

    public function test_aislamiento_de_sucursal_en_stock_y_settings(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-ISOLATION');

        $this->createStockRow($branchA->id, $variantId, '1.000', '0.000');
        $this->createStockRow($branchB->id, $variantId, '9.000', '0.000');
        $this->createReorderSettings($branchA->id, $variantId, '2.000');
        $this->createReorderSettings($branchB->id, $variantId, '8.000');

        $rowsA = $this->rowsBySku(
            $this->actingAs($this->admin)->get($this->reorderUrl($branchA)),
        );
        $rowsB = $this->rowsBySku(
            $this->actingAs($this->admin)->get($this->reorderUrl($branchB)),
        );

        $this->assertSame('1.000', $rowsA['REORDER-ISOLATION']['on_hand']);
        $this->assertSame('2.000', $rowsA['REORDER-ISOLATION']['reorder_point']);
        $this->assertTrue($rowsA['REORDER-ISOLATION']['is_critical']);

        $this->assertSame('9.000', $rowsB['REORDER-ISOLATION']['on_hand']);
        $this->assertSame('8.000', $rowsB['REORDER-ISOLATION']['reorder_point']);
        $this->assertFalse($rowsB['REORDER-ISOLATION']['is_critical']);
    }

    public function test_get_no_escribe_y_solo_existen_las_rutas_aprobadas(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-GET-NO-WRITE');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $this->createReorderSettings($branch->id, $variantId, '2.000');

        $counts = [
            'settings' => DB::table('branch_variant_settings')->count(),
            'stock' => DB::table('current_stock')->count(),
            'movements' => DB::table('stock_movements')->count(),
            'audit' => DB::table('audit_logs')->count(),
        ];

        $this->actingAs($this->admin)->get($this->reorderUrl($branch))->assertOk();

        $this->assertSame($counts['settings'], DB::table('branch_variant_settings')->count());
        $this->assertSame($counts['stock'], DB::table('current_stock')->count());
        $this->assertSame($counts['movements'], DB::table('stock_movements')->count());
        $this->assertSame($counts['audit'], DB::table('audit_logs')->count());

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), '/reorder'));

        $this->assertCount(2, $routes);

        $index = $routes->first(fn ($route): bool => $route->getName() === 'inventory.reorder.index');
        $update = $routes->first(fn ($route): bool => $route->getName() === 'inventory.reorder.update');

        $this->assertNotNull($index);
        $this->assertContains('GET', $index->methods());

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertNotContains($method, $index->methods());
        }

        $this->assertNotNull($update);
        $this->assertContains('PUT', $update->methods());

        foreach (['GET', 'POST', 'PATCH', 'DELETE'] as $method) {
            $this->assertNotContains($method, $update->methods());
        }
    }

    public function test_sucursal_sin_datos_devuelve_estado_vacio(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)
            ->get($this->reorderUrl($branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 0)
                ->where('rows.total', 0)
                ->where('critical_count', 0)
                ->where('rows.previous_page_url', null)
                ->where('rows.next_page_url', null)
            );
    }
}
