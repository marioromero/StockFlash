<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserLimitTest extends UserTestCase
{
    public function test_sin_fila_de_licencia_se_bloquea_crear_usuarios(): void
    {
        $this->deleteLicense();
        $payload = $this->validUserPayload();

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertSessionHasErrors('license');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_sin_fila_de_licencia_se_bloquea_activar_usuarios(): void
    {
        $user = $this->makeUser([
            'email' => 'activar.sin.licencia@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => false,
        ]);
        $this->deleteLicense();

        $this->actingAs($this->admin)
            ->post("/users/{$user->id}/activate")
            ->assertSessionHasErrors('license');

        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_limite_de_usuarios_activos_rechaza_crear_sin_estado_parcial(): void
    {
        $this->setMaxUsers(2);
        $this->makeUser(['email' => 'activo.1@prueba.cl', 'global_role_id' => $this->role('cashier')->id]);

        $payload = $this->validUserPayload();

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertSessionHasErrors('max_users');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertSame(2, User::query()->where('is_active', 1)->count());
    }

    public function test_limite_de_usuarios_activos_rechaza_activar_sin_estado_parcial(): void
    {
        $this->setMaxUsers(2);
        $this->makeUser(['email' => 'activo.2@prueba.cl', 'global_role_id' => $this->role('cashier')->id]);
        $inactivo = $this->makeUser([
            'email' => 'inactivo.limite@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->post("/users/{$inactivo->id}/activate")
            ->assertSessionHasErrors('max_users');

        $this->assertFalse((bool) $inactivo->fresh()->is_active);

        // Al liberar un cupo, la activación es aceptada.
        $activo = User::query()->where('email', 'activo.2@prueba.cl')->firstOrFail();
        $this->actingAs($this->admin)
            ->post("/users/{$activo->id}/deactivate")
            ->assertRedirect(route('users.index'));

        $this->actingAs($this->admin)
            ->post("/users/{$inactivo->id}/activate")
            ->assertRedirect(route('users.index'));

        $this->assertTrue((bool) $inactivo->fresh()->is_active);
    }

    public function test_usuarios_inactivos_no_consumen_cupo(): void
    {
        $this->setMaxUsers(2);

        for ($i = 1; $i <= 5; $i++) {
            $this->makeUser([
                'email' => "inactivo{$i}@prueba.cl",
                'global_role_id' => $this->role('cashier')->id,
                'is_active' => false,
            ]);
        }

        $payload = $this->validUserPayload();
        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'is_active' => 1]);
    }

    public function test_max_users_cero_con_licencia_registrada_significa_ilimitado(): void
    {
        $this->setMaxUsers(0);

        for ($i = 1; $i <= 4; $i++) {
            $this->actingAs($this->admin)
                ->post('/users', $this->validUserPayload([
                    'email' => "ilimitado{$i}@prueba.cl",
                ]))
                ->assertRedirect(route('users.index'));
        }

        $this->assertSame(5, User::query()->where('is_active', 1)->count());
    }

    public function test_licencia_suspendida_fuera_de_gracia_bloquea_altas(): void
    {
        $this->suspendLicense(now()->subDay()->toDateString());

        $payload = $this->validUserPayload();
        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertSessionHasErrors('license');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_licencia_suspendida_fuera_de_gracia_bloquea_activaciones(): void
    {
        $user = $this->makeUser([
            'email' => 'suspension.activar@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => false,
        ]);
        $this->suspendLicense(now()->subDay()->toDateString());

        $this->actingAs($this->admin)
            ->post("/users/{$user->id}/activate")
            ->assertSessionHasErrors('license');

        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_licencia_suspendida_dentro_de_gracia_permite_altas(): void
    {
        $this->suspendLicense(now()->addDay()->toDateString());

        $payload = $this->validUserPayload();
        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => $payload['email']]);
    }

    public function test_consultar_usuarios_sigue_permitido_sin_licencia(): void
    {
        $this->deleteLicense();

        $this->actingAs($this->admin)->get('/users')->assertOk();
    }

    public function test_cuenta_eliminada_no_consume_cupo(): void
    {
        $this->setMaxUsers(2);
        $eliminado = $this->makeUser([
            'email' => 'cupo.eliminado@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);
        $this->actingAs($this->admin)->delete("/users/{$eliminado->id}")
            ->assertRedirect(route('users.index'));

        $payload = $this->validUserPayload();
        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertRedirect(route('users.index'));

        $this->assertSame(
            2,
            DB::table('users')->where('is_active', 1)->whereNull('deleted_at')->count()
        );
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'is_active' => 1]);
    }
}
