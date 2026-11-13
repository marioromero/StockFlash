<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserStateTransitionTest extends UserTestCase
{
    public function test_desactivar_usuario_impide_su_acceso_y_audita_evento_de_seguridad(): void
    {
        $user = $this->makeUser([
            'email' => 'desactivar@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post("/users/{$user->id}/deactivate")
            ->assertRedirect(route('users.index'));

        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.deactivated',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
        ]);

        $this->actingAsGuest()->post('/login', [
            'email' => 'desactivar@prueba.cl',
            'password' => 'ContrasenaSegura123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_activar_usuario_inactivo_lo_habilita_y_audita(): void
    {
        $user = $this->makeUser([
            'email' => 'activar@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->post("/users/{$user->id}/activate")
            ->assertRedirect(route('users.index'));

        $this->assertTrue((bool) $user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.activated',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
        ]);
    }

    public function test_desactivar_usuario_ya_inactivo_es_idempotente_y_no_audita(): void
    {
        $user = $this->makeUser(['global_role_id' => $this->role('cashier')->id, 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post("/users/{$user->id}/deactivate")
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'user.deactivated',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_no_puede_desactivarse_ni_eliminarse_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->post("/users/{$this->admin->id}/deactivate")
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->delete("/users/{$this->admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'is_active' => 1,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_eliminar_deja_inactivo_aplica_soft_delete_y_audita(): void
    {
        $user = $this->makeUser([
            'email' => 'borrar@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete("/users/{$user->id}")
            ->assertRedirect(route('users.index'));

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->is_active);
        $this->assertNotNull($row->deleted_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.deleted',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
        ]);
    }

    public function test_editar_un_usuario_inactivo_no_lo_activa(): void
    {
        $user = $this->makeUser([
            'email' => 'inactivo.editado@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => false,
        ]);

        $payload = $this->validUserPayload([
            'name' => 'Inactivo Editado',
            'email' => $user->email,
            'password' => null,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Inactivo Editado', $user->name);
        $this->assertFalse((bool) $user->is_active);
    }

    public function test_activar_usuario_ya_activo_es_idempotente(): void
    {
        $user = $this->makeUser([
            'email' => 'ya.activo@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post("/users/{$user->id}/activate")
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'user.activated',
            'auditable_id' => $user->id,
        ]);
    }
}
