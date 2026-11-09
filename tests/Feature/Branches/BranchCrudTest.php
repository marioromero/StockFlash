<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class BranchCrudTest extends BranchTestCase
{
    public function test_index_lista_sucursales_ordenadas_por_codigo(): void
    {
        $this->makeBranch(['code' => 'SUC-B', 'name' => 'Sucursal B']);
        $this->makeBranch(['code' => 'SUC-A', 'name' => 'Sucursal A']);

        $response = $this->actingAs($this->admin)->get('/branches');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Branches/Index')
            ->has('branches', 2)
            ->where('branches.0.code', 'SUC-A')
            ->where('branches.1.code', 'SUC-B')
            ->where('active_count', 2)
        );
    }

    public function test_store_crea_sucursal_normalizando_codigo_y_registra_datos(): void
    {
        $payload = $this->validPayload([
            'code' => 'suc-c',
            'name' => 'Sucursal C',
            'settings' => '{"canal": "mostrador"}',
        ]);

        $response = $this->actingAs($this->admin)->post('/branches', $payload);

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('branches', [
            'code' => 'SUC-C',
            'name' => 'Sucursal C',
            'is_active' => 1,
            'requires_cash_control' => 1,
        ]);

        $storedSettings = DB::table('branches')->where('code', 'SUC-C')->value('settings');

        $this->assertSame(['canal' => 'mostrador'], json_decode((string) $storedSettings, true));
    }

    public function test_store_preserva_objeto_json_vacio(): void
    {
        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'SUC-EMPTY',
            'settings' => '{}',
        ]));

        $response->assertRedirect(route('branches.index'));

        $storedSettings = DB::table('branches')->where('code', 'SUC-EMPTY')->value('settings');

        $this->assertSame('{}', trim((string) $storedSettings));
    }

    public function test_store_trata_el_arreglo_vacio_de_settings_como_objeto(): void
    {
        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'SUC-EMPTY-ARR',
            'settings' => [],
        ]));

        $response->assertRedirect(route('branches.index'));

        $storedSettings = DB::table('branches')->where('code', 'SUC-EMPTY-ARR')->value('settings');

        $this->assertSame('{}', trim((string) $storedSettings));
    }

    public function test_settings_permite_claves_con_pin_como_subcadena(): void
    {
        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'SUC-SHIP',
            'settings' => '{"shipping_cost": 1500, "mapping": "canal"}',
        ]));

        $response->assertRedirect(route('branches.index'));

        $storedSettings = DB::table('branches')->where('code', 'SUC-SHIP')->value('settings');

        $this->assertSame(
            ['shipping_cost' => 1500, 'mapping' => 'canal'],
            json_decode((string) $storedSettings, true)
        );
    }

    public function test_settings_rechaza_secretos_por_token_exacto(): void
    {
        $casos = [
            '{"user_pin": "1234"}',
            '{"api_key": "clave"}',
            '{"password": "secreta"}',
            '{"secret_token": "valor"}',
        ];

        foreach ($casos as $index => $settings) {
            $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
                'code' => "SUC-TOK{$index}",
                'settings' => $settings,
            ]));

            $response->assertSessionHasErrors('settings');
        }

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_settings_rechaza_secretos_camel_case_y_concatenados(): void
    {
        $casos = [
            '{"secretToken": "valor-real"}',
            '{"authToken": "valor-real"}',
            '{"clientSecret": "valor-real"}',
            '{"apiKey": "valor-real"}',
            '{"privateKey": "valor-real"}',
            '{"creditCard": "4111111111111111"}',
            '{"integration": {"clientSecret": "valor-real"}}',
        ];

        foreach ($casos as $index => $settings) {
            $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
                'code' => "SUC-CAMEL{$index}",
                'settings' => $settings,
            ]));

            $response->assertSessionHasErrors('settings');
        }

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_store_permite_ajustes_heredables_nulos(): void
    {
        $payload = $this->validPayload([
            'code' => 'SUC-H',
            'sales_flow_mode' => null,
            'seller_code_policy' => null,
            'allow_negative_stock' => null,
        ]);

        $response = $this->actingAs($this->admin)->post('/branches', $payload);

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseHas('branches', [
            'code' => 'SUC-H',
            'sales_flow_mode' => null,
            'seller_code_policy' => null,
            'allow_negative_stock' => null,
        ]);
    }

    public function test_edit_muestra_el_formulario_con_la_sucursal(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-E', 'name' => 'Sucursal Existente']);

        $response = $this->actingAs($this->admin)->get("/branches/{$branch->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Branches/Form')
            ->where('branch.id', $branch->id)
            ->where('branch.code', 'SUC-E')
            ->where('branch.name', 'Sucursal Existente')
        );
    }

    public function test_update_actualiza_campos_editables_sin_cambiar_codigo(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-U', 'name' => 'Nombre Original']);

        $payload = $this->validPayload([
            'code' => 'SUC-U',
            'name' => 'Nombre Actualizado',
            'commune' => 'Ñuñoa',
            'requires_cash_control' => false,
        ]);
        unset($payload['is_active']);

        $response = $this->actingAs($this->admin)->put("/branches/{$branch->id}", $payload);

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('success');

        $branch->refresh();

        $this->assertSame('SUC-U', $branch->code);
        $this->assertSame('Nombre Actualizado', $branch->name);
        $this->assertSame('Ñuñoa', $branch->commune);
        $this->assertFalse($branch->requires_cash_control);
    }

    public function test_update_rechaza_cambio_de_codigo(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-I', 'name' => 'Inmutable']);

        $payload = $this->validPayload([
            'code' => 'SUC-OTRO',
            'name' => 'Intento de cambio',
        ]);
        unset($payload['is_active']);

        $response = $this->actingAs($this->admin)->put("/branches/{$branch->id}", $payload);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'code' => 'SUC-I',
            'name' => 'Inmutable',
        ]);
    }

    public function test_validaciones_de_codigo_y_nombre(): void
    {
        $casos = [
            ['code' => '', 'name' => 'Sucursal', 'campo' => 'code'],
            ['code' => 'SUC A!', 'name' => 'Sucursal', 'campo' => 'code'],
            ['code' => str_repeat('A', 31), 'name' => 'Sucursal', 'campo' => 'code'],
            ['code' => 'SUC-V1', 'name' => '', 'campo' => 'name'],
            ['code' => 'SUC-V2', 'name' => str_repeat('N', 151), 'campo' => 'name'],
            ['code' => 'SUC-V3', 'name' => 'Sucursal', 'type' => 'bodega', 'campo' => 'type'],
        ];

        foreach ($casos as $caso) {
            $campo = $caso['campo'];
            unset($caso['campo']);

            $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload($caso));

            $response->assertSessionHasErrors($campo);
        }

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_settings_rechaza_json_invalido_listas_y_escalares(): void
    {
        $settingsInvalidos = [
            'esto no es json',
            '[1, 2, 3]',
            '"texto"',
            '10',
            '{"clave": ',
            '{"password": "no-permitido"}',
        ];

        foreach ($settingsInvalidos as $index => $settings) {
            $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
                'code' => "SUC-S{$index}",
                'settings' => $settings,
            ]));

            $response->assertSessionHasErrors('settings');
        }

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_unicidad_de_codigo_es_un_error_de_validacion(): void
    {
        $this->makeBranch(['code' => 'SUC-DUP']);

        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'suc-dup',
        ]));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_violacion_unique_de_base_de_datos_se_convierte_en_error_sin_estado_parcial(): void
    {
        $this->makeBranch(['code' => 'SUC-BASE']);

        // Simula una carrera: otra conexión inserta el mismo código justo
        // antes del INSERT del controlador, después de la validación.
        Branch::creating(function (Branch $branch): void {
            DB::table('branches')->insert([
                'code' => $branch->code,
                'name' => 'Duplicada por carrera',
                'type' => 'store',
                'is_active' => 1,
                'requires_cash_control' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'SUC-CARRERA',
        ]));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('branches', ['code' => 'SUC-CARRERA']);
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_soft_delete_no_borra_fisicamente_y_oculta_del_index(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-D', 'name' => 'Sucursal Eliminable']);

        $response = $this->actingAs($this->admin)->delete("/branches/{$branch->id}");

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('branches', ['id' => $branch->id]);

        $this->actingAs($this->admin)
            ->get('/branches')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Branches/Index')
                ->has('branches', 0)
            );
    }

    public function test_codigo_de_sucursal_eliminada_sigue_reservado(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-R']);

        $this->actingAs($this->admin)->delete("/branches/{$branch->id}");

        $response = $this->actingAs($this->admin)->post('/branches', $this->validPayload([
            'code' => 'SUC-R',
            'name' => 'Reutilización',
        ]));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseHas('branches', [
            'code' => 'SUC-R',
            'name' => 'Sucursal A',
        ]);
    }

    public function test_sucursal_inexistente_responde_404_en_rutas_de_administracion(): void
    {
        $this->actingAs($this->admin)
            ->get('/branches/999999/edit')
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put('/branches/999999', $this->validPayload())
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete('/branches/999999')
            ->assertNotFound();
    }
}
