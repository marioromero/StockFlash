<?php

namespace Tests\Feature\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema canónico de traspasos (Parte 2 §7.5 · INV-06).
 */
class StockTransferSchemaTest extends StockTransferTestCase
{
    public function test_columnas_exactas_sin_timestamps_automaticos(): void
    {
        $expected = [
            'stock_transfers' => [
                'id', 'number', 'from_branch_id', 'to_branch_id', 'status',
                'requested_by_user_id', 'shipped_by_user_id', 'received_by_user_id',
                'requested_at', 'shipped_at', 'received_at', 'notes',
            ],
            'stock_transfer_items' => [
                'id', 'transfer_id', 'variant_id', 'requested_qty', 'shipped_qty',
                'received_qty', 'unit_cost_snapshot',
            ],
        ];

        foreach ($expected as $table => $columns) {
            $actual = array_column(Schema::getColumns($table), 'name');
            sort($actual);
            sort($columns);

            $this->assertSame($columns, $actual, "Columnas inesperadas en {$table}.");
            $this->assertArrayNotHasKey('created_at', $this->columns($table));
            $this->assertArrayNotHasKey('updated_at', $this->columns($table));
        }
    }

    public function test_estado_default_enum_e_indice_aprobados(): void
    {
        $columns = $this->columns('stock_transfers');
        $this->assertFalse($columns['number']['nullable']);
        $this->assertFalse($columns['status']['nullable']);
        $this->assertTrue($columns['notes']['nullable']);
        $this->assertTrue($columns['requested_at']['nullable']);
        $this->assertTrue($columns['shipped_at']['nullable']);
        $this->assertTrue($columns['received_at']['nullable']);

        $source = file_get_contents(database_path('migrations/2026_11_20_000024_create_stock_transfer_tables.php'));
        $this->assertIsString($source);

        foreach ([
            'draft', 'requested', 'in_transit', 'received', 'partially_received', 'cancelled',
        ] as $status) {
            $this->assertStringContainsString("'{$status}'", $source);
        }

        $this->assertStringContainsString("->default('draft')", $source);
        $this->assertTrue($this->hasPlainIndex('stock_transfers', ['status']));
        $this->assertTrue($this->hasUniqueIndex('stock_transfers', ['from_branch_id', 'number']));
    }

    public function test_fks_y_borrados_aprobados(): void
    {
        $byColumn = [];

        foreach (Schema::getForeignKeys('stock_transfers') as $foreignKey) {
            $byColumn[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        foreach (['from_branch_id', 'to_branch_id'] as $column) {
            $this->assertArrayHasKey($column, $byColumn);
            $this->assertSame('branches', $byColumn[$column]['foreign_table']);
        }

        foreach (['requested_by_user_id', 'shipped_by_user_id', 'received_by_user_id'] as $column) {
            $this->assertArrayHasKey($column, $byColumn);
            $this->assertSame('users', $byColumn[$column]['foreign_table']);
            $this->assertSame('set null', strtolower((string) $byColumn[$column]['on_delete']));
        }

        $itemForeignKeys = [];

        foreach (Schema::getForeignKeys('stock_transfer_items') as $foreignKey) {
            $itemForeignKeys[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        $this->assertArrayHasKey('transfer_id', $itemForeignKeys);
        $this->assertSame('stock_transfers', $itemForeignKeys['transfer_id']['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $itemForeignKeys['transfer_id']['on_delete']));
        $this->assertArrayHasKey('variant_id', $itemForeignKeys);
        $this->assertSame('product_variants', $itemForeignKeys['variant_id']['foreign_table']);
    }

    public function test_cantidades_decimales_default_cero_y_costo_nullable(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('SCHEMA-TRF-001');

        $transferId = (int) DB::table('stock_transfers')->insertGetId([
            'number' => 'SCHEMA000001',
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
        ]);

        $itemId = (int) DB::table('stock_transfer_items')->insertGetId([
            'transfer_id' => $transferId,
            'variant_id' => $variantId,
        ]);

        $item = DB::table('stock_transfer_items')->where('id', $itemId)->first();
        $this->assertQuantity('0.000', $item->requested_qty);
        $this->assertQuantity('0.000', $item->shipped_qty);
        $this->assertQuantity('0.000', $item->received_qty);
        $this->assertNull($item->unit_cost_snapshot);
        $this->assertSame('draft', $this->transferRow($transferId)->status);

        $columns = $this->columns('stock_transfer_items');
        $this->assertFalse($columns['requested_qty']['nullable']);
        $this->assertFalse($columns['shipped_qty']['nullable']);
        $this->assertFalse($columns['received_qty']['nullable']);
        $this->assertTrue($columns['unit_cost_snapshot']['nullable']);
    }

    public function test_numero_obligatorio_y_unico_por_sucursal_de_origen(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        try {
            DB::table('stock_transfers')->insert([
                'from_branch_id' => $branchA->id,
                'to_branch_id' => $branchB->id,
            ]);
            $this->fail('El número no puede ser nulo.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        DB::table('stock_transfers')->insert([
            'number' => 'TRF-000001',
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
        ]);

        DB::table('stock_transfers')->insert([
            'number' => 'TRF-000001',
            'from_branch_id' => $branchB->id,
            'to_branch_id' => $branchA->id,
        ]);

        $this->assertSame(2, DB::table('stock_transfers')->where('number', 'TRF-000001')->count());

        $this->expectException(QueryException::class);
        DB::table('stock_transfers')->insert([
            'number' => 'TRF-000001',
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
        ]);
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
    private function hasUniqueIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && ! $index['primary'] && $this->sameColumns($index['columns'], $columns)) {
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
        return array_map('strtolower', $columns) === array_map('strtolower', $expected);
    }
}
