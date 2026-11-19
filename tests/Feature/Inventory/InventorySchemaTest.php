<?php

namespace Tests\Feature\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema aprobado P0 de inventario (Parte 2 §7.2 y §7.4).
 */
class InventorySchemaTest extends InventoryTestCase
{
    public function test_existen_solo_las_dos_tablas_aprobadas_de_esta_ola(): void
    {
        $this->assertTrue(Schema::hasTable('current_stock'));
        $this->assertTrue(Schema::hasTable('stock_movements'));
        // Reservas y configuracion por variante quedan fuera de esta ola.
        $this->assertFalse(Schema::hasTable('stock_reservations'));
        $this->assertFalse(Schema::hasTable('branch_variant_settings'));
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
}
