<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * CRUD persona/empresa, descuentos, estado y campos forjados (CUS-01/03).
 */
class CustomerCrudTest extends CustomerTestCase
{
    public function test_crear_persona_y_empresa_con_defaults_de_servidor(): void
    {
        $person = $this->createCustomer([
            'type' => 'person',
            'name' => 'Persona Natural',
            'email' => 'persona@example.test',
            'phone' => '+56911112222',
        ]);

        $company = $this->createCustomer([
            'type' => 'company',
            'name' => 'Empresa SpA',
            'trade_name' => 'Marca Empresa',
            'business_activity' => 'Comercio',
            'fiscal_address' => 'Calle 123',
            'fiscal_commune' => 'Santiago',
            'fiscal_city' => 'Santiago',
        ]);

        $this->assertSame('person', $person->type);
        $this->assertSame('company', $company->type);
        $this->assertSame('Marca Empresa', $company->trade_name);

        foreach ([$person, $company] as $customer) {
            $customer->refresh();
            $this->assertSame('pos', $customer->origin);
            $this->assertSame(0, (int) $customer->marketing_opt_in);
            $this->assertTrue($customer->is_active);
            $this->assertNull($customer->last_activity_at);
            $this->assertNull($customer->blocked_at);
            $this->assertNull($customer->anonymized_at);
            $this->assertNull($customer->deleted_at);
        }
    }

    public function test_campos_forjados_se_ignoran_al_crear(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Forjado',
            'is_active' => false,
            'origin' => 'online',
            'marketing_opt_in' => 1,
            'last_activity_at' => '2000-01-01 00:00:00',
            'blocked_at' => '2000-01-02 00:00:00',
            'anonymized_at' => '2000-01-03 00:00:00',
            'user_id' => 999999,
            'fiscal_id_hash' => str_repeat('a', 64),
            'fiscal_id_encrypted' => 'forjado',
        ]);

        $customer->refresh();

        $this->assertTrue($customer->is_active);
        $this->assertSame('pos', $customer->origin);
        $this->assertSame(0, (int) $customer->marketing_opt_in);
        $this->assertNull($customer->last_activity_at);
        $this->assertNull($customer->blocked_at);
        $this->assertNull($customer->anonymized_at);
        $this->assertNull($customer->fiscal_id_hash);
        $this->assertNull($customer->fiscal_id_encrypted);
    }

    public function test_actualizar_campos_no_sensibles_y_conservar_sensibles_en_blanco(): void
    {
        $group = $this->makeGroup(['default_discount_percent' => '5.00', 'max_discount_percent' => '10.00']);

        $customer = $this->createCustomer([
            'name' => 'Original',
            'email' => 'original@example.test',
            'phone' => '+56911112222',
            'business_activity' => 'Giro Original',
            'fiscal_address' => 'Calle Original 1',
            'customer_group_id' => $group->id,
        ]);

        $this->actingAs($this->admin)
            ->patch("/customers/{$customer->id}", [
                'name' => 'Actualizado',
                'trade_name' => 'Fantasia',
                'email' => '',
                'phone' => null,
                'business_activity' => '',
                'fiscal_address' => null,
                'customer_group_id' => null,
                'default_discount_percent' => null,
                'max_discount_percent' => null,
            ])
            ->assertRedirect("/customers/{$customer->id}");

        $customer->refresh();

        $this->assertSame('Actualizado', $customer->name);
        $this->assertSame('Fantasia', $customer->trade_name);
        $this->assertSame('original@example.test', $customer->email);
        $this->assertSame('+56911112222', $customer->phone);
        $this->assertSame('Giro Original', $customer->business_activity);
        $this->assertSame('Calle Original 1', $customer->fiscal_address);
        $this->assertNull($customer->customer_group_id);
        $this->assertNull($customer->default_discount_percent);
        $this->assertNull($customer->max_discount_percent);
    }

    public function test_estado_se_alterna_sin_borrado_fisico(): void
    {
        $customer = $this->createCustomer();

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/deactivate")
            ->assertRedirect("/customers/{$customer->id}");

        $this->assertFalse($customer->refresh()->is_active);
        $this->assertNull($customer->deleted_at);

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/activate")
            ->assertRedirect("/customers/{$customer->id}");

        $this->assertTrue($customer->refresh()->is_active);
        $this->assertSame(1, Customer::query()->whereKey($customer->id)->count());
    }

    public function test_cliente_bloqueado_no_se_reactiva_y_sus_flags_no_se_limpian(): void
    {
        $customer = $this->createCustomer(['name' => 'Cliente Bloqueado']);
        DB::table('customers')->where('id', $customer->id)->update([
            'is_active' => 0,
            'blocked_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/activate")
            ->assertSessionHasErrors('customer');

        $this->assertFalse($customer->refresh()->is_active);
        $this->assertNotNull($customer->blocked_at);

        $this->actingAs($this->admin)
            ->patch("/customers/{$customer->id}", [
                'blocked_at' => null,
            ])
            ->assertRedirect();

        $customer->refresh();
        $this->assertSame('Cliente Bloqueado', $customer->name);
        $this->assertNotNull($customer->blocked_at);
    }

    public function test_cliente_anonimizado_rechaza_edicion_y_cambios_de_estado(): void
    {
        $customer = $this->createCustomer();
        DB::table('customers')->where('id', $customer->id)->update(['anonymized_at' => now()]);

        $this->actingAs($this->admin)
            ->patch("/customers/{$customer->id}", ['name' => 'No debe cambiar'])
            ->assertSessionHasErrors('customer');

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/activate")
            ->assertSessionHasErrors('customer');

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/deactivate")
            ->assertSessionHasErrors('customer');

        $customer->refresh();
        $this->assertNotNull($customer->anonymized_at);
        $this->assertTrue($customer->is_active);
    }

    public function test_descuentos_exactos_nullables_y_efectivos_heredados(): void
    {
        $group = $this->makeGroup([
            'default_discount_percent' => '5.00',
            'max_discount_percent' => '10.00',
        ]);

        $customer = $this->createCustomer([
            'name' => 'Descuentos',
            'customer_group_id' => $group->id,
            'default_discount_percent' => '7.5',
            'max_discount_percent' => '100',
        ]);

        $customer->refresh();

        $this->assertSame('7.50', $customer->default_discount_percent);
        $this->assertSame('100.00', $customer->max_discount_percent);

        $this->actingAs($this->admin)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.discounts.own_default', '7.50')
                ->where('customer.discounts.own_max', '100.00')
                ->where('customer.discounts.group_default', '5.00')
                ->where('customer.discounts.group_max', '10.00')
                ->where('customer.discounts.effective_default', '7.50')
                ->where('customer.discounts.effective_max', '100.00'));

        $this->actingAs($this->admin)
            ->patch("/customers/{$customer->id}", [
                'default_discount_percent' => null,
                'max_discount_percent' => null,
            ])
            ->assertRedirect();

        $customer->refresh();
        $this->assertNull($customer->default_discount_percent);
        $this->assertNull($customer->max_discount_percent);

        $this->actingAs($this->admin)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.discounts.effective_default', '5.00')
                ->where('customer.discounts.effective_max', '10.00'));
    }

    public function test_descuentos_fuera_de_rango_o_no_decimales_son_rechazados(): void
    {
        foreach (['101', '-1', '100.01', '5.555', 'abc', '1e2', 5.5] as $value) {
            $this->actingAs($this->admin)
                ->post('/customers', $this->customerPayload([
                    'name' => 'Inválido '.md5((string) $value),
                    'default_discount_percent' => $value,
                ]))
                ->assertSessionHasErrors('default_discount_percent');
        }

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_no_crea_ventas_reservas_ni_movimientos_de_stock(): void
    {
        $group = $this->makeGroup();
        $customer = $this->createCustomer(['customer_group_id' => $group->id, 'rut' => '12.345.678-5']);

        $this->actingAs($this->admin)->patch("/customers/{$customer->id}", ['name' => 'Cambio']);
        $this->actingAs($this->admin)->post("/customers/{$customer->id}/deactivate");

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('sales'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('stock_reservations'));
    }
}
