<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Support\Customers\CustomerRut;
use App\Support\Customers\RutException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * RUT: normalización, módulo 11, cifrado, índice ciego y búsqueda exacta.
 */
class CustomerRutTest extends CustomerTestCase
{
    public function test_normalizacion_equivalente_puntos_espacios_guiones_mayusculas_y_ceros(): void
    {
        $rut = app(CustomerRut::class);

        $this->assertSame('12345678-5', $rut->normalize(' 12.345.678-5 '));
        $this->assertSame('12345678-5', $rut->normalize('0012345678-5'));
        $this->assertSame('12345678-5', $rut->normalize('12 345 678 5'));
        $this->assertSame('6000000-K', $rut->normalize('6.000.000-k'));
        $this->assertSame('6000000-K', $rut->normalize('0006000000-K'));
    }

    public function test_rut_invalido_por_digito_vacio_o_solo_puntuacion(): void
    {
        $rut = app(CustomerRut::class);

        foreach (['12345678-4', '123', '.-', '12.345.678-X', ''] as $invalid) {
            try {
                $rut->normalize($invalid);
                $this->fail("Se esperaba RUT inválido: {$invalid}");
            } catch (RutException $exception) {
                $this->assertSame('rut', $exception->field);

                if ($invalid !== '') {
                    $this->assertStringNotContainsString($invalid, $exception->getMessage());
                }
            }
        }
    }

    public function test_crear_rut_cifra_en_reposo_y_calcula_hmac_exacto(): void
    {
        $customer = $this->createCustomer(['rut' => '12.345.678-5']);

        $row = DB::table('customers')->where('id', $customer->id)->first();
        $expectedHash = hash_hmac('sha256', '12345678-5', (string) config('privacy.blind_index_key'));

        $this->assertSame($expectedHash, $row->fiscal_id_hash);
        $this->assertSame(64, strlen((string) $row->fiscal_id_hash));
        $this->assertNotSame(hash('sha256', '12345678-5'), $row->fiscal_id_hash);
        $this->assertNotSame('12345678-5', $row->fiscal_id_encrypted);
        $this->assertSame('12345678-5', app(CustomerRut::class)->decrypt($row->fiscal_id_encrypted));
    }

    public function test_rut_con_k_se_normaliza_y_es_buscable(): void
    {
        $customer = $this->createCustomer(['rut' => '6.000.000-k']);

        $row = DB::table('customers')->where('id', $customer->id)->first();
        $this->assertSame('6000000-K', app(CustomerRut::class)->decrypt($row->fiscal_id_encrypted));

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '0006000000-k'])
            ->assertRedirect('/customers');

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $customer->id));
    }

    public function test_variantes_de_puntuacion_equivalentes_son_duplicado_sin_filtrar_rut(): void
    {
        $this->createCustomer(['rut' => '12.345.678-5']);

        $response = $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload(['rut' => '0012345678-5']))
            ->assertSessionHasErrors('rut');

        $this->assertSame(1, Customer::query()->count());

        $errors = session('errors')->get('rut');
        $this->assertStringNotContainsString('12345678', implode(' ', $errors));
        $this->assertStringNotContainsString(hash_hmac('sha256', '12345678-5', (string) config('privacy.blind_index_key')), implode(' ', $errors));

        $response->assertSessionHasErrors('rut');
    }

    public function test_clave_ausente_o_corta_falla_solo_en_operaciones_con_rut(): void
    {
        config(['privacy.blind_index_key' => '']);

        $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload(['name' => 'Sin RUT permitido']))
            ->assertRedirect();

        $this->assertSame(1, Customer::query()->count());

        $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload(['name' => 'Con RUT bloqueado', 'rut' => '12.345.678-5']))
            ->assertSessionHasErrors('rut');

        $this->assertSame(1, Customer::query()->count());

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '12.345.678-5'])
            ->assertSessionHasErrors('rut');

        $this->assertNull(session('customers.rut_hash'));

        config(['privacy.blind_index_key' => 'short']);

        $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload(['name' => 'Clave corta', 'rut' => '12.345.678-5']))
            ->assertSessionHasErrors('rut');

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_busqueda_exacta_por_indice_ciego_y_rechazo_de_parciales(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Cliente con RUT',
            'rut' => '12.345.678-5',
        ]);

        $this->createCustomer(['name' => '12345678-5', 'rut' => null]);

        foreach (['12345678-5', '12.345.678-5', '0012345678-5'] as $query) {
            $this->actingAs($this->admin)
                ->post('/customers/rut-search', ['rut' => $query])
                ->assertRedirect('/customers');

            $this->actingAs($this->admin)
                ->get('/customers')
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($customer): void {
                    $page->where('customers.total', 1)
                        ->where('customers.data.0.id', $customer->id);

                    $encoded = (string) json_encode($page->toArray()['props']);
                    $this->assertStringNotContainsString('12345678', $encoded);
                });
        }

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '1234567'])
            ->assertSessionHasErrors('rut');

        $this->actingAs($this->admin)
            ->post('/customers/rut-search', ['rut' => '11111111-1'])
            ->assertRedirect('/customers');

        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('customers.total', 0));
    }

    public function test_rut_plano_no_aparece_en_respuesta_ni_auditoria(): void
    {
        $this->createCustomer(['rut' => '12.345.678-5']);

        $hash = hash_hmac('sha256', '12345678-5', (string) config('privacy.blind_index_key'));

        $response = $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk();

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('12345678-5', $content);
        $this->assertStringNotContainsString($hash, $content);

        $audit = DB::table('audit_logs')->where('event', 'customers.created')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('12345678', (string) $audit->new_values);
        $this->assertStringNotContainsString($hash, (string) $audit->new_values);
    }
}
