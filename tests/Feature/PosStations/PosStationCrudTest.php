<?php

namespace Tests\Feature\PosStations;

use App\Models\PosStation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * CRUD, código inmutable, perfiles de impresora, sucursal y campos forjados
 * (CSH-02).
 */
class PosStationCrudTest extends PosStationTestCase
{
    public function test_crear_normaliza_codigo_y_guarda_defaults_de_servidor(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)
            ->post('/settings/pos-stations', [
                'code' => '  pos.caja_1-2  ',
                'name' => '  Caja Principal  ',
                'printer_profile' => 'thermal80',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $station = PosStation::query()->where('code', 'POS.CAJA_1-2')->firstOrFail();

        $this->assertSame('Caja Principal', $station->name);
        $this->assertSame('thermal80', $station->printer_profile);
        $this->assertSame($branch->id, (int) $station->branch_id);
        $this->assertTrue($station->is_active);
        $this->assertNull($station->getAttribute('device_token_hash'));
        $this->assertNull($station->last_seen_at);
        $this->assertNull($station->deleted_at);
    }

    public function test_codigo_normaliza_mayusculas_rechaza_formato_y_es_unico(): void
    {
        $branch = $this->makeBranch();

        $station = $this->createStation($this->admin, [
            'code' => 'abc.1_2-3',
            'name' => 'Caja',
            'branch_id' => $branch->id,
        ]);

        $this->assertSame('ABC.1_2-3', $station->code);

        foreach (['', 'CODIGO CON ESPACIO', str_repeat('X', 31), 'Añandú', 'ABC/1', 'ABC!'] as $invalid) {
            $this->actingAs($this->admin)
                ->post('/settings/pos-stations', $this->stationPayload([
                    'code' => $invalid,
                    'branch_id' => $branch->id,
                ]))
                ->assertSessionHasErrors('code');
        }

        $this->actingAs($this->admin)
            ->post('/settings/pos-stations', $this->stationPayload([
                'code' => 'abc.1_2-3',
                'branch_id' => $branch->id,
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_codigo_unico_incluye_variantes_de_caja_y_eliminados_logicamente(): void
    {
        $branch = $this->makeBranch();
        $station = $this->createStation($this->admin, ['code' => 'DUP-POS', 'branch_id' => $branch->id]);

        $this->actingAs($this->admin)
            ->post('/settings/pos-stations', $this->stationPayload([
                'code' => 'dup-pos',
                'branch_id' => $branch->id,
            ]))
            ->assertSessionHasErrors('code');

        $station->delete();

        $this->actingAs($this->admin)
            ->post('/settings/pos-stations', $this->stationPayload([
                'code' => 'DUP-POS',
                'branch_id' => $branch->id,
            ]))
            ->assertSessionHasErrors('code');

        $this->assertSame(1, PosStation::withTrashed()->where('code', 'DUP-POS')->count());
    }

    public function test_la_base_rechaza_duplicados_aunque_el_prechequeo_se_omita(): void
    {
        $branch = $this->makeBranch();
        $station = $this->createStation($this->admin, ['code' => 'RACE-POS', 'branch_id' => $branch->id]);

        try {
            DB::table('pos_stations')->insert([
                'branch_id' => $branch->id,
                'code' => $station->code,
                'name' => 'Duplicada',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Se esperaba una violación de unicidad en la base.');
        } catch (QueryException) {
            $this->assertSame(1, PosStation::withTrashed()->where('code', 'RACE-POS')->count());
        }
    }

    public function test_codigo_es_inmutable_y_se_ignora_en_la_edicion(): void
    {
        $branch = $this->makeBranch();
        $station = $this->createStation($this->admin, ['code' => 'IMM-POS', 'branch_id' => $branch->id]);

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'code' => 'OTRO-CODIGO',
                'name' => 'Nombre Nuevo',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $station->refresh();
        $this->assertSame('IMM-POS', $station->code);
        $this->assertSame('Nombre Nuevo', $station->name);

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'code' => 'bad code!',
                'name' => 'Nombre Nuevo',
            ])
            ->assertRedirect();

        $this->assertSame('IMM-POS', $station->refresh()->code);
    }

    public function test_perfiles_de_impresora_permitidos_y_rechazo_de_invalidos(): void
    {
        $branch = $this->makeBranch();

        foreach (['thermal58', 'thermal80', 'a4', null] as $index => $profile) {
            $station = $this->createStation($this->admin, [
                'code' => 'PRN-'.$index,
                'branch_id' => $branch->id,
                'printer_profile' => $profile,
            ]);

            $this->assertSame($profile, $station->printer_profile);
        }

        foreach (['thermal', '58', 'A4', 'pdf'] as $index => $invalid) {
            $this->actingAs($this->admin)
                ->post('/settings/pos-stations', $this->stationPayload([
                    'code' => 'BAD-PRN-'.$index,
                    'branch_id' => $branch->id,
                    'printer_profile' => $invalid,
                ]))
                ->assertSessionHasErrors('printer_profile');
        }

        $this->assertDatabaseMissing('pos_stations', ['code' => 'BAD-PRN-0']);
    }

    public function test_campos_forjados_no_alteran_el_estado_ni_el_token(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)
            ->post('/settings/pos-stations', $this->stationPayload([
                'code' => 'FORGED-POS',
                'branch_id' => $branch->id,
                'id' => 999999,
                'is_active' => false,
                'device_token_hash' => str_repeat('a', 64),
                'last_seen_at' => '2000-01-01 00:00:00',
                'deleted_at' => '2000-01-02 00:00:00',
                'created_at' => '2000-01-01 00:00:00',
            ]))
            ->assertRedirect();

        $station = PosStation::query()->where('code', 'FORGED-POS')->firstOrFail();

        $this->assertNotSame(999999, $station->id);
        $this->assertTrue($station->is_active);
        $this->assertNull($station->getAttribute('device_token_hash'));
        $this->assertNull($station->last_seen_at);
        $this->assertNull($station->deleted_at);

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => $station->name,
                'is_active' => false,
                'device_token_hash' => str_repeat('b', 64),
                'last_seen_at' => '2000-01-01 00:00:00',
                'deleted_at' => '2000-01-02 00:00:00',
            ])
            ->assertRedirect();

        $station->refresh();
        $this->assertTrue($station->is_active);
        $this->assertNull($station->getAttribute('device_token_hash'));
        $this->assertNull($station->last_seen_at);
        $this->assertNull($station->deleted_at);
    }

    public function test_sucursal_obligatoria_existente_activa_y_no_eliminada(): void
    {
        $inactive = $this->makeBranch(['is_active' => false]);
        $trashed = $this->makeBranch();
        $trashed->delete();

        foreach ([null, 999999, $inactive->id, $trashed->id] as $index => $branchId) {
            $this->actingAs($this->admin)
                ->post('/settings/pos-stations', $this->stationPayload([
                    'code' => 'BR-POS-'.$index,
                    'branch_id' => $branchId,
                ]))
                ->assertSessionHasErrors('branch_id');
        }

        $this->assertSame(0, PosStation::query()->count());
    }

    public function test_administrador_por_sucursal_solo_crea_en_sus_sucursales(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $manager = $this->makeBranchUser($branchA, $this->makeRoleWithPermissions(['settings.manage']));

        $this->actingAs($manager)
            ->post('/settings/pos-stations', $this->stationPayload([
                'code' => 'OUT-POS',
                'branch_id' => $branchB->id,
            ]))
            ->assertSessionHasErrors('branch_id');

        $this->assertSame(0, PosStation::query()->count());

        $station = $this->createStation($manager, ['code' => 'IN-POS', 'branch_id' => $branchA->id]);
        $this->assertSame($branchA->id, (int) $station->branch_id);
    }

    public function test_edicion_de_sucursal_solo_a_destino_activo_y_autorizado(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $inactive = $this->makeBranch(['is_active' => false]);

        $station = $this->createStation($this->admin, ['code' => 'MOVE-POS', 'branch_id' => $branchA->id]);

        $manager = $this->makeBranchUser($branchA, $this->makeRoleWithPermissions(['settings.manage']));

        $this->actingAs($manager)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => $station->name,
                'branch_id' => $branchB->id,
            ])
            ->assertSessionHasErrors('branch_id');

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => $station->name,
                'branch_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertSame($branchA->id, (int) $station->refresh()->branch_id);

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => $station->name,
                'branch_id' => $branchB->id,
            ])
            ->assertRedirect();

        $this->assertSame($branchB->id, (int) $station->refresh()->branch_id);
    }

    public function test_activar_y_desactivar_conservan_codigo_y_no_borran(): void
    {
        $branch = $this->makeBranch();
        $station = $this->createStation($this->admin, ['code' => 'STATE-POS', 'branch_id' => $branch->id]);

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/deactivate")
            ->assertRedirect();

        $station->refresh();
        $this->assertFalse($station->is_active);
        $this->assertNull($station->deleted_at);
        $this->assertSame('STATE-POS', $station->code);
        $this->assertNull($station->last_seen_at);

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/activate")
            ->assertRedirect();

        $this->assertTrue($station->refresh()->is_active);
        $this->assertNull($station->getAttribute('device_token_hash'));
    }

    public function test_no_existe_borrado_fisico(): void
    {
        $branch = $this->makeBranch();
        $station = $this->createStation($this->admin, ['code' => 'NO-DELETE-POS', 'branch_id' => $branch->id]);

        $this->actingAs($this->admin)
            ->delete("/settings/pos-stations/{$station->id}")
            ->assertStatus(405);

        $this->assertNull($station->refresh()->deleted_at);
    }
}
