<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Support\UserAccessManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserLastAdminTest extends UserTestCase
{
    public function test_el_servicio_rechaza_desactivar_al_unico_administrador_activo(): void
    {
        $otro = $this->makeUser(['global_role_id' => $this->role('cashier')->id]);
        $manager = app(UserAccessManager::class);

        $this->expectException(ValidationException::class);

        DB::transaction(function () use ($manager, $otro): void {
            $manager->assertCanDeactivateOrDelete($this->admin, $otro);
        });
    }

    public function test_el_servicio_permite_desactivar_cuando_otro_administrador_activo_permanece(): void
    {
        $otroAdmin = $this->makeUser([
            'email' => 'otro.admin@prueba.cl',
            'global_role_id' => $this->role('central_admin')->id,
        ]);
        $actor = $this->makeUser(['global_role_id' => $this->role('cashier')->id]);

        app(UserAccessManager::class)->assertCanDeactivateOrDelete($otroAdmin, $actor);

        $this->assertTrue(true);
    }

    public function test_auto_cambio_de_rol_global_se_rechaza_si_no_queda_otro_administrador(): void
    {
        $payload = $this->validUserPayload([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$this->admin->id}", $payload)
            ->assertSessionHasErrors('global_role_id');

        $this->assertSame(
            $this->role('central_admin')->id,
            $this->admin->fresh()->global_role_id
        );
    }

    public function test_auto_cambio_de_rol_global_se_permite_si_otro_administrador_activo_permanece(): void
    {
        $this->makeUser([
            'email' => 'segundo.admin@prueba.cl',
            'global_role_id' => $this->role('central_admin')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$this->admin->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertSame(
            $this->role('cashier')->id,
            $this->admin->fresh()->global_role_id
        );
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role_changed',
            'auditable_id' => $this->admin->id,
            'is_security_event' => 1,
        ]);
    }

    public function test_auto_conversion_a_usuario_por_sucursal_se_rechaza_si_no_queda_administrador(): void
    {
        $branch = $this->makeBranch();

        $payload = $this->validUserPayload([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => null,
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('supervisor')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$this->admin->id}", $payload)
            ->assertSessionHasErrors('access');

        $this->assertSame(
            $this->role('central_admin')->id,
            $this->admin->fresh()->global_role_id
        );
        $this->assertDatabaseMissing('branch_user', ['user_id' => $this->admin->id]);
    }

    public function test_auto_conversion_a_usuario_por_sucursal_se_permite_con_otro_administrador(): void
    {
        $this->makeUser([
            'email' => 'respaldo.admin@prueba.cl',
            'global_role_id' => $this->role('central_admin')->id,
        ]);
        $branch = $this->makeBranch();

        $payload = $this->validUserPayload([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => null,
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('supervisor')->id],
            ],
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$this->admin->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertNull($this->admin->fresh()->global_role_id);
        $this->assertDatabaseHas('branch_user', [
            'user_id' => $this->admin->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('supervisor')->id,
        ]);
    }

    public function test_cambio_de_rol_de_otro_admin_queda_permitido_porque_el_actor_sigue_siendo_admin(): void
    {
        $target = $this->makeUser([
            'email' => 'degradar@prueba.cl',
            'global_role_id' => $this->role('central_admin')->id,
        ]);

        $payload = $this->validUserPayload([
            'name' => $target->name,
            'email' => $target->email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/users/{$target->id}", $payload)
            ->assertRedirect(route('users.index'));

        $this->assertSame($this->role('cashier')->id, $target->fresh()->global_role_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.access_revoked',
            'auditable_id' => $target->id,
        ]);
    }

    public function test_error_de_cambio_de_asignaciones_usa_la_clave_neutra_access(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-ACCESS']);
        $supervisor = $this->role('supervisor');
        $permissionId = DB::table('permissions')->where('code', 'users.manage')->value('id');

        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $supervisor->id,
            'permission_id' => $permissionId,
        ]);

        $target = $this->makeUser([
            'email' => 'access.key@prueba.cl',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $target->id,
            'branch_id' => $branch->id,
            'role_id' => $supervisor->id,
        ]);

        // Deja al objetivo como ?nico usuario activo con users.manage.
        DB::table('users')->where('id', $this->admin->id)->update(['is_active' => 0]);

        $manager = app(UserAccessManager::class);

        try {
            DB::transaction(function () use ($manager, $target): void {
                $manager->assertAccessChangeKeepsAdmin($target, $target, false);
            });
            $this->fail('Se esperaba el rechazo por perder users.manage.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('access', $exception->errors());
            $this->assertArrayNotHasKey('global_role_id', $exception->errors());
        }
    }
}
