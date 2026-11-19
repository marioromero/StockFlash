<?php

namespace Tests\Feature\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema aprobado P0 de inventario (Parte 2 §7.1, §7.2 y §7.4).
 */
class InventorySchemaTest extends InventoryTestCase
{
    public function test_existen_solo_las_tablas_aprobadas_de_inventario(): void
    {
        $this->assertTrue(Schema::hasTable('current_stock'));
        $this->assertTrue(Schema::hasTable('stock_movements'));
        $this->assertTrue(Schema::hasTable('branch_variant_settings'));
        // Reservas permanecen fuera de esta ola.
        $this->assertFalse(Schema::hasTable('stock_reservations'));
    }

    public function test_branch_variant_settings_tiene_columnas_exactas_sin_id_ni_timestamps(): void
    {
        $expected = [
            'branch_id',
            'variant_id',
            'reorder_point',
            'reorder_qty',
            'max_stock',
            'safety_stock',
            'location_label',
        ];

        $actual = array_column(Schema::getColumns('branch_variant_settings'), 'name');
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);

        $columns = $this->columns('branch_variant_settings');
        $this->assertArrayNotHasKey('id', $columns);
        $this->assertArrayNotHasKey('created_at', $columns);
        $this->assertArrayNotHasKey('updated_at', $columns);

        $this->assertFalse($columns['branch_id']['nullable']);
        $this->assertFalse($columns['variant_id']['nullable']);
        $this->assertFalse($columns['reorder_point']['nullable']);
        $this->assertTrue($columns['reorder_qty']['nullable']);
        $this->assertTrue($columns['max_stock']['nullable']);
        $this->assertFalse($columns['safety_stock']['nullable']);
        $this->assertTrue($columns['location_label']['nullable']);

        $source = $this->reorderMigrationSource();

        foreach ([
            "unsignedBigInteger('branch_id')",
            "unsignedBigInteger('variant_id')",
            "decimal('reorder_point', 12, 3)->default(0)",
            "decimal('reorder_qty', 12, 3)->nullable()",
            "decimal('max_stock', 12, 3)->nullable()",
            "decimal('safety_stock', 12, 3)->default(0)",
            "string('location_label', 50)->nullable()",
            "primary(['branch_id', 'variant_id'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertStringContainsString(
            "foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete()",
            $source,
        );
        $this->assertStringContainsString(
            "foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete()",
            $source,
        );
    }

    public function test_branch_variant_settings_pk_compuesta_y_fks_cascade(): void
    {
        $this->assertTrue($this->hasPrimaryIndex('branch_variant_settings', ['branch_id', 'variant_id']));

        $byColumn = [];

        foreach (Schema::getForeignKeys('branch_variant_settings') as $foreignKey) {
            $byColumn[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        $this->assertArrayHasKey('branch_id', $byColumn);
        $this->assertArrayHasKey('variant_id', $byColumn);
        $this->assertSame('branches', $byColumn['branch_id']['foreign_table']);
        $this->assertSame('product_variants', $byColumn['variant_id']['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $byColumn['branch_id']['on_delete']));
        $this->assertSame('cascade', strtolower((string) $byColumn['variant_id']['on_delete']));

        $plainIndexes = array_values(array_filter(
            Schema::getIndexes('branch_variant_settings'),
            fn (array $index): bool => ! $index['primary'],
        ));

        $this->assertSame([], $plainIndexes, 'No se agregan índices no aprobados.');
    }

    public function test_inventario_agrega_exactamente_una_migracion_aprobada(): void
    {
        $expected = [
            '2026_10_16_000001_create_system_configs_table.php',
            '2026_10_16_000002_create_installation_info_table.php',
            '2026_10_16_000003_create_installation_modules_table.php',
            '2026_10_16_000004_create_license_states_table.php',
            '2026_10_16_000005_create_plan_definitions_table.php',
            '2026_10_16_000006_create_branches_table.php',
            '2026_10_16_000007_create_roles_table.php',
            '2026_10_16_000008_create_permissions_tables.php',
            '2026_10_16_000009_create_users_table.php',
            '2026_10_16_000010_create_user_mfa_recovery_codes_table.php',
            '2026_10_16_000011_create_branch_user_table.php',
            '2026_10_16_000012_create_reason_codes_table.php',
            '2026_10_16_000013_create_pos_stations_table.php',
            '2026_10_16_000014_create_document_sequences_table.php',
            '2026_10_16_000015_create_integration_health_table.php',
            '2026_10_16_000016_create_backup_runs_table.php',
            '2026_10_16_000017_create_system_updates_table.php',
            '2026_10_16_000018_create_authorization_overrides_table.php',
            '2026_10_16_000019_create_audit_logs_table.php',
            '2026_10_16_000020_db001_qa_fixes.php',
            '2026_11_16_000021_create_catalog_p0_tables.php',
            '2026_11_18_000022_create_inventory_core_tables.php',
            '2026_11_19_000023_create_branch_variant_settings_table.php',
        ];

        $actual = array_map(
            fn ($file): string => $file->getFilename(),
            File::files(database_path('migrations')),
        );

        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_columnas_exactas_sin_timestamps_automaticos(): void
    {
        $expected = [
            'current_stock' => ['branch_id', 'variant_id', 'on_hand', 'reserved', 'available', 'last_movement_at', 'updated_at'],
            'stock_movements' => [
                'id', 'branch_id', 'variant_id', 'type', 'quantity', 'balance_after', 'unit_cost',
                'source_type', 'source_id', 'reservation_id', 'reverses_movement_id', 'user_id',
                'reason_code_id', 'note', 'occurred_at',
            ],
        ];

        foreach ($expected as $table => $columns) {
            $actual = array_column(Schema::getColumns($table), 'name');
            sort($actual);
            sort($columns);

            $this->assertSame($columns, $actual, "Columnas inesperadas en {$table}.");
        }

        $this->assertArrayNotHasKey('created_at', $this->columns('current_stock'));
        $this->assertArrayNotHasKey('id', $this->columns('current_stock'));
        $this->assertArrayNotHasKey('created_at', $this->columns('stock_movements'));
        $this->assertArrayNotHasKey('updated_at', $this->columns('stock_movements'));
    }

    public function test_pk_compuesta_e_indices_aprobados(): void
    {
        $this->assertTrue($this->hasPrimaryIndex('current_stock', ['branch_id', 'variant_id']));
        $this->assertTrue($this->hasPlainIndex('stock_movements', ['branch_id', 'variant_id', 'id']));
        $this->assertTrue($this->hasPlainIndex('stock_movements', ['variant_id', 'occurred_at']));
        $this->assertTrue($this->hasPlainIndex('stock_movements', ['source_type', 'source_id']));
        $this->assertTrue($this->hasPlainIndex('stock_movements', ['type', 'occurred_at']));
    }

    public function test_existe_disponible_generado_por_el_motor_y_se_actualiza(): void
    {
        [$productId, $variantId] = $this->makeProductVariant('GEN-001');
        $branch = $this->makeBranch();

        $this->createStockRow($branch->id, $variantId, '10.500', '2.250');

        $row = DB::table('current_stock')->where('branch_id', $branch->id)->first();
        $this->assertQuantity('8.250', $row->available);

        DB::table('current_stock')->where('branch_id', $branch->id)->update(['on_hand' => '12.000']);
        $row = DB::table('current_stock')->where('branch_id', $branch->id)->first();
        $this->assertQuantity('9.750', $row->available);

        $source = $this->migrationSource();
        $this->assertStringContainsString("decimal('available', 12, 3)", $source);
        $this->assertStringContainsString("virtualAs('on_hand - reserved')", $source);
        $this->assertSame('virtual', strtolower((string) ($this->columns('current_stock')['available']['generation']['type'] ?? 'virtual')));
    }

    public function test_disponible_no_admite_escritura_directa(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('GEN-002');

        $this->expectException(QueryException::class);

        DB::table('current_stock')->insert([
            'branch_id' => $branch->id,
            'variant_id' => $variantId,
            'on_hand' => '1.000',
            'reserved' => '0.000',
            'available' => '1.000',
            'updated_at' => now(),
        ]);
    }

    public function test_definiciones_decimales_enum_y_columnas_aprobadas(): void
    {
        $source = $this->migrationSource();

        foreach ([
            "decimal('on_hand', 12, 3)",
            "decimal('reserved', 12, 3)",
            "decimal('available', 12, 3)",
            "decimal('quantity', 12, 3)",
            "decimal('balance_after', 12, 3)",
            "decimal('unit_cost', 14, 4)",
            "string('source_type', 50)",
            "unsignedBigInteger('source_id')",
            "unsignedBigInteger('reservation_id')",
            "string('note', 255)",
            "dateTime('occurred_at')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertStringContainsString(
            "enum('type', [\n                'initial',\n                'purchase_receipt',\n                'adjustment',\n                'count_adjustment',\n                'waste',\n                'sale_pos',\n                'sale_online',\n                'customer_return',\n                'supplier_return',\n                'transfer_out',\n                'transfer_in',\n                'reversal',\n            ])",
            $source,
        );
    }

    public function test_fks_aprobadas_y_fk_de_reserva_diferida_sin_stock_reservations(): void
    {
        $foreignKeys = Schema::getForeignKeys('stock_movements');
        $byColumn = [];

        foreach ($foreignKeys as $foreignKey) {
            $byColumn[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        $this->assertArrayHasKey('branch_id', $byColumn);
        $this->assertArrayHasKey('variant_id', $byColumn);
        $this->assertArrayHasKey('user_id', $byColumn);
        $this->assertArrayHasKey('reason_code_id', $byColumn);
        $this->assertArrayHasKey('reverses_movement_id', $byColumn);

        $this->assertSame('users', $byColumn['user_id']['foreign_table']);
        $this->assertSame('set null', strtolower((string) $byColumn['user_id']['on_delete']));
        $this->assertSame('reason_codes', $byColumn['reason_code_id']['foreign_table']);
        $this->assertSame('stock_movements', $byColumn['reverses_movement_id']['foreign_table']);

        // La columna existe, pero la FK a stock_reservations se difiere junto
        // con la tabla de reservas. No se crea una relacion ficticia.
        $this->assertArrayHasKey('reservation_id', $this->columns('stock_movements'));
        $this->assertArrayNotHasKey('reservation_id', $byColumn);
        $this->assertFalse(Schema::hasTable('stock_reservations'));
    }

    public function test_defaults_decimales_cero(): void
    {
        $source = $this->migrationSource();
        $this->assertStringContainsString("decimal('on_hand', 12, 3)->default(0)", $source);
        $this->assertStringContainsString("decimal('reserved', 12, 3)->default(0)", $source);

        $columns = $this->columns('current_stock');
        $this->assertTrue($columns['last_movement_at']['nullable']);
        $this->assertFalse($columns['updated_at']['nullable']);
        $this->assertFalse($this->columns('stock_movements')['quantity']['nullable']);
        $this->assertTrue($this->columns('stock_movements')['unit_cost']['nullable']);
        $this->assertTrue($this->columns('stock_movements')['source_type']['nullable']);
        $this->assertTrue($this->columns('stock_movements')['source_id']['nullable']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function columns(string $table): array
    {
        $result = [];

        foreach (Schema::getColumns($table) as $column) {
            $result[$column['name']] = $column;
        }

        return $result;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasPlainIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (! $index['unique'] && ! $index['primary'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasPrimaryIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function sameColumns(array $columns, array $expected): bool
    {
        $actual = array_map('strtolower', $columns);
        $expected = array_map('strtolower', $expected);

        return $actual === $expected;
    }

    private function migrationSource(): string
    {
        $source = file_get_contents(database_path('migrations/2026_11_18_000022_create_inventory_core_tables.php'));

        $this->assertIsString($source);

        return $source;
    }

    private function reorderMigrationSource(): string
    {
        $source = file_get_contents(database_path('migrations/2026_11_19_000023_create_branch_variant_settings_table.php'));

        $this->assertIsString($source);

        return $source;
    }
}
