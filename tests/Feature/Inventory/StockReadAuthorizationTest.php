<?php

namespace Tests\Feature\Inventory;

use App\Models\BranchUser;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Lectura de inventario y límites de autorización (INV-03).
 */
class StockReadAuthorizationTest extends InventoryTestCase
{
    public function test_entrada_redirige_a_la_sucursal_por_defecto_del_usuario_global(): void
    {
        $first = $this->makeBranch();
        $default = $this->makeBranch();
        $this->admin->update(['default_branch_id' => $default->id]);

        $this->actingAs($this->admin->refresh())
            ->get('/inventory')
            ->assertRedirect(route('inventory.stock.index', ['branch' => $default->id]));
    }

    public function test_lectura_autorizada_devuelve_existencia_reservado_y_disponible(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('READ-001');
        $this->createStockRow($branch->id, $variantId, '10.500', '2.250', '2026-11-18 10:00:00');

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Stock')
                ->where('branch.id', $branch->id)
                ->where('branch.code', $branch->code)
                ->where('stock.data.0.sku', 'READ-001')
                ->where('stock.data.0.on_hand', '10.500')
                ->where('stock.data.0.reserved', '2.250')
                ->where('stock.data.0.available', '8.250')
                ->where('filters.q', '')
                ->where('can_view_inventory', true)
                ->where('read_only', false));
    }

    public function test_busqueda_por_sku_o_nombre_es_acotada_a_la_sucursal(): void
    {
        $branch = $this->makeBranch();
        [, $firstVariant] = $this->makeProductVariant('BUSCAR-ARROZ');
        [, $secondVariant] = $this->makeProductVariant('BUSCAR-FIDEO');
        $this->createStockRow($branch->id, $firstVariant, '1.000');
        $this->createStockRow($branch->id, $secondVariant, '2.000');

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}?q=ARROZ")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('stock.data', 1)
                ->where('stock.data.0.sku', 'BUSCAR-ARROZ')
                ->where('filters.q', 'ARROZ'));
    }

    public function test_paginacion_bounded_y_variantes_sin_fila_salen_en_cero(): void
    {
        $branch = $this->makeBranch();

        for ($i = 0; $i < 3; $i++) {
            $this->makeProductVariant('PAGE-'.$i);
        }

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}?per_page=1000")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('stock.data', 3)
                ->where('stock.last_page', 1)
                ->where('stock.data.0.on_hand', '0.000')
                ->where('stock.data.0.available', '0.000'));
    }

    public function test_usuario_de_sucursal_sin_permiso_recibe_403(): void
    {
        $branch = $this->makeBranch();
        $role = $this->makeRoleWithPermissions(['catalog.view']);
        $user = $this->makeBranchUser($branch, $role);

        $this->actingAs($user)->get("/inventory/stock/{$branch->id}")->assertForbidden();
        $this->actingAs($user)->get('/inventory')->assertForbidden();
    }

    public function test_usuario_con_permiso_no_puede_leer_otra_sucursal(): void
    {
        $own = $this->makeBranch();
        $other = $this->makeBranch();
        $role = $this->makeRoleWithPermissions(['inventory.view']);
        $user = $this->makeBranchUser($own, $role);

        $this->actingAs($user)->get("/inventory/stock/{$own->id}")->assertOk();
        $this->actingAs($user)->get("/inventory/stock/{$other->id}")->assertForbidden();
    }

    public function test_codigo_de_sucursal_se_resuelve_en_servidor(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-INV-CODIGO']);

        $this->actingAs($this->admin)
            ->get('/inventory/stock/SUC-INV-CODIGO')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branch.id', $branch->id));
    }

    public function test_modulo_deshabilitado_bloquea_lectura(): void
    {
        $branch = $this->makeBranch();
        $this->disableInventoryModule();

        $this->actingAs($this->admin)->get('/inventory')->assertForbidden();
        $this->actingAs($this->admin)->get("/inventory/stock/{$branch->id}")->assertForbidden();
    }

    public function test_modulo_habilitado_por_pos_o_bodega_habilita_lectura(): void
    {
        $branch = $this->makeBranch();
        $this->disableInventoryModule();
        DB::table('installation_modules')
            ->where('module_code', 'inventory')
            ->delete();
        DB::table('installation_modules')
            ->where('module_code', 'bodega')
            ->update(['is_enabled' => 1]);

        $this->actingAs($this->admin)->get("/inventory/stock/{$branch->id}")->assertOk();
    }

    public function test_licencia_suspendida_fuera_de_gracia_no_bloquea_la_lectura(): void
    {
        $branch = $this->makeBranch();
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));
    }

    public function test_sin_sucursal_accesible_la_entrada_responde_403(): void
    {
        $role = $this->makeRoleWithPermissions(['inventory.view']);
        $user = $this->makeUser([
            'default_branch_id' => null,
            'global_role_id' => null,
        ]);
        BranchUser::create([
            'user_id' => $user->id,
            'branch_id' => $this->makeBranch(['is_active' => false])->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)->get('/inventory')->assertForbidden();
    }

    public function test_sucursal_inactiva_no_es_accesible_aunque_el_usuario_sea_global(): void
    {
        $branch = $this->makeBranch(['is_active' => false]);

        $this->actingAs($this->admin)->get("/inventory/stock/{$branch->id}")->assertForbidden();
    }

    public function test_dashboard_expone_can_view_inventory_solo_con_permiso(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_inventory', true));

        $branch = $this->makeBranch();
        $role = $this->makeRoleWithPermissions(['catalog.view']);
        $user = $this->makeBranchUser($branch, $role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_inventory', false));
    }

    public function test_solo_existen_las_rutas_de_lectura_y_escritura_manual_aprobadas(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->getName() ?? '')
            ->filter(fn (string $name): bool => str_starts_with($name, 'inventory.'))
            ->values()
            ->all();

        $this->assertSame([
            'inventory.entry',
            'inventory.stock.index',
            'inventory.import.index',
            'inventory.import.template',
            'inventory.import.preview',
            'inventory.import.commit',
            'inventory.kardex.index',
            'inventory.reorder.index',
            'inventory.stock.initial',
            'inventory.stock.adjust',
            'inventory.reorder.update',
        ], $routes);
    }
}
