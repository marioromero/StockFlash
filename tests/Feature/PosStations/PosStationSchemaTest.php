<?php

namespace Tests\Feature\PosStations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema aprobado de estaciones POS (Parte 2 §4.13 · CSH-02 / POS-02).
 *
 * Esta ola reutiliza exactamente la migración existente
 * `2026_10_16_000013_create_pos_stations_table.php`; no agrega tablas ni
 * columnas.
 */
class PosStationSchemaTest extends PosStationTestCase
{
    public function test_pos_stations_tiene_columnas_tipos_y_sd_exactos(): void
    {
        $this->assertTrue(Schema::hasTable('pos_stations'));

        $expected = [
            'id',
            'branch_id',
            'code',
            'name',
            'printer_profile',
            'device_token_hash',
            'last_seen_at',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $actual = array_column(Schema::getColumns('pos_stations'), 'name');
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);

        $columns = $this->columns('pos_stations');
        $this->assertFalse($columns['branch_id']['nullable']);
        $this->assertFalse($columns['code']['nullable']);
        $this->assertFalse($columns['name']['nullable']);
        $this->assertTrue($columns['printer_profile']['nullable']);
        $this->assertTrue($columns['device_token_hash']['nullable']);
        $this->assertTrue($columns['last_seen_at']['nullable']);
        $this->assertFalse($columns['is_active']['nullable']);
        $this->assertTrue($columns['deleted_at']['nullable']);

        $source = (string) file_get_contents(database_path('migrations/2026_10_16_000013_create_pos_stations_table.php'));

        foreach ([
            "unsignedBigInteger('branch_id')",
            "string('code', 30)->unique()",
            "string('name', 100)",
            "enum('printer_profile', ['thermal58', 'thermal80', 'a4'])->nullable()",
            "string('device_token_hash', 64)->nullable()",
            "dateTime('last_seen_at')->nullable()",
            "unsignedTinyInteger('is_active')->default(1)->index()",
            'timestamps()',
            'softDeletes()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertStringContainsString(
            "foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete()",
            $source,
        );

        $this->assertTrue($this->hasPrimaryIndex('pos_stations', ['id']));
        $this->assertTrue($this->hasUniqueIndex('pos_stations', ['code']));

        $foreignKeys = [];

        foreach (Schema::getForeignKeys('pos_stations') as $foreignKey) {
            $foreignKeys[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        $this->assertArrayHasKey('branch_id', $foreignKeys);
        $this->assertSame('branches', $foreignKeys['branch_id']['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $foreignKeys['branch_id']['on_delete']));
    }

    public function test_no_se_agregan_tablas_de_ventas_caja_ni_reservas(): void
    {
        foreach ([
            'sales',
            'sale_items',
            'sale_payments',
            'sale_returns',
            'cash_sessions',
            'cash_movements',
            'stock_reservations',
            'pos_cart_items',
            'pos_tickets',
            'pos_station_sessions',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La tabla {$table} no pertenece a esta ola.");
        }

        $this->assertSame(
            1,
            collect(File::files(database_path('migrations')))
                ->filter(fn ($file): bool => str_contains($file->getFilename(), 'create_pos_stations_table'))
                ->count(),
            'No se crea una segunda migración para pos_stations.',
        );
    }

    public function test_el_hash_de_dispositivo_admite_solo_columna_de_token(): void
    {
        $columns = array_column(Schema::getColumns('pos_stations'), 'name');

        foreach (['token', 'device_token', 'raw_token', 'api_token', 'last_seen_ip'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "La columna {$forbidden} no está aprobada.");
        }
    }
}
