<?php

namespace Tests\Feature\Customers;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Búsqueda de RUT por POST: sin RUT en URL, cuerpo, props ni sesión.
 */
class CustomerRutSearchPrivacyTest extends CustomerTestCase
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

    public function test_flujo_de_busqueda_no_expone_el_rut_ni_sus_variantes(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Cliente Secreto',
            'rut' => '6.000.000-k',
        ]);

        $variants = [
            '6.000.000-k',
            '6.000.000-K',
            '6000000-k',
            '6000000-K',
            '0006000000-K',
            '6 000 000 k',
        ];

        $posted = $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '0006000000-K'])
            ->assertRedirect('/customers');

        $location = (string) $posted->headers->get('Location');
        $this->assertStringNotContainsString('rut=', $location);
        $this->assertStringNotContainsString('6000000', $location);

        $postBody = (string) $posted->getContent();

        foreach ($variants as $variant) {
            $this->assertStringNotContainsString($variant, $postBody);
        }

        $page = $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk();

        $body = (string) $page->getContent();

        foreach ($variants as $variant) {
            $this->assertStringNotContainsString($variant, $body);
        }

        $this->assertStringNotContainsString('rut=', $body);

        $page->assertInertia(function (Assert $page) use ($customer): void {
            $page->where('customers.total', 1)
                ->where('customers.data.0.id', $customer->id)
                ->where('filters.rut_filter', true)
                ->missing('filters.rut');

            $props = $page->toArray()['props'];
            $encoded = (string) json_encode($props);

            $this->assertStringNotContainsString('6000000', $encoded);
            $this->assertStringNotContainsString('rut_hash', $encoded);

            $hash = hash_hmac(
                'sha256',
                '6000000-K',
                (string) config('privacy.blind_index_key'),
            );

            $this->assertStringNotContainsString($hash, $encoded);
        });

        $expectedHash = hash_hmac(
            'sha256',
            '6000000-K',
            (string) config('privacy.blind_index_key'),
        );

        $this->assertSame($expectedHash, session('customers.rut_hash'));
        $this->assertNull(session('customers.rut'));

        $session = (string) json_encode(session()->all());

        foreach ($variants as $variant) {
            $this->assertStringNotContainsString($variant, $session);
        }
    }

    public function test_filtro_exacto_se_mantiene_al_paginar(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Objetivo RUT',
            'rut' => '12.345.678-5',
        ]);

        for ($index = 1; $index <= 30; $index++) {
            $this->insertCustomer(['name' => sprintf('Otro Cliente %02d', $index)]);
        }

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '12.345.678-5'])
            ->assertRedirect('/customers');

        $this->actingAs($this->admin)
            ->get('/customers?per_page=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.current_page', 1)
                ->where('customers.data.0.id', $customer->id)
                ->where('filters.rut_filter', true));

        $this->actingAs($this->admin)
            ->get('/customers?per_page=1&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.current_page', 2)
                ->where('customers.data', [])
                ->where('filters.rut_filter', true));
    }

    public function test_clave_ausente_o_corta_falla_cerrado_sin_filtro(): void
    {
        config(['privacy.blind_index_key' => '']);

        $missing = $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '12.345.678-5'])
            ->assertRedirect('/customers')
            ->assertSessionHasErrors('rut');

        $this->assertNull(session('customers.rut_hash'));
        $this->assertStringNotContainsString('12345678', (string) $missing->getContent());

        config(['privacy.blind_index_key' => 'short']);

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '12.345.678-5'])
            ->assertRedirect('/customers')
            ->assertSessionHasErrors('rut');

        $this->assertNull(session('customers.rut_hash'));
    }

    public function test_get_forjado_con_rut_redirige_a_url_limpia_sin_reflejarlo(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/customers?rut=12.345.678-5&status=active')
            ->assertRedirect();

        $location = (string) $response->headers->get('Location');

        $this->assertStringNotContainsString('rut=', $location);
        $this->assertStringNotContainsString('12345678', $location);
        $this->assertStringContainsString('status=active', $location);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('12.345.678-5', $body);
        $this->assertStringNotContainsString('12345678-5', $body);
    }

    public function test_limpiar_filtro_elimina_la_sesion_y_vuelve_al_listado_completo(): void
    {
        $this->createCustomer([
            'name' => 'Con RUT',
            'rut' => '12.345.678-5',
        ]);
        $this->createCustomer(['name' => 'Sin RUT']);

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '12.345.678-5'])
            ->assertRedirect('/customers');

        $this->assertNotNull(session('customers.rut_hash'));

        $this->actingAs($this->admin)
            ->post('/customers/rut-search/clear')
            ->assertRedirect('/customers');

        $this->assertNull(session('customers.rut_hash'));

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 2)
                ->where('filters.rut_filter', false));
    }
}
