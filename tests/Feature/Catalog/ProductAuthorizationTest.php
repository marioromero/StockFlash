<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

class ProductAuthorizationTest extends ProductTestCase
{
    public function test_get_listado_con_catalog_view_responde_200(): void
    {
        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');

        $this->actingAs($viewer)
            ->get('/catalog/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Products')
                ->where('can_manage', false)
                ->has('products.data')
                ->has('catalogs.categories')
                ->has('catalogs.brands')
                ->has('catalogs.tax_rates')
                ->has('catalogs.tags')
                ->has('catalogs.attributes')
            );
    }

    public function test_get_sin_catalog_view_recibe_403(): void
    {
        $branch = $this->makeBranch();
        $noPermission = $this->makeBranchUser($branch, $this->makeNoPermissionRole());

        $this->actingAs($noPermission)->get('/catalog/products')->assertForbidden();
    }

    public function test_mutaciones_sin_catalog_manage_reciben_403_sin_escribir(): void
    {
        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');

        $this->actingAs($viewer)
            ->post('/catalog/products', $this->productPayload(['name' => 'No autorizado']))
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'No autorizado']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'catalog.products.created']);
    }

    public function test_actor_sin_view_cost_recibe_detalle_sin_costos_ni_derivados(): void
    {
        $product = $this->createSimpleProduct();
        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');

        $this->actingAs($viewer)
            ->get("/catalog/products/{$product->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Products')
                ->where('can_view_cost', false)
                ->has('selected.variants', 1)
                ->missing('selected.variants.0.cost_price')
                ->missing('selected.variants.0.last_cost')
                ->missing('selected.variants.0.margin')
                ->missing('selected.variants.0.markup_percent')
            );
    }

    public function test_actor_con_view_cost_recibe_costos_y_derivados(): void
    {
        $product = $this->createSimpleProduct();
        $branch = $this->makeBranch();
        $keeper = $this->makeBranchUser($branch, 'warehouse_keeper');

        $this->actingAs($keeper)
            ->get("/catalog/products/{$product->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_view_cost', true)
                ->where('selected.variants.0.cost_price', '500.0000')
                ->where('selected.variants.0.last_cost', null)
                ->where('selected.variants.0.margin', '500.00')
                ->where('selected.variants.0.markup_percent', '100.00')
            );
    }

    public function test_branch_id_en_query_o_body_no_eleva_el_ambito(): void
    {
        $branchA = $this->makeBranch(['code' => 'PROD-A']);
        $branchB = $this->makeBranch(['code' => 'PROD-B']);
        $noPermission = $this->makeNoPermissionRole();

        $user = $this->makeUser([
            'email' => 'prod-manipulador@prueba.cl',
            'default_branch_id' => $branchA->id,
            'global_role_id' => null,
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $user->id, 'branch_id' => $branchA->id, 'role_id' => $noPermission->id],
            ['user_id' => $user->id, 'branch_id' => $branchB->id, 'role_id' => $this->role('supervisor')->id],
        ]);

        $this->actingAs($user)
            ->get('/catalog/products?branch_id='.$branchB->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/catalog/products', $this->productPayload([
                'name' => 'No autorizado por sucursal',
                'branch_id' => $branchB->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'No autorizado por sucursal']);
    }

    public function test_modulo_habilitado_no_otorga_permiso(): void
    {
        $branch = $this->makeBranch();
        $noPermission = $this->makeBranchUser($branch, $this->makeNoPermissionRole());

        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'catalog'],
            [
                'is_enabled' => 1,
                'enabled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($noPermission)->get('/catalog/products')->assertForbidden();
    }

    public function test_rutas_exigen_permisos_y_middlewares_protegidos_sin_branch_access(): void
    {
        $expectations = [
            'catalog.products.index' => 'can.permission:catalog.view',
            'catalog.products.show' => 'can.permission:catalog.view',
            'catalog.products.store' => 'can.permission:catalog.manage',
            'catalog.products.update' => 'can.permission:catalog.manage',
            'catalog.products.destroy' => 'can.permission:catalog.manage',
            'catalog.products.variants.store' => 'can.permission:catalog.manage',
            'catalog.products.variants.update' => 'can.permission:catalog.manage',
            'catalog.products.variants.destroy' => 'can.permission:catalog.manage',
            'catalog.products.barcodes.store' => 'can.permission:catalog.manage',
            'catalog.products.barcodes.update' => 'can.permission:catalog.manage',
            'catalog.products.barcodes.destroy' => 'can.permission:catalog.manage',
        ];

        foreach ($expectations as $name => $permissionMiddleware) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Falta la ruta {$name}.");

            $middleware = $route->gatherMiddleware();

            $this->assertContains($permissionMiddleware, $middleware, "La ruta {$name} no exige {$permissionMiddleware}.");
            $this->assertContains('auth', $middleware, "La ruta {$name} no exige auth.");
            $this->assertContains('password.change', $middleware, "La ruta {$name} no exige password.change.");
            $this->assertContains('verify.session', $middleware, "La ruta {$name} no exige verify.session.");
            $this->assertNotContains('branch.access', $middleware, "La ruta {$name} no debe usar branch.access.");
        }
    }

    public function test_detalle_de_producto_ajeno_por_sucursal_no_amplia_permiso(): void
    {
        $product = $this->createSimpleProduct();
        $default = $this->makeBranch(['code' => 'PROD-DEF']);
        $other = $this->makeBranch(['code' => 'PROD-OTHER']);

        $user = $this->makeUser([
            'email' => 'prod-view-otra@prueba.cl',
            'default_branch_id' => $default->id,
            'global_role_id' => null,
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $user->id, 'branch_id' => $default->id, 'role_id' => $this->makeNoPermissionRole()->id],
            ['user_id' => $user->id, 'branch_id' => $other->id, 'role_id' => $this->role('cashier')->id],
        ]);

        $this->actingAs($user)->get("/catalog/products/{$product->id}")->assertForbidden();
    }
}
