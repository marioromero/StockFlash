<?php

namespace Tests\Feature\Customers;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Listado acotado, filtros, orden y enlace de dashboard (CUS-01).
 */
class CustomerListTest extends CustomerTestCase
{
    private function insertCustomer(array $values): int
    {
        return (int) DB::table('customers')->insertGetId(array_merge([
            'type' => 'person',
            'origin' => 'pos',
            'marketing_opt_in' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $values));
    }

    public function test_paginacion_acotada_por_defecto_y_limites(): void
    {
        for ($index = 1; $index <= 30; $index++) {
            $this->insertCustomer(['name' => sprintf('Cliente %02d', $index)]);
        }

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $props = $page->toArray()['props'];
                $this->assertSame(30, $props['customers']['total']);
                $this->assertSame(25, $props['customers']['per_page']);
                $this->assertCount(25, $props['customers']['data']);
            });

        $this->actingAs($this->admin)
            ->get('/customers?per_page=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.per_page', 1)
                ->has('customers.data', 1));

        $this->actingAs($this->admin)
            ->get('/customers?per_page=500')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('customers.per_page', 100));
    }

    public function test_busqueda_por_nombre_escapa_comodines_y_no_busca_contacto(): void
    {
        $this->insertCustomer(['name' => 'Ana Pérez']);
        $this->insertCustomer(['name' => 'Beto Soto', 'email' => 'ana@example.test']);
        $this->insertCustomer(['name' => 'Porcentaje 100%']);
        $this->insertCustomer(['name' => 'Porcentaje 100X']);

        $this->actingAs($this->admin)
            ->get('/customers?q=Ana')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.name', 'Ana Pérez'));

        $this->actingAs($this->admin)
            ->get('/customers?q=%25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.name', 'Porcentaje 100%'));
    }

    public function test_filtros_de_grupo_estado_y_rut(): void
    {
        $groupA = $this->makeGroup(['code' => 'G-A']);
        $groupB = $this->makeGroup(['code' => 'G-B']);

        $active = $this->insertCustomer([
            'name' => 'Activo A',
            'customer_group_id' => $groupA->id,
            'fiscal_id_hash' => hash_hmac('sha256', '12345678-5', (string) config('privacy.blind_index_key')),
        ]);
        $inactive = $this->insertCustomer([
            'name' => 'Inactivo B',
            'customer_group_id' => $groupA->id,
            'is_active' => 0,
        ]);
        $blocked = $this->insertCustomer([
            'name' => 'Bloqueado C',
            'customer_group_id' => $groupB->id,
            'blocked_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get("/customers?group_id={$groupA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('customers.total', 2));

        $this->actingAs($this->admin)
            ->get('/customers?status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $active));

        $this->actingAs($this->admin)
            ->get('/customers?status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $inactive));

        $this->actingAs($this->admin)
            ->get('/customers?status=blocked')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $blocked));

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '12.345.678-5'])
            ->assertRedirect('/customers');

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $active));
    }

    public function test_filtros_invalidos_responden_422(): void
    {
        $this->actingAs($this->admin)
            ->get('/customers?status=desconocido')
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin)
            ->get('/customers?group_id=abc')
            ->assertSessionHasErrors('group_id');

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => 'parcial'])
            ->assertSessionHasErrors('rut');
    }

    public function test_orden_determinista_por_nombre_y_luego_id(): void
    {
        $first = $this->insertCustomer(['name' => 'Ana']);
        $this->insertCustomer(['name' => 'Beto']);
        $second = $this->insertCustomer(['name' => 'Ana']);

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($first, $second): void {
                $rows = $page->toArray()['props']['customers']['data'];

                $this->assertSame($first, $rows[0]['id']);
                $this->assertSame($second, $rows[1]['id']);
            });
    }

    public function test_dashboard_muestra_enlace_solo_a_usuarios_elegibles(): void
    {
        $viewer = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['customers.view'])->id,
        ]);

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_customers', true));

        $other = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([])->id,
        ]);

        $this->actingAs($other)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_customers', false));

        $this->disableCustomersModule();

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_customers', false));
    }

    public function test_dashboard_enlace_clientes_coincide_con_alcance_global_de_rutas(): void
    {
        $defaultBranch = $this->makeBranch();
        $otherBranch = $this->makeBranch();
        $customerRole = $this->makeRoleWithPermissions(['customers.view']);

        $defaultBranchUser = $this->makeBranchUser($defaultBranch, $customerRole);

        $this->actingAs($defaultBranchUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_customers', true));

        $this->actingAs($defaultBranchUser)->get('/customers')->assertOk();

        // Permiso solo en otra sucursal: ni enlace ni ruta deben autorizar,
        // porque las rutas de clientes evalúan la sucursal por defecto.
        $otherBranchOnly = $this->makeBranchUser($otherBranch, $customerRole, [
            'default_branch_id' => $defaultBranch->id,
        ]);

        $this->actingAs($otherBranchOnly)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_customers', false));

        $this->actingAs($otherBranchOnly)->get('/customers')->assertForbidden();
    }
}
