<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Support\UserAccessFingerprint;

class UserSessionInvalidationTest extends UserTestCase
{
    public function test_sesion_sin_cambios_sigue_vigente(): void
    {
        $victim = $this->makeUser([
            'email' => 'vigente@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->login($victim);

        $this->get('/dashboard')->assertOk();
    }

    public function test_fingerprint_ausente_invalida_la_sesion(): void
    {
        $victim = $this->makeUser([
            'email' => 'sin.fingerprint@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->login($victim);
        $this->session([UserAccessFingerprint::SESSION_KEY => null]);

        $this->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_reset_de_contrasena_por_admin_invalida_sesiones_existentes(): void
    {
        $victim = $this->makeUser([
            'email' => 'reset.sesion@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $oldFingerprint = $this->login($victim);

        $payload = $this->validUserPayload([
            'name' => $victim->name,
            'email' => $victim->email,
            'password' => 'OtraContrasenaTemporal789!',
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$victim->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertNotSame(
            $oldFingerprint,
            UserAccessFingerprint::for($victim->fresh()),
            'El reset de contraseña debe cambiar el fingerprint.'
        );

        $this->restoreOldSession($victim, $oldFingerprint);

        $this->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_cambio_de_rol_global_invalida_sesiones_existentes(): void
    {
        $victim = $this->makeUser([
            'email' => 'rol.sesion@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $oldFingerprint = $this->login($victim);

        $payload = $this->validUserPayload([
            'name' => $victim->name,
            'email' => $victim->email,
            'password' => null,
            'global_role_id' => $this->role('supervisor')->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$victim->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->restoreOldSession($victim, $oldFingerprint);

        $this->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_cambio_de_asignaciones_por_sucursal_invalida_sesiones_existentes(): void
    {
        $branch = $this->makeBranch();
        $victim = $this->makeUser([
            'email' => 'asignacion.sesion@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        \App\Models\BranchUser::create([
            'user_id' => $victim->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $oldFingerprint = $this->login($victim);

        $payload = $this->validUserPayload([
            'name' => $victim->name,
            'email' => $victim->email,
            'password' => null,
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('supervisor')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$victim->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->restoreOldSession($victim, $oldFingerprint);

        $this->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_cambio_de_sucursal_inicial_invalida_sesiones_existentes(): void
    {
        $branchA = $this->makeBranch(['code' => 'SUC-INIT-A']);
        $branchB = $this->makeBranch(['code' => 'SUC-INIT-B']);
        $victim = $this->makeUser([
            'email' => 'inicial.sesion@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'default_branch_id' => $branchA->id,
        ]);

        $oldFingerprint = $this->login($victim);

        $payload = $this->validUserPayload([
            'name' => $victim->name,
            'email' => $victim->email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
            'default_branch_id' => $branchB->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$victim->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->restoreOldSession($victim, $oldFingerprint);

        $this->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_desactivacion_invalida_sesiones_existentes(): void
    {
        $victim = $this->makeUser([
            'email' => 'estado.sesion@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $oldFingerprint = $this->login($victim);

        $this->actingAs($this->admin)
            ->post("/users/{$victim->id}/deactivate")
            ->assertRedirect(route('users.index'));

        $this->restoreOldSession($victim, $oldFingerprint);

        $this->get('/dashboard')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_cambio_de_contrasena_propio_cierra_la_sesion(): void
    {
        $victim = $this->makeUser([
            'email' => 'propio.cambio@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->login($victim);

        $this->put('/password/change', [
            'current_password' => 'ContrasenaSegura123!',
            'password' => 'NuevaContrasenaPropia123!',
            'password_confirmation' => 'NuevaContrasenaPropia123!',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    private function login(User $user): string
    {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'ContrasenaSegura123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        return (string) $this->app['session']->get(UserAccessFingerprint::SESSION_KEY);
    }

    private function restoreOldSession(User $user, string $fingerprint): void
    {
        // Simula la siguiente petición real: el guard recarga el usuario desde
        // la base de datos, por lo que el fingerprint se recalcula con el
        // estado actualizado y debe diferir del guardado en la sesión antigua.
        $user->refresh();

        $this->actingAs($user);
        $this->session([UserAccessFingerprint::SESSION_KEY => $fingerprint]);
    }
}
