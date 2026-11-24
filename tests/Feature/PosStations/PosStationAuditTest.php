<?php

namespace Tests\Feature\PosStations;

use App\Models\PosStation;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Auditoría atómica de estaciones POS: solo IDs, estado, nombres de campos y
 * flags no secretos. Un fallo de auditoría revierte la mutación.
 */
class PosStationAuditTest extends PosStationTestCase
{
    public function test_creacion_audita_solo_ids_y_nombres_de_campos(): void
    {
        $branch = $this->makeBranch();

        $station = $this->createStation($this->admin, [
            'code' => 'AUDIT-CREATE',
            'name' => 'Nombre Secreto Estación',
            'printer_profile' => 'thermal58',
            'branch_id' => $branch->id,
        ]);

        $audit = DB::table('audit_logs')
            ->where('event', 'pos_stations.created')
            ->where('auditable_id', $station->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('pos', $audit->module_code);
        $this->assertSame('pos_stations', $audit->auditable_type);
        $this->assertSame($branch->id, (int) $audit->branch_id);

        $payload = (string) $audit->new_values;

        $this->assertStringNotContainsString('AUDIT-CREATE', $payload);
        $this->assertStringNotContainsString('Nombre Secreto Estación', $payload);
        $this->assertStringContainsString('"id":'.$station->id, $payload);
        $this->assertStringContainsString('"branch_id":'.$branch->id, $payload);
        $this->assertStringContainsString('"fields"', $payload);
        $this->assertStringContainsString('code', $payload);
        $this->assertStringContainsString('name', $payload);
        $this->assertStringContainsString('printer_profile', $payload);
    }

    public function test_edicion_y_estado_auditan_campos_sin_valores_ni_token(): void
    {
        $branch = $this->makeBranch();
        $station = $this->createStation($this->admin, [
            'code' => 'AUDIT-UPDATE',
            'name' => 'Original',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => 'Nombre Nuevo Secreto',
                'printer_profile' => 'a4',
            ])
            ->assertRedirect();

        $updated = DB::table('audit_logs')
            ->where('event', 'pos_stations.updated')
            ->where('auditable_id', $station->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($updated);
        $payload = (string) $updated->new_values;

        $this->assertStringNotContainsString('Nombre Nuevo Secreto', $payload);
        $this->assertStringNotContainsString('"a4"', $payload);
        $this->assertStringContainsString('name', $payload);
        $this->assertStringContainsString('printer_profile', $payload);
        $this->assertStringContainsString('"token_invalidated":false', $payload);

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/deactivate")
            ->assertRedirect();

        $deactivated = DB::table('audit_logs')
            ->where('event', 'pos_stations.deactivated')
            ->where('auditable_id', $station->id)
            ->first();

        $this->assertNotNull($deactivated);
        $this->assertStringContainsString('"is_active":false', (string) $deactivated->new_values);

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/activate")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'pos_stations.activated',
            'auditable_id' => $station->id,
            'module_code' => 'pos',
        ]);
    }

    public function test_fallo_de_auditoria_revierte_creacion_edicion_y_estado(): void
    {
        $branch = $this->makeBranch();

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post('/settings/pos-stations', $this->stationPayload([
                    'code' => 'AUDIT-ROLLBACK-POS',
                    'branch_id' => $branch->id,
                ]));
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría caída.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('pos_stations', ['code' => 'AUDIT-ROLLBACK-POS']);

        $station = PosStation::create([
            'branch_id' => $branch->id,
            'code' => 'AUDIT-ROLLBACK-POS-2',
            'name' => 'Original',
            'is_active' => true,
        ]);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->patch("/settings/pos-stations/{$station->id}", ['name' => 'No debe persistir']);
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertSame('Original', $station->refresh()->name);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/settings/pos-stations/{$station->id}/deactivate");
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertTrue($station->refresh()->is_active);
    }
}
