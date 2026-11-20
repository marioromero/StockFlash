<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * RBAC, gate de módulo, licencia solo lectura y rutas públicas preservadas.
 */
class CustomerAuthorizationTest extends CustomerTestCase
{
    public function test_sin_permisos_todas_las_rutas_de_clientes_son_403(): void
    {
        $customer = $this->createCustomer();
        $group = $this->makeGroup();

        $user = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([])->id,
        ]);

        $this->actingAs($user)->get('/customers')->assertForbidden();
        $this->actingAs($user)->get("/customers/{$customer->id}")->assertForbidden();
        $this->actingAs($user)->get('/customers/groups')->assertForbidden();
        $this->actingAs($user)->get("/customers/groups/{$group->id}")->assertForbidden();
        $this->actingAs($user)->post('/customers/rut-search', ['rut' => '12.345.678-5'])->assertForbidden();
        $this->actingAs($user)->post('/customers/rut-search/clear')->assertForbidden();
        $this->actingAs($user)->post('/customers', $this->customerPayload())->assertForbidden();
        $this->actingAs($user)->patch("/customers/{$customer->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->post('/customers/groups', ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get("/customers/{$customer->id}/reveal")->assertForbidden();
    }

    public function test_lectura_con_view_y_escritura_con_manage(): void
    {
        $group = $this->makeGroup();
        $customer = $this->createCustomer(['customer_group_id' => $group->id]);

        $viewer = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['customers.view'])->id,
        ]);

        $this->actingAs($viewer)->get('/customers')->assertOk();
        $this->actingAs($viewer)->get("/customers/{$customer->id}")->assertOk();
        $this->actingAs($viewer)->get('/customers/groups')->assertOk();
        $this->actingAs($viewer)->get("/customers/groups/{$group->id}")->assertOk();
        $this->actingAs($viewer)->get("/customers/{$customer->id}/reveal")->assertForbidden();
        $this->actingAs($viewer)->post('/customers', $this->customerPayload())->assertForbidden();
        $this->actingAs($viewer)->patch("/customers/{$customer->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post('/customers/groups', ['code' => 'X', 'name' => 'X'])->assertForbidden();

        $manager = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([
                'customers.view', 'customers.manage',
            ])->id,
        ]);

        $this->actingAs($manager)
            ->post('/customers', $this->customerPayload(['name' => 'Creado por manager']))
            ->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'Creado por manager']);
        $this->actingAs($manager)->get("/customers/{$customer->id}/reveal")->assertForbidden();
    }

    public function test_modulo_deshabilitado_bloquea_todas_las_rutas(): void
    {
        $customer = $this->createCustomer();
        $group = $this->makeGroup();
        $this->disableCustomersModule();

        $this->actingAs($this->admin)->get('/customers')->assertForbidden();
        $this->actingAs($this->admin)->get("/customers/{$customer->id}")->assertForbidden();
        $this->actingAs($this->admin)->get('/customers/groups')->assertForbidden();
        $this->actingAs($this->admin)->get("/customers/groups/{$group->id}")->assertForbidden();
        $this->actingAs($this->admin)->post('/customers/rut-search', ['rut' => '12.345.678-5'])->assertForbidden();
        $this->actingAs($this->admin)->post('/customers/rut-search/clear')->assertForbidden();
        $this->actingAs($this->admin)->get("/customers/{$customer->id}/reveal")->assertForbidden();
        $this->actingAs($this->admin)->post('/customers', $this->customerPayload())->assertForbidden();
        $this->actingAs($this->admin)->post('/customers/groups', ['code' => 'MOD', 'name' => 'Módulo'])->assertForbidden();
    }

    public function test_sin_fila_customers_manda_pos_y_sin_pos_queda_deshabilitado(): void
    {
        $this->usePosFallbackWithoutCustomersRow();

        $this->actingAs($this->admin)->get('/customers')->assertOk();

        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($this->admin)->get('/customers')->assertForbidden();
    }

    public function test_licencia_solo_lectura_permite_consultar_pero_no_escribir(): void
    {
        $group = $this->makeGroup();
        $customer = $this->createCustomer(['customer_group_id' => $group->id]);
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload(['name' => 'No debe crearse']))
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->patch("/customers/{$customer->id}", ['name' => 'No debe cambiar'])
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/deactivate")
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post('/customers/groups', ['code' => 'RO', 'name' => 'No debe crearse'])
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->patch("/customers/groups/{$group->id}", ['name' => 'No debe cambiar'])
            ->assertSessionHasErrors('read_only');

        $this->assertSame(1, Customer::query()->count());
        $this->assertDatabaseMissing('customers', ['name' => 'No debe crearse']);
        $this->assertDatabaseMissing('customer_groups', ['code' => 'RO']);
    }

    public function test_identificadores_no_numericos_o_inexistentes_son_404(): void
    {
        $this->actingAs($this->admin)->get('/customers/abc')->assertNotFound();
        $this->actingAs($this->admin)->get('/customers/999999')->assertNotFound();
        $this->actingAs($this->admin)->patch('/customers/abc', [])->assertNotFound();
        $this->actingAs($this->admin)->get('/customers/999999/reveal')->assertNotFound();
    }

    public function test_rutas_publicas_documentacion_roadmap_y_setup_siguen_disponibles(): void
    {
        $this->get('/docs/')->assertOk();
        $roadmap = $this->get('/roadmap')->assertOk();
        $this->assertStringContainsString('no-store', (string) $roadmap->headers->get('Cache-Control'));

        $setup = $this->get('/setup');
        $this->assertContains($setup->getStatusCode(), [200, 302]);
    }
}
