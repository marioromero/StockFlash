<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BranchAuditTest extends BranchTestCase
{
    public function test_crud_registra_los_eventos_de_auditoria_esperados(): void
    {
        $this->actingAs($this->admin)
            ->post('/branches', $this->validPayload([
                'code' => 'SUC-AUD',
                'name' => 'Sucursal Auditoría',
                'settings' => '{"canal": "mostrador"}',
            ]))
            ->assertRedirect(route('branches.index'));

        $branch = Branch::query()->where('code', 'SUC-AUD')->firstOrFail();

        $updatePayload = $this->validPayload([
            'code' => 'SUC-AUD',
            'name' => 'Sucursal Auditoría Editada',
        ]);
        unset($updatePayload['is_active']);

        $this->actingAs($this->admin)
            ->put("/branches/{$branch->id}", $updatePayload)
            ->assertRedirect(route('branches.index'));

        $this->actingAs($this->admin)
            ->post("/branches/{$branch->id}/deactivate")
            ->assertRedirect(route('branches.index'));

        $this->actingAs($this->admin)
            ->post("/branches/{$branch->id}/activate")
            ->assertRedirect(route('branches.index'));

        $this->actingAs($this->admin)
            ->delete("/branches/{$branch->id}")
            ->assertRedirect(route('branches.index'));

        $rows = DB::table('audit_logs')->orderBy('id')->get();

        $this->assertSame(
            ['branch.created', 'branch.updated', 'branch.deactivated', 'branch.activated', 'branch.deleted'],
            $rows->pluck('event')->all()
        );

        foreach ($rows as $row) {
            $this->assertSame('core', $row->module_code);
            $this->assertSame('branches', $row->auditable_type);
            $this->assertSame($branch->id, (int) $row->auditable_id);
            $this->assertSame($this->admin->id, (int) $row->user_id);
            $this->assertNotNull($row->ip_address);
            $this->assertNotNull($row->created_at);
        }

        $created = json_decode((string) $rows[0]->new_values, true);
        $updatedOld = json_decode((string) $rows[1]->old_values, true);
        $updatedNew = json_decode((string) $rows[1]->new_values, true);

        $this->assertSame('Sucursal Auditoría', $created['name']);
        $this->assertSame('Sucursal Auditoría', $updatedOld['name']);
        $this->assertSame('Sucursal Auditoría Editada', $updatedNew['name']);
    }

    public function test_la_auditoria_filtra_claves_sensibles(): void
    {
        $branch = $this->makeBranch([
            'code' => 'SUC-SEC',
            'settings' => [
                'canal' => 'mostrador',
                'password' => 'no-debe-registrarse',
                'api_token' => 'tampoco',
            ],
        ]);

        $payload = $this->validPayload([
            'code' => 'SUC-SEC',
            'name' => 'Sucursal Segura',
        ]);
        unset($payload['settings'], $payload['is_active']);

        $this->actingAs($this->admin)
            ->put("/branches/{$branch->id}", $payload)
            ->assertRedirect(route('branches.index'));

        $row = DB::table('audit_logs')->where('event', 'branch.updated')->firstOrFail();
        $old = json_decode((string) $row->old_values, true);
        $new = json_decode((string) $row->new_values, true);

        $this->assertArrayNotHasKey('password', $old['settings']);
        $this->assertArrayNotHasKey('api_token', $old['settings']);
        $this->assertArrayNotHasKey('password', $new['settings']);
        $this->assertArrayNotHasKey('api_token', $new['settings']);
        $this->assertSame('mostrador', $old['settings']['canal']);
    }

    public function test_la_auditoria_filtra_claves_camel_case_concatenadas_y_anidadas(): void
    {
        $branch = $this->makeBranch([
            'code' => 'SUC-SEC-CAMEL',
            'settings' => [
                'canal' => 'mostrador',
                'shipping_cost' => 1500,
                'mapping' => 'canal',
                'secretToken' => 'no-debe-registrarse',
                'authToken' => 'no-debe-registrarse',
                'clientSecret' => 'no-debe-registrarse',
                'apiKey' => 'no-debe-registrarse',
                'privateKey' => 'no-debe-registrarse',
                'creditCard' => '4111111111111111',
                'integration' => [
                    'clientSecret' => 'no-debe-registrarse',
                    'webhook' => 'https://ejemplo.test/hook',
                ],
            ],
        ]);

        $payload = $this->validPayload([
            'code' => 'SUC-SEC-CAMEL',
            'name' => 'Sucursal Segura Camel',
        ]);
        unset($payload['settings'], $payload['is_active']);

        $this->actingAs($this->admin)
            ->put("/branches/{$branch->id}", $payload)
            ->assertRedirect(route('branches.index'));

        $row = DB::table('audit_logs')->where('event', 'branch.updated')->firstOrFail();

        foreach ([$row->old_values, $row->new_values] as $serialized) {
            $values = json_decode((string) $serialized, true);
            $settings = $values['settings'];

            $this->assertStringNotContainsString('no-debe-registrarse', (string) $serialized);
            $this->assertStringNotContainsString('clientSecret', (string) $serialized);

            foreach ([
                'secretToken', 'authToken', 'clientSecret',
                'apiKey', 'privateKey', 'creditCard',
            ] as $key) {
                $this->assertArrayNotHasKey($key, $settings);
            }

            $this->assertArrayNotHasKey('clientSecret', $settings['integration']);
            $this->assertSame('canal', $settings['mapping']);
            $this->assertSame(1500, $settings['shipping_cost']);
            $this->assertSame('https://ejemplo.test/hook', $settings['integration']['webhook']);
        }
    }

    public function test_fallo_de_auditoria_revierte_la_creacion_completa(): void
    {
        $this->bindFailingAuditLogger();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post('/branches', $this->validPayload(['code' => 'SUC-RB-C']));
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertDatabaseMissing('branches', ['code' => 'SUC-RB-C']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_la_edicion_completa(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-RB-U', 'name' => 'Nombre Original']);
        $this->bindFailingAuditLogger();

        $payload = $this->validPayload([
            'code' => 'SUC-RB-U',
            'name' => 'Nombre No Persistido',
        ]);
        unset($payload['is_active']);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->put("/branches/{$branch->id}", $payload);
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $branch->refresh();
        $this->assertSame('Nombre Original', $branch->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_la_desactivacion_completa(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-RB-D', 'is_active' => true]);
        $this->bindFailingAuditLogger();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/branches/{$branch->id}/deactivate");
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $branch->refresh();
        $this->assertTrue($branch->is_active);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_la_activacion_completa(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-RB-A', 'is_active' => false]);
        $this->bindFailingAuditLogger();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/branches/{$branch->id}/activate");
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $branch->refresh();
        $this->assertFalse($branch->is_active);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_la_eliminacion_logica(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-RB-X']);
        $this->bindFailingAuditLogger();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->delete("/branches/{$branch->id}");
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertNotSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_editar_sucursal_activa_mantiene_el_estado_y_audita_updated(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-EST', 'is_active' => true]);

        $payload = $this->validPayload(['code' => 'SUC-EST', 'name' => 'Nombre Editado']);
        unset($payload['is_active']);

        $this->actingAs($this->admin)
            ->put("/branches/{$branch->id}", $payload)
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 1]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.updated', 'auditable_id' => $branch->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'branch.deactivated', 'auditable_id' => $branch->id]);
    }

    public function test_desactivar_solo_por_accion_dedicada_audita_deactivated(): void
    {
        $branch = $this->makeBranch(['code' => 'SUC-DES', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post("/branches/{$branch->id}/deactivate")
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => 0]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.deactivated', 'auditable_id' => $branch->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'branch.updated', 'auditable_id' => $branch->id]);
    }

    private function bindFailingAuditLogger(): void
    {
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function log(
                string $event,
                Branch $branch,
                ?array $old = null,
                ?array $new = null,
                string $moduleCode = 'core',
            ): void {
                throw new RuntimeException('Fallo de auditoría simulado');
            }
        });
    }
}
