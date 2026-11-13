<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

class UserCrudTest extends UserTestCase
{
    public function test_index_lista_usuarios_y_filtra_por_nombre_o_correo(): void
    {
        $this->makeUser(['name' => 'Ana Pérez', 'email' => 'ana@prueba.cl']);
        $this->makeUser(['name' => 'Bruno Soto', 'email' => 'bruno@prueba.cl']);

        $response = $this->actingAs($this->admin)->get('/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 3)
        );

        $this->actingAs($this->admin)
            ->get('/users?search=ana')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'ana@prueba.cl')
            );

        $this->actingAs($this->admin)
            ->get('/users?search=bruno@prueba')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Bruno Soto')
            );
    }

    public function test_index_pagina_resultados_en_servidor(): void
    {
        for ($i = 1; $i <= 18; $i++) {
            $this->makeUser([
                'name' => sprintf('Usuario %02d', $i),
                'email' => "paginado{$i}@prueba.cl",
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/users');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 15)
            ->where('users.total', 19)
            ->where('users.last_page', 2)
        );
    }

    public function test_store_crea_usuario_con_cambio_de_contrasena_obligatorio_y_audita(): void
    {
        $payload = $this->validUserPayload([
            'name' => 'Ana Pérez',
            'email' => 'ANA@PRUEBA.CL',
        ]);

        $response = $this->actingAs($this->admin)->post('/users', $payload);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user = User::query()->where('email', 'ana@prueba.cl')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('ContrasenaTemporal123!', $user->password));
        $this->assertSame($this->role('cashier')->id, $user->global_role_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'auditable_type' => 'users',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
            'is_personal_data_access' => 1,
        ]);

        $row = DB::table('audit_logs')->where('event', 'user.created')->firstOrFail();
        $this->assertStringNotContainsString('ContrasenaTemporal123!', (string) $row->new_values);
        $this->assertStringNotContainsString('password', (string) $row->new_values);
    }

    public function test_store_exige_contrasena_de_15_a_64_caracteres(): void
    {
        $corta = $this->validUserPayload(['password' => str_repeat('A', 14)]);
        $this->actingAs($this->admin)->post('/users', $corta)->assertSessionHasErrors('password');

        $larga = $this->validUserPayload(['password' => str_repeat('A', 65)]);
        $this->actingAs($this->admin)->post('/users', $larga)->assertSessionHasErrors('password');

        $valida = $this->validUserPayload(['password' => str_repeat('A', 64)]);
        $this->actingAs($this->admin)->post('/users', $valida)->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['email' => $corta['email']]);
        $this->assertDatabaseMissing('users', ['email' => $larga['email']]);
        $this->assertDatabaseHas('users', ['email' => $valida['email']]);
    }

    public function test_email_es_unico_sin_distinguir_mayusculas_incluyendo_eliminados(): void
    {
        $eliminado = $this->makeUser(['email' => 'Reservado@Prueba.CL']);
        $eliminado->delete();

        $payload = $this->validUserPayload(['email' => 'reservado@prueba.cl']);
        $this->actingAs($this->admin)->post('/users', $payload)->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'reservado@prueba.cl']);
    }

    public function test_edicion_permite_conservar_el_mismo_email_sin_colisionar_consigo_mismo(): void
    {
        $user = $this->makeUser(['email' => 'mismo@prueba.cl', 'global_role_id' => $this->role('cashier')->id]);

        $payload = $this->validUserPayload([
            'name' => 'Nombre Actualizado',
            'email' => 'MISMO@PRUEBA.CL',
            'password' => null,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('mismo@prueba.cl', $user->email);
        $this->assertSame('Nombre Actualizado', $user->name);
    }

    public function test_editar_contrasena_la_rehashea_marca_cambio_y_audita_sin_valor(): void
    {
        $user = $this->makeUser([
            'email' => 'reset@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'must_change_password' => false,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'NuevaContrasenaTemporal456!',
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaContrasenaTemporal456!', $user->password));
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertNotNull($user->password_changed_at);

        $row = DB::table('audit_logs')->where('event', 'user.password_reset')->firstOrFail();
        $this->assertSame(1, (int) $row->is_security_event);
        $this->assertStringNotContainsString('NuevaContrasenaTemporal456!', (string) $row->new_values);
    }

    public function test_pin_se_guarda_solo_como_hash_y_su_longitud_viene_de_system_configs(): void
    {
        $payload = $this->validUserPayload(['pin' => '1234']);
        $this->actingAs($this->admin)->post('/users', $payload)->assertRedirect(route('users.index'));

        $user = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertNotNull($user->pin_hash);
        $this->assertTrue(Hash::check('1234', $user->pin_hash));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.pin_reset',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
        ]);

        $invalido = $this->validUserPayload(['pin' => '123']);
        $this->actingAs($this->admin)->post('/users', $invalido)->assertSessionHasErrors('pin');

        $response = $this->actingAs($this->admin)->get("/users/{$user->id}/edit");
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Form')
            ->where('user.pin_configured', true)
        );
        $this->assertStringNotContainsString('pin_hash', (string) $response->getContent());

        $clearPayload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'pin_clear' => true,
        ]);
        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $clearPayload)
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->pin_hash);
    }

    public function test_topes_propios_validan_rangos(): void
    {
        $invalido = $this->validUserPayload([
            'max_discount_percent' => 101,
            'max_return_amount' => -1,
        ]);
        $this->actingAs($this->admin)->post('/users', $invalido)
            ->assertSessionHasErrors(['max_discount_percent', 'max_return_amount']);

        $valido = $this->validUserPayload([
            'max_discount_percent' => 0,
            'max_return_amount' => 0,
        ]);
        $this->actingAs($this->admin)->post('/users', $valido)->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => $valido['email'],
            'max_discount_percent' => 0,
            'max_return_amount' => 0,
        ]);
    }

    public function test_is_active_no_se_modifica_desde_creacion_ni_edicion(): void
    {
        $payload = $this->validUserPayload(['is_active' => false]);
        $this->actingAs($this->admin)->post('/users', $payload)->assertSessionHasErrors('is_active');

        $user = $this->makeUser(['global_role_id' => $this->role('cashier')->id, 'is_active' => true]);

        $update = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $update)
            ->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $user->fresh()->is_active);
    }

    public function test_soft_delete_excluye_listado_reserva_email_e_impide_login(): void
    {
        $user = $this->makeUser([
            'email' => 'eliminado@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/users/{$user->id}")
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertFalse((bool) User::withTrashed()->findOrFail($user->id)->is_active);

        $this->actingAs($this->admin)
            ->get('/users')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('users.data', fn ($rows): bool => collect($rows)->every(fn ($row): bool => $row['id'] !== $user->id))
            );

        $reuse = $this->validUserPayload(['email' => 'eliminado@prueba.cl']);
        $this->actingAs($this->admin)->post('/users', $reuse)->assertSessionHasErrors('email');

        $this->actingAsGuest()->post('/login', [
            'email' => 'eliminado@prueba.cl',
            'password' => 'ContrasenaSegura123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_listado_y_ficha_no_exponen_contrasena_ni_pin(): void
    {
        $user = $this->makeUser([
            'email' => 'privado@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'pin_hash' => Hash::make('1234'),
        ]);

        $list = $this->actingAs($this->admin)->get('/users');
        $ficha = $this->actingAs($this->admin)->get("/users/{$user->id}/edit");

        foreach ([$list, $ficha] as $response) {
            $content = (string) $response->getContent();
            $this->assertStringNotContainsString('pin_hash', $content);
            $this->assertStringNotContainsString('password', $content);
            $this->assertStringNotContainsString($user->password, $content);
        }
    }
}
