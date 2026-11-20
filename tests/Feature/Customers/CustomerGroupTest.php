<?php

namespace Tests\Feature\Customers;

use App\Models\CustomerGroup;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * CRUD, unicidad, descuentos, estado y FK SET NULL de grupos (CUS-02).
 */
class CustomerGroupTest extends CustomerTestCase
{
    public function test_crear_grupo_recorta_codigo_y_aplica_defaults(): void
    {
        $this->actingAs($this->admin)
            ->post('/customers/groups', [
                'code' => '  MAYORISTA  ',
                'name' => '  Mayorista  ',
                'default_discount_percent' => '5',
                'max_discount_percent' => '10.5',
            ])
            ->assertRedirect('/customers/groups');

        $group = CustomerGroup::query()->firstOrFail();

        $this->assertSame('MAYORISTA', $group->code);
        $this->assertSame('Mayorista', $group->name);
        $this->assertSame('5.00', $group->default_discount_percent);
        $this->assertSame('10.50', $group->max_discount_percent);
        $this->assertTrue($group->is_active);
        $this->assertNotNull($group->created_at);
    }

    public function test_grupo_sin_descuento_por_defecto_usa_cero_y_tope_nulo(): void
    {
        $this->actingAs($this->admin)
            ->post('/customers/groups', ['code' => 'BASE', 'name' => 'Base'])
            ->assertRedirect('/customers/groups');

        $group = CustomerGroup::query()->where('code', 'BASE')->firstOrFail();

        $this->assertSame('0.00', $group->default_discount_percent);
        $this->assertNull($group->max_discount_percent);
    }

    public function test_codigo_duplicado_es_rechazado_sin_segundo_grupo(): void
    {
        $this->makeGroup(['code' => 'DUP']);

        $this->actingAs($this->admin)
            ->post('/customers/groups', ['code' => 'DUP', 'name' => 'Duplicado'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, CustomerGroup::query()->where('code', 'DUP')->count());
    }

    public function test_descuentos_fuera_de_rango_o_precision_invalida_son_rechazados(): void
    {
        foreach (['101', '-1', '100.01', '5.555', 'abc', '1e2'] as $value) {
            $this->actingAs($this->admin)
                ->post('/customers/groups', [
                    'code' => 'BAD-'.substr(md5($value), 0, 6),
                    'name' => 'Inválido',
                    'default_discount_percent' => $value,
                ])
                ->assertSessionHasErrors('default_discount_percent');
        }

        $this->assertSame(0, CustomerGroup::query()->count());
    }

    public function test_actualizar_grupo_permite_tope_nulo_y_descuento_exacto(): void
    {
        $group = $this->makeGroup([
            'code' => 'EDIT',
            'default_discount_percent' => '5.00',
            'max_discount_percent' => '15.00',
        ]);

        $this->actingAs($this->admin)
            ->patch("/customers/groups/{$group->id}", [
                'code' => 'EDIT2',
                'name' => 'Editado',
                'default_discount_percent' => '7.5',
                'max_discount_percent' => null,
            ])
            ->assertRedirect('/customers/groups');

        $group->refresh();

        $this->assertSame('EDIT2', $group->code);
        $this->assertSame('Editado', $group->name);
        $this->assertSame('7.50', $group->default_discount_percent);
        $this->assertNull($group->max_discount_percent);
    }

    public function test_activar_y_desactivar_no_borran_la_fila(): void
    {
        $group = $this->makeGroup(['code' => 'STATE']);

        $this->actingAs($this->admin)
            ->post("/customers/groups/{$group->id}/deactivate")
            ->assertRedirect('/customers/groups');

        $group->refresh();
        $this->assertFalse($group->is_active);
        $this->assertNull($group->deleted_at);

        $this->actingAs($this->admin)
            ->post("/customers/groups/{$group->id}/activate")
            ->assertRedirect('/customers/groups');

        $this->assertTrue($group->refresh()->is_active);
        $this->assertSame(1, CustomerGroup::query()->whereKey($group->id)->count());
    }

    public function test_fk_set_null_al_eliminar_fisicamente_un_grupo(): void
    {
        $group = $this->makeGroup(['code' => 'FKNULL']);
        $customer = $this->createCustomer(['customer_group_id' => $group->id]);

        $this->assertSame((int) $group->id, (int) $customer->refresh()->customer_group_id);

        DB::table('customer_groups')->where('id', $group->id)->delete();

        $this->assertNull($customer->refresh()->customer_group_id);
    }

    public function test_listado_paginado_buscable_y_sin_pii(): void
    {
        $this->makeGroup(['code' => 'AAA', 'name' => 'Mayorista']);
        $this->makeGroup(['code' => 'BBB', 'name' => 'Empleados']);

        $this->actingAs($this->admin)
            ->get('/customers/groups?q=Mayor')
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $props = $page->toArray()['props'];

                $this->assertSame(1, $props['groups']['total']);
                $this->assertSame('AAA', $props['groups']['data'][0]['code']);
                $this->assertArrayNotHasKey('customers', $props['groups']['data'][0]);
                $this->assertSame(25, $props['groups']['per_page']);
            });

        $this->actingAs($this->admin)
            ->get('/customers/groups?per_page=500')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('groups.per_page', 100));
    }

    public function test_grupo_inexistente_o_no_numerico_es_404(): void
    {
        $this->actingAs($this->admin)->get('/customers/groups/999999')->assertNotFound();
        $this->actingAs($this->admin)->get('/customers/groups/abc')->assertNotFound();
        $this->actingAs($this->admin)->patch('/customers/groups/abc', [])->assertNotFound();
    }

    public function test_grupo_desconocido_en_cliente_es_422(): void
    {
        $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload(['customer_group_id' => 999999]))
            ->assertSessionHasErrors('customer_group_id');

        $this->assertSame(0, DB::table('customers')->count());
    }
}
