<?php

namespace Tests\Feature\Sellers;

use Inertia\Testing\AssertableInertia as Assert;

/**
 * Listado acotado, búsqueda y opciones de usuario sin PII.
 */
class SellerListTest extends SellerTestCase
{
    public function test_paginacion_acotada_por_defecto_y_limites(): void
    {
        $branch = $this->makeBranch();

        for ($index = 1; $index <= 30; $index++) {
            $this->insertSeller([
                'code' => sprintf('PAG-%02d', $index),
                'name' => sprintf('Vendedor %02d', $index),
            ], [$branch->id]);
        }

        $this->actingAs($this->admin)
            ->get('/sellers')
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $props = $page->toArray()['props'];
                $this->assertSame(30, $props['sellers']['total']);
                $this->assertSame(25, $props['sellers']['per_page']);
                $this->assertCount(25, $props['sellers']['data']);
            });

        $this->actingAs($this->admin)
            ->get('/sellers?per_page=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sellers.per_page', 1)
                ->has('sellers.data', 1));

        $this->actingAs($this->admin)
            ->get('/sellers?per_page=500')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sellers.per_page', 100));
    }

    public function test_busqueda_por_nombre_y_codigo_escapa_comodines(): void
    {
        $branch = $this->makeBranch();

        $this->insertSeller(['code' => 'ANA-1', 'name' => 'Ana Pérez'], [$branch->id]);
        $this->insertSeller(['code' => 'BETO-1', 'name' => 'Beto Soto'], [$branch->id]);
        $this->insertSeller(['code' => 'PCT-100', 'name' => 'Porcentaje 100%'], [$branch->id]);
        $this->insertSeller(['code' => 'PCT-100X', 'name' => 'Porcentaje 100X'], [$branch->id]);

        $this->actingAs($this->admin)
            ->get('/sellers?q=Ana')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sellers.total', 1)
                ->where('sellers.data.0.name', 'Ana Pérez'));

        $this->actingAs($this->admin)
            ->get('/sellers?q=beto-1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sellers.total', 1)
                ->where('sellers.data.0.code', 'BETO-1'));

        $this->actingAs($this->admin)
            ->get('/sellers?q=%25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sellers.total', 1)
                ->where('sellers.data.0.name', 'Porcentaje 100%'));
    }

    public function test_opciones_de_usuario_solo_tienen_id_y_nombre_sin_pii(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser([
            'name' => 'Usuario Visible',
            'email' => 'no-debe-salir@example.test',
        ]);
        $this->assignBranch($user, $branch, $this->role('seller'));

        $this->actingAs($this->admin)
            ->get('/sellers')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($user): void {
                $props = $page->toArray()['props'];
                $option = collect($props['user_options'])->firstWhere('id', $user->id);

                $this->assertNotNull($option);
                $this->assertSame(['id', 'name'], array_keys($option));
                $this->assertSame('Usuario Visible', $option['name']);
            })
            ->assertDontSee('no-debe-salir@example.test', false);
    }

    public function test_administrador_por_sucursal_no_ve_usuarios_de_otras_sucursales(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        $global = $this->makeUser([
            'name' => 'Usuario Global Visible',
            'global_role_id' => $this->adminRole->id,
        ]);
        $userA = $this->makeBranchUser($branchA, $this->role('seller'), ['name' => 'Usuario Sucursal A']);
        $userB = $this->makeBranchUser($branchB, $this->role('seller'), ['name' => 'Usuario Sucursal B']);

        $manager = $this->makeBranchUser(
            $branchA,
            $this->makeRoleWithPermissions(['sellers.manage']),
            ['name' => 'Manager Sucursal A']
        );

        $this->actingAs($manager)
            ->get('/sellers')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($global, $userA, $userB, $manager): void {
                $props = $page->toArray()['props'];
                $ids = array_column($props['user_options'], 'id');

                $this->assertContains($global->id, $ids);
                $this->assertContains($userA->id, $ids);
                $this->assertContains($manager->id, $ids);
                $this->assertNotContains($userB->id, $ids);

                foreach ($props['user_options'] as $option) {
                    $this->assertSame(['id', 'name'], array_keys($option));
                }
            });
    }

    public function test_listado_no_expone_tablas_de_ventas_ni_reportes(): void
    {
        $branch = $this->makeBranch();
        $this->insertSeller(['code' => 'NO-SALES', 'name' => 'Vendedor Sin Ventas'], [$branch->id]);

        $this->actingAs($this->admin)
            ->get('/sellers')
            ->assertOk()
            ->assertDontSee('Ventas del vendedor', false)
            ->assertDontSee('Comisión ganada', false)
            ->assertDontSee('Reporte de ventas', false);
    }
}
