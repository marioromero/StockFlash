<?php

namespace Tests\Feature\Sellers;

use App\Models\Seller;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Auditoría atómica sin PII y rollback de vendedor/pivote (CORE-10 · PRV-06).
 */
class SellerAuditTest extends SellerTestCase
{
    public function test_creacion_audita_solo_id_estado_sucursales_y_campos(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser([
            'name' => 'Usuario Secreto',
            'email' => 'usuario-secreto@example.test',
            'global_role_id' => $this->adminRole->id,
        ]);

        $seller = $this->createSeller($this->admin, [$branch->id], [
            'code' => 'AUD-1',
            'name' => 'Nombre Secreto',
            'user_id' => $user->id,
            'commission_percent' => '7.5',
        ]);

        $audit = DB::table('audit_logs')
            ->where('event', 'sellers.created')
            ->where('auditable_id', $seller->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('sellers', $audit->module_code);
        $this->assertSame('sellers', $audit->auditable_type);
        $this->assertNull($audit->branch_id);

        $payload = (string) $audit->new_values;

        foreach ([
            'AUD-1',
            'Nombre Secreto',
            'usuario-secreto@example.test',
            'Usuario Secreto',
            '7.50',
            '7.5',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payload);
        }

        $this->assertStringContainsString('"id":'.$seller->id, $payload);
        $this->assertStringContainsString('"is_active":true', $payload);
        $this->assertStringContainsString('"branch_ids":['.$branch->id.']', $payload);
        $this->assertStringContainsString('"fields"', $payload);
    }

    public function test_edicion_y_sucursales_auditan_nombres_de_campos_y_ids(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branchA->id], [
            'code' => 'AUD-2',
            'name' => 'Original',
        ]);

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => 'Nombre Nuevo',
                'commission_percent' => '33.33',
                'branches' => [$branchA->id, $branchB->id],
            ])
            ->assertRedirect();

        $updated = DB::table('audit_logs')
            ->where('event', 'sellers.updated')
            ->where('auditable_id', $seller->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($updated);
        $payload = (string) $updated->new_values;
        $this->assertStringNotContainsString('Nombre Nuevo', $payload);
        $this->assertStringNotContainsString('33.33', $payload);
        $this->assertStringContainsString('"fields"', $payload);
        $this->assertStringContainsString('name', $payload);
        $this->assertStringContainsString('commission_percent', $payload);
        $this->assertStringContainsString('branches', $payload);
        $this->assertStringContainsString((string) $branchA->id, $payload);
        $this->assertStringContainsString((string) $branchB->id, $payload);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sellers.branches_updated',
            'auditable_id' => $seller->id,
        ]);
    }

    public function test_activar_y_desactivar_quedan_auditados(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'AUD-3']);

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sellers.deactivated',
            'auditable_id' => $seller->id,
            'module_code' => 'sellers',
        ]);

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/activate")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sellers.activated',
            'auditable_id' => $seller->id,
            'module_code' => 'sellers',
        ]);

        $deactivated = DB::table('audit_logs')
            ->where('event', 'sellers.deactivated')
            ->where('auditable_id', $seller->id)
            ->first();

        $this->assertStringContainsString('"is_active":false', (string) $deactivated->new_values);
    }

    public function test_fallo_de_auditoria_revierte_creacion_y_pivote(): void
    {
        $branch = $this->makeBranch();

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post('/sellers', $this->sellerPayload([
                    'code' => 'AUD-ROLLBACK',
                    'branches' => [$branch->id],
                ]));
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría caída.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('sellers', ['code' => 'AUD-ROLLBACK']);
        $this->assertSame(0, DB::table('branch_seller')->count());
    }

    public function test_fallo_de_auditoria_revierte_edicion_sucursales_y_estado(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branchA->id], [
            'code' => 'AUD-ROLLBACK-2',
            'name' => 'Original',
        ]);

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->patch("/sellers/{$seller->id}", [
                    'name' => 'No debe persistir',
                    'branches' => [$branchB->id],
                ]);
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $seller->refresh();
        $this->assertSame('Original', $seller->name);
        $this->assertSame([$branchA->id], DB::table('branch_seller')
            ->where('seller_id', $seller->id)
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id)
            ->all());

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/sellers/{$seller->id}/deactivate");
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertTrue($seller->refresh()->is_active);
    }

    public function test_la_auditoria_no_copia_pii_del_usuario_vinculado(): void
    {
        $branch = $this->makeBranch();
        $user = $this->makeUser([
            'name' => 'PII Nombre',
            'email' => 'pii-secreto@example.test',
            'global_role_id' => $this->adminRole->id,
        ]);

        $seller = $this->createSeller($this->admin, [$branch->id], [
            'code' => 'AUD-PII',
            'user_id' => $user->id,
        ]);

        $payload = (string) DB::table('audit_logs')
            ->where('event', 'sellers.created')
            ->where('auditable_id', $seller->id)
            ->value('new_values');

        $this->assertStringNotContainsString('PII Nombre', $payload);
        $this->assertStringNotContainsString('pii-secreto@example.test', $payload);
        $this->assertStringNotContainsString('AUD-PII', $payload);

        $this->assertSame($user->id, Seller::query()->findOrFail($seller->id)->user_id);
    }
}
