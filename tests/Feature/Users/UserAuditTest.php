<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UserAuditTest extends UserTestCase
{
    public function test_crud_registra_los_eventos_de_usuario_esperados(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)->post('/users', $this->validUserPayload([
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('cashier')->id],
            ],
            'pin' => '1234',
        ]))->assertRedirect(route('users.index'));

        $user = User::query()->where('email', '!=', $this->admin->email)->latest('id')->firstOrFail();

        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->validUserPayload([
            'name' => 'Nombre Auditado',
            'email' => $user->email,
            'password' => 'OtraContrasenaTemporal456!',
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'assignments' => [
                ['branch_id' => $branch->id, 'role_id' => $this->role('supervisor')->id],
            ],
            'pin' => null,
        ]))->assertRedirect(route('users.index'));

        $this->actingAs($this->admin)->post("/users/{$user->id}/deactivate")
            ->assertRedirect(route('users.index'));
        $this->actingAs($this->admin)->post("/users/{$user->id}/activate")
            ->assertRedirect(route('users.index'));
        $this->actingAs($this->admin)->delete("/users/{$user->id}")
            ->assertRedirect(route('users.index'));

        $events = DB::table('audit_logs')->orderBy('id')->pluck('event')->all();

        foreach ([
            'user.created',
            'user.branch_assigned',
            'user.updated',
            'user.branch_unassigned',
            'user.password_reset',
            'user.deactivated',
            'user.activated',
            'user.deleted',
        ] as $expected) {
            $this->assertContains($expected, $events, "Falta el evento {$expected}");
        }

        foreach (DB::table('audit_logs')->whereIn('event', [
            'user.created', 'user.deactivated', 'user.activated', 'user.deleted', 'user.password_reset',
        ])->get() as $row) {
            $this->assertSame(1, (int) $row->is_security_event);
        }

        foreach (DB::table('audit_logs')->get() as $row) {
            $serialized = (string) $row->old_values.(string) $row->new_values;
            $this->assertStringNotContainsString('OtraContrasenaTemporal456!', $serialized);
            $this->assertStringNotContainsString('pin_hash', $serialized);
            $this->assertStringNotContainsString('$2y$', $serialized);
        }
    }

    public function test_cambio_de_pii_marca_evento_de_datos_personales(): void
    {
        $user = $this->makeUser([
            'email' => 'pii@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->validUserPayload([
            'name' => 'Nombre PII Nuevo',
            'email' => $user->email,
            'password' => null,
        ]))->assertRedirect(route('users.index'));

        $updated = DB::table('audit_logs')->where('event', 'user.updated')->firstOrFail();
        $this->assertSame(1, (int) $updated->is_personal_data_access);
    }

    public function test_cambio_solo_de_rol_no_marca_datos_personales(): void
    {
        $user = $this->makeUser([
            'email' => 'solo.rol@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->validUserPayload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'global_role_id' => $this->role('supervisor')->id,
        ]))->assertRedirect(route('users.index'));

        $updated = DB::table('audit_logs')->where('event', 'user.updated')->firstOrFail();
        $this->assertSame(0, (int) $updated->is_personal_data_access);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role_changed',
            'auditable_id' => $user->id,
            'is_security_event' => 1,
        ]);
    }

    public function test_revocar_administracion_registra_access_revoked(): void
    {
        $target = $this->makeUser([
            'email' => 'admin.revocado@prueba.cl',
            'global_role_id' => $this->role('central_admin')->id,
        ]);

        $this->actingAs($this->admin)->put("/users/{$target->id}", $this->validUserPayload([
            'name' => $target->name,
            'email' => $target->email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
        ]))->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.access_revoked',
            'auditable_id' => $target->id,
            'is_security_event' => 1,
        ]);
    }

    public function test_lectura_de_listado_registra_un_unico_evento_antes_de_responder(): void
    {
        $this->makeUser(['email' => 'lectura@prueba.cl', 'global_role_id' => $this->role('cashier')->id]);

        $this->actingAs($this->admin)->get('/users')->assertOk();

        $rows = DB::table('audit_logs')->where('event', 'user.list_viewed')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->is_personal_data_access);
        $this->assertSame(0, (int) $rows[0]->is_security_event);
        $this->assertNull($rows[0]->auditable_id);
        $this->assertSame($this->admin->id, (int) $rows[0]->user_id);
        $this->assertNotNull($rows[0]->ip_address);
        $this->assertNull($rows[0]->old_values);
        $this->assertNull($rows[0]->new_values);
    }

    public function test_lectura_de_ficha_registra_solo_el_id_consultado(): void
    {
        $user = $this->makeUser([
            'name' => 'Persona Secreta',
            'email' => 'ficha.secreta@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)->get("/users/{$user->id}/edit")->assertOk();

        $row = DB::table('audit_logs')->where('event', 'user.record_viewed')->firstOrFail();
        $this->assertSame(1, (int) $row->is_personal_data_access);
        $this->assertSame($user->id, (int) $row->auditable_id);
        $this->assertSame('users', $row->auditable_type);
        $this->assertNull($row->old_values);
        $this->assertNull($row->new_values);
        $this->assertStringNotContainsString('Persona Secreta', (string) $row->new_values);
        $this->assertStringNotContainsString('ficha.secreta@prueba.cl', (string) $row->new_values);
    }

    public function test_fallo_del_log_de_lectura_impide_entregar_datos(): void
    {
        $this->bindFailingReadLogger();

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->get('/users');
            $this->fail('Se esperaba la excepción del registro de lectura.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría de lectura simulado', $exception->getMessage());
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_del_log_de_lectura_de_ficha_impide_entregar_datos(): void
    {
        $user = $this->makeUser(['global_role_id' => $this->role('cashier')->id]);
        $this->bindFailingReadLogger();

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->get("/users/{$user->id}/edit");
            $this->fail('Se esperaba la excepción del registro de lectura.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría de lectura simulado', $exception->getMessage());
        }
    }

    public function test_fallo_de_auditoria_revierte_la_creacion_completa(): void
    {
        $this->bindFailingWriteLogger();
        $payload = $this->validUserPayload();

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->post('/users', $payload);
            $this->fail('Se esperaba la excepción de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_la_edicion_completa(): void
    {
        $user = $this->makeUser([
            'name' => 'Nombre Original',
            'email' => 'rollback.update@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
        ]);
        $this->bindFailingWriteLogger();

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->put("/users/{$user->id}", $this->validUserPayload([
                'name' => 'Nombre No Persistido',
                'email' => $user->email,
                'password' => null,
            ]));
            $this->fail('Se esperaba la excepción de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertSame('Nombre Original', $user->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_la_desactivacion_completa(): void
    {
        $user = $this->makeUser([
            'email' => 'rollback.deactivate@prueba.cl',
            'global_role_id' => $this->role('cashier')->id,
            'is_active' => true,
        ]);
        $this->bindFailingWriteLogger();

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->post("/users/{$user->id}/deactivate");
            $this->fail('Se esperaba la excepción de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertTrue((bool) $user->fresh()->is_active);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_creacion_edicion_y_baja_no_persisten_valores_de_nombre_ni_email(): void
    {
        $originalName = 'Nombre R1 Original '.Str::random(12);
        $originalEmail = 'r1.original.'.Str::lower(Str::random(12)).'@example.test';
        $updatedName = 'Nombre R1 Editado '.Str::random(12);
        $updatedEmail = 'r1.editado.'.Str::lower(Str::random(12)).'@example.test';

        $this->actingAs($this->admin)->post('/users', $this->validUserPayload([
            'name' => $originalName,
            'email' => $originalEmail,
        ]))->assertRedirect(route('users.index'));

        $user = User::query()->where('email', $originalEmail)->firstOrFail();
        $userId = (int) $user->id;

        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->validUserPayload([
            'name' => $updatedName,
            'email' => $updatedEmail,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
        ]))->assertRedirect(route('users.index'));

        $this->actingAs($this->admin)->delete("/users/{$user->id}")
            ->assertRedirect(route('users.index'));

        // Ninguna fila de auditoría puede contener los valores PII, ni antiguos
        // ni nuevos, en old_values ni en new_values.
        foreach (DB::table('audit_logs')->get() as $row) {
            foreach ([$originalName, $originalEmail, $updatedName, $updatedEmail] as $piiValue) {
                $this->assertStringNotContainsString($piiValue, (string) $row->old_values);
                $this->assertStringNotContainsString($piiValue, (string) $row->new_values);
            }
        }

        $created = DB::table('audit_logs')->where('event', 'user.created')->firstOrFail();
        $updated = DB::table('audit_logs')->where('event', 'user.updated')->firstOrFail();
        $deleted = DB::table('audit_logs')->where('event', 'user.deleted')->firstOrFail();

        $this->assertSame(1, (int) $created->is_personal_data_access);
        $this->assertSame(1, (int) $created->is_security_event);
        $this->assertSame(1, (int) $updated->is_personal_data_access);
        $this->assertSame(0, (int) $updated->is_security_event);

        // Trazabilidad no PII conservada: actor, entidad, ID y fecha.
        foreach ([$created, $updated, $deleted] as $row) {
            $this->assertSame((int) $this->admin->id, (int) $row->user_id);
            $this->assertSame('users', $row->auditable_type);
            $this->assertSame($userId, (int) $row->auditable_id);
            $this->assertNotNull($row->created_at);
        }

        // Solo se registran los nombres de los campos afectados.
        $this->assertStringContainsString('personal_data_fields', (string) $created->new_values);
        $this->assertStringContainsString('personal_data_fields', (string) $updated->new_values);
        $this->assertStringContainsString('name', (string) $updated->new_values);
        $this->assertStringContainsString('email', (string) $updated->new_values);
        $this->assertStringContainsString('personal_data_fields', (string) $deleted->new_values);
        $this->assertStringNotContainsString('name', (string) $deleted->old_values);
        $this->assertStringNotContainsString('email', (string) $deleted->old_values);
    }

    public function test_edicion_sin_cambio_de_pii_mantiene_flag_en_cero(): void
    {
        $name = 'Nombre Estable '.Str::random(10);
        $email = 'estable.'.Str::lower(Str::random(10)).'@example.test';

        $user = $this->makeUser([
            'name' => $name,
            'email' => $email,
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->validUserPayload([
            'name' => $name,
            'email' => $email,
            'password' => null,
            'global_role_id' => $this->role('cashier')->id,
        ]))->assertRedirect(route('users.index'));

        $updated = DB::table('audit_logs')->where('event', 'user.updated')->firstOrFail();

        $this->assertSame(0, (int) $updated->is_personal_data_access);
        $this->assertSame(0, (int) $updated->is_security_event);
        $this->assertStringNotContainsString('personal_data_fields', (string) $updated->new_values);
        $this->assertStringNotContainsString($name, (string) $updated->new_values);
        $this->assertStringNotContainsString($email, (string) $updated->new_values);
        $this->assertStringNotContainsString($name, (string) $updated->old_values);
        $this->assertStringNotContainsString($email, (string) $updated->old_values);
    }

    private function bindFailingReadLogger(): void
    {
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function recordRead(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                string $moduleCode = 'users',
            ): void {
                throw new RuntimeException('Fallo de auditoría de lectura simulado');
            }
        });
    }

    private function bindFailingWriteLogger(): void
    {
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function record(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                ?int $branchId = null,
                ?array $old = null,
                ?array $new = null,
                string $moduleCode = 'core',
                bool $isSecurityEvent = false,
                bool $isPersonalDataAccess = false,
            ): void {
                throw new RuntimeException('Fallo de auditoría simulado');
            }
        });
    }
}
