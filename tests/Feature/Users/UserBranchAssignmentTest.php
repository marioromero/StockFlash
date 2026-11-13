<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserBranchAssignmentTest extends UserTestCase
{
    public function test_usuario_global_no_acepta_asignaciones_por_sucursal(): void
    {
        $branch = $this->makeBranch();

        $payload = $this->validUserPayload([
            'global_role_id' => $this->role('cashier')->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('seller')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertSessionHasErrors('assignments');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_central_admin_solo_puede_ser_rol_global_no_por_sucursal(): void
    {
        $branch = $this->makeBranch();

        $payload = $this->validUserPayload([
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('central_admin')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertSessionHasErrors('assignments.0.role_id');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_crea_usuario_por_sucursal_con_filas_unicas_y_sucursal_inicial(): void
    {
        $branchA = $this->makeBranch(['code' => 'SUC-AS-A']);
        $branchB = $this->makeBranch(['code' => 'SUC-AS-B']);

        $payload = $this->validUserPayload([
            'global_role_id' => null,
            'default_branch_id' => $branchA->id,
            'assignments' => [
                ['branch_id' => $branchA->id, 'role_id' => $this->role('cashier')->id],
                ['branch_id' => $branchB->id, 'role_id' => $this->role('warehouse_keeper')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertNull($user->global_role_id);
        $this->assertSame($branchA->id, $user->default_branch_id);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branchA->id,
            'role_id' => $this->role('cashier')->id,
        ]);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branchB->id,
            'role_id' => $this->role('warehouse_keeper')->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.branch_assigned',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
        ]);
    }

    public function test_rechaza_sucursales_duplicadas_inexistentes_o_inactivas(): void
    {
        $branch = $this->makeBranch();
        $inactiva = $this->makeBranch(['is_active' => false]);

        $duplicada = $this->validUserPayload([
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('seller')->id],
                ['branch_id' => $branch->id, 'role_id' => $this->role('cashier')->id],
            ],
        ]);
        $this->actingAs($this->admin)->post('/users', $duplicada)
            ->assertSessionHasErrors('assignments.0.branch_id');

        $inexistente = $this->validUserPayload([
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => 999999, 'role_id' => $this->role('seller')->id],
            ],
        ]);
        $this->actingAs($this->admin)->post('/users', $inexistente)
            ->assertSessionHasErrors('assignments.0.branch_id');

        $inactivaPayload = $this->validUserPayload([
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => $inactiva->id, 'role_id' => $this->role('seller')->id],
            ],
        ]);
        $this->actingAs($this->admin)->post('/users', $inactivaPayload)
            ->assertSessionHasErrors('assignments.0.branch_id');

        $this->assertDatabaseMissing('users', ['email' => $duplicada['email']]);
        $this->assertDatabaseMissing('users', ['email' => $inexistente['email']]);
        $this->assertDatabaseMissing('users', ['email' => $inactivaPayload['email']]);
    }

    public function test_permite_guardar_usuario_por_sucursal_sin_asignaciones_con_advertencia(): void
    {
        $payload = $this->validUserPayload([
            'global_role_id' => null,
            'assignments' => [],
        ]);

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('warning');

        $user = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertNull($user->global_role_id);
        $this->assertDatabaseCount('branch_user', 0);
    }

    public function test_sucursal_inicial_debe_estar_asignada_para_usuario_por_sucursal(): void
    {
        $asignada = $this->makeBranch(['code' => 'SUC-INI-A']);
        $otra = $this->makeBranch(['code' => 'SUC-INI-B']);

        $payload = $this->validUserPayload([
            'global_role_id' => null,
            'default_branch_id' => $otra->id,
            'assignments' => [
                ['branch_id' => $asignada->id, 'role_id' => $this->role('seller')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post('/users', $payload)
            ->assertSessionHasErrors('default_branch_id');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_conversion_de_global_a_sucursal_limpia_rol_global_y_crea_asignaciones(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser([
            'email' => 'convertir@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('seller')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertNull($user->global_role_id);
        $this->assertSame($branch->id, $user->default_branch_id);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('seller')->id,
        ]);
    }

    public function test_conversion_de_sucursal_a_global_elimina_asignaciones(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser([
            'email' => 'globalizar@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
            'default_branch_id' => $branch->id,
            'assignments' => [],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame($this->role('cashier')->id, $user->global_role_id);
        $this->assertDatabaseMissing('branch_user', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.branch_unassigned',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_reemplazo_de_asignaciones_audita_asignado_y_desasignado(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser([
            'email' => 'reasignar@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('supervisor')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('supervisor')->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.branch_unassigned',
            'auditable_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.branch_assigned',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_rechaza_edicion_sin_default_branch_id_si_la_sucursal_inicial_persistida_sale_de_las_asignaciones(): void
    {
        $branchA = $this->makeBranch(['code' => 'SUC-M1-A']);
        $branchB = $this->makeBranch(['code' => 'SUC-M1-B']);
        $user = $this->makeUser([
            'email' => 'menor1.a@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branchA->id,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $user->id,
            'branch_id' => $branchA->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => $branchB->id, 'role_id' => $this->role('seller')->id],
            ],
        ]);
        unset($payload['default_branch_id']);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertSessionHasErrors('default_branch_id');

        $user->refresh();
        $this->assertSame($branchA->id, $user->default_branch_id);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branchA->id,
            'role_id' => $this->role('cashier')->id,
        ]);
        $this->assertDatabaseMissing('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branchB->id,
        ]);
    }

    public function test_permite_edicion_sin_default_branch_id_si_la_sucursal_inicial_persistida_sigue_asignada(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-M1-C']);
        $user = $this->makeUser([
            'email' => 'menor1.b@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('supervisor')->id],
            ],
        ]);
        unset($payload['default_branch_id']);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertSame($branch->id, $user->fresh()->default_branch_id);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('supervisor')->id,
        ]);
    }

    public function test_default_branch_id_explicito_null_limpia_la_sucursal_inicial(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-M1-D']);
        $user = $this->makeUser([
            'email' => 'menor1.c@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => null,
            'default_branch_id' => null,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('cashier')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->default_branch_id);
    }

    public function test_conversion_global_a_sucursal_sin_default_branch_id_revalida_la_sucursal_persistida(): void
    {
        $branchA = $this->makeBranch(['code' => 'SUC-M1-E']);
        $branchB = $this->makeBranch(['code' => 'SUC-M1-F']);
        $user = $this->makeUser([
            'email' => 'menor1.d@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'default_branch_id' => $branchA->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => null,
            'assignments' => [
                ['branch_id' => $branchB->id, 'role_id' => $this->role('seller')->id],
            ],
        ]);
        unset($payload['default_branch_id']);

        $this->actingAs($this->admin)
            ->put("/users/{$user->id}", $payload)
            ->assertSessionHasErrors('default_branch_id');

        $user->refresh();
        $this->assertSame($this->role('cashier')->id, $user->global_role_id);
        $this->assertSame($branchA->id, $user->default_branch_id);
        $this->assertDatabaseMissing('branch_user', ['user_id' => $user->id]);
    }
}
