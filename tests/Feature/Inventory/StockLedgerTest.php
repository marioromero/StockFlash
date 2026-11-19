<?php

namespace Tests\Feature\Inventory;

use App\Support\AuditLogger;
use App\Support\Inventory\DuplicateStockMovementException;
use App\Support\Inventory\InventoryDecimal;
use App\Support\Inventory\StockLedgerException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Kardex transaccional, inmutabilidad, idempotencia de origen y reversas.
 */
class StockLedgerTest extends InventoryTestCase
{
    public function test_primer_movimiento_crea_fila_y_registra_balance_auditoria(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-001');

        $this->assertSame(0, DB::table('current_stock')->count());

        $movement = $this->recordMovement($branch->id, $variantId, 'initial', '10.500', [
            'unit_cost' => '500.0000',
            'note' => 'Carga inicial',
        ]);

        $this->assertSame('initial', $movement->type);
        $this->assertQuantity('10.500', $movement->quantity);
        $this->assertQuantity('10.500', $movement->balance_after);

        $stock = DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertSame(1, DB::table('current_stock')->count());
        $this->assertNotNull($stock);
        $this->assertQuantity('10.500', $stock->on_hand);
        $this->assertQuantity('0.000', $stock->reserved);
        $this->assertQuantity('10.500', $stock->available);
        $this->assertNotNull($stock->last_movement_at);

        $audit = DB::table('audit_logs')->where('event', 'inventory.movement_recorded')->first();
        $this->assertNotNull($audit);
        $this->assertSame('inventory', $audit->module_code);
        $this->assertSame('stock_movements', $audit->auditable_type);
        $this->assertSame($movement->id, (int) $audit->auditable_id);
        $this->assertSame($branch->id, (int) $audit->branch_id);

        $values = json_decode((string) $audit->new_values, true);
        $this->assertSame('initial', $values['movement_type']);
        $this->assertSame('10.500', $values['quantity']);
        $this->assertArrayNotHasKey('email', $values);
        $this->assertArrayNotHasKey('password', $values);
        $this->assertArrayNotHasKey('unit_cost', $values);
    }

    public function test_segundo_movimiento_calcula_balance_desde_la_fila_bloqueada(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-002');

        $this->recordMovement($branch->id, $variantId, 'initial', '10.500');
        $second = $this->recordMovement($branch->id, $variantId, 'waste', '-2.250');

        $this->assertQuantity('8.250', $second->balance_after);
        $this->assertQuantity('8.250', $this->onHand($branch->id, $variantId));
        $this->assertSame(2, DB::table('stock_movements')->count());
    }

    public function test_origen_duplicado_no_double_posta_y_no_agrega_indice_unico(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-003');

        $this->recordMovement($branch->id, $variantId, 'sale_pos', '-1.000', [
            'source_type' => 'sale_item',
            'source_id' => 77,
        ]);

        try {
            $this->recordMovement($branch->id, $variantId, 'sale_pos', '-1.000', [
                'source_type' => 'sale_item',
                'source_id' => 77,
            ]);
            $this->fail('Se esperaba el rechazo del origen duplicado.');
        } catch (DuplicateStockMovementException $exception) {
            $this->assertSame('sale_item', $exception->sourceType);
            $this->assertSame(77, $exception->sourceId);
        }

        $this->assertSame(1, DB::table('stock_movements')->count());
        $this->assertQuantity('-1.000', $this->onHand($branch->id, $variantId));

        $indexes = collect(DB::select("PRAGMA index_list('stock_movements')"));
        $this->assertSame(0, $indexes->where('unique', 1)->count(), 'No debe existir un índice único no aprobado.');
    }

    public function test_mismo_origen_con_distinto_tipo_se_permite(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-004');

        $this->recordMovement($branch->id, $variantId, 'initial', '5.000', [
            'source_type' => 'goods_receipt_item',
            'source_id' => 10,
        ]);
        $adjustment = $this->recordMovement($branch->id, $variantId, 'adjustment', '1.000', [
            'source_type' => 'goods_receipt_item',
            'source_id' => 10,
        ]);

        $this->assertQuantity('6.000', $adjustment->balance_after);
        $this->assertSame(2, DB::table('stock_movements')->count());
    }

    public function test_reversa_agrega_inverso_y_no_modifica_el_original(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-005');

        $this->recordMovement($branch->id, $variantId, 'initial', '10.000');
        $original = $this->recordMovement($branch->id, $variantId, 'sale_pos', '-3.000', [
            'unit_cost' => '123.4500',
            'source_type' => 'sale_item',
            'source_id' => 90,
        ]);
        $originalSnapshot = (array) DB::table('stock_movements')->where('id', $original->id)->first();

        $reversal = $this->ledger()->reverseMovement(
            (int) $original->id,
            (int) $this->admin->id,
            null,
            'Corrección de prueba',
        );

        $this->assertSame('reversal', $reversal->type);
        $this->assertQuantity('3.000', $reversal->quantity);
        $this->assertQuantity('10.000', $reversal->balance_after);
        $this->assertSame((int) $original->id, (int) $reversal->reverses_movement_id);
        $this->assertCost('123.4500', $reversal->unit_cost);
        $this->assertQuantity('10.000', $this->onHand($branch->id, $variantId));

        $this->assertSame($originalSnapshot, (array) DB::table('stock_movements')->where('id', $original->id)->first());
        $this->assertSame(3, DB::table('stock_movements')->count());
    }

    public function test_no_se_puede_revertir_dos_veces_ni_revertir_una_reversa(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-006');

        $original = $this->recordMovement($branch->id, $variantId, 'initial', '4.000');
        $reversal = $this->ledger()->reverseMovement((int) $original->id, (int) $this->admin->id);

        try {
            $this->ledger()->reverseMovement((int) $original->id, (int) $this->admin->id);
            $this->fail('Se esperaba el rechazo de doble reversa.');
        } catch (StockLedgerException $exception) {
            $this->assertStringContainsString('ya fue revertido', $exception->getMessage());
        }

        $this->expectException(StockLedgerException::class);
        $this->ledger()->reverseMovement((int) $reversal->id, (int) $this->admin->id);
    }

    public function test_modelo_bloquea_update_delete_y_save_de_movimiento_existente(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-007');
        $movement = $this->recordMovement($branch->id, $variantId, 'initial', '1.000');

        foreach ([
            fn () => $movement->update(['note' => 'alterado']),
            fn () => $movement->delete(),
            function () use ($movement): void {
                $movement->quantity = '0.000';
                $movement->save();
            },
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('La mutación del kardex debía bloquearse.');
            } catch (LogicException) {
                // Esperado: inmutabilidad de aplicación.
            }
        }

        $row = DB::table('stock_movements')->where('id', $movement->id)->first();
        $this->assertQuantity('1.000', $row->quantity);
        $this->assertNull($row->note);
    }

    public function test_fallo_de_auditoria_revierte_cache_y_kardex(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-008');

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->recordMovement($branch->id, $variantId, 'initial', '9.000');
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría caída.', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('current_stock')->count());
    }

    public function test_licencia_suspendida_fuera_de_gracia_bloquea_escritura_sin_dejar_filas(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-009');
        $this->setLicense('suspended', null);

        try {
            $this->recordMovement($branch->id, $variantId, 'initial', '1.000');
            $this->fail('Se esperaba el modo solo lectura.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('read_only', $exception->errors());
        }

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('current_stock')->count());
    }

    public function test_fk_de_sucursal_y_variante_faltantes_falla_y_revierte(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-010');

        foreach ([
            ['branch_id' => $branch->id + 999999, 'variant_id' => $variantId],
            ['branch_id' => $branch->id, 'variant_id' => $variantId + 999999],
        ] as $ids) {
            try {
                $this->ledger()->recordMovement([
                    'branch_id' => $ids['branch_id'],
                    'variant_id' => $ids['variant_id'],
                    'type' => 'adjustment',
                    'quantity' => '1.000',
                ]);
                $this->fail('La FK faltante debía fallar.');
            } catch (QueryException) {
                // Esperado.
            }
        }

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('current_stock')->count());
    }

    public function test_decimales_rechazan_escala_rango_cero_flotantes_y_campos_no_permitidos(): void
    {
        $this->assertSame(999_999_999_999, InventoryDecimal::quantity('999999999.999'));
        $this->assertSame(-999_999_999_999, InventoryDecimal::quantity('-999999999.999'));
        $this->assertSame(10_500, InventoryDecimal::quantity('10.5'));
        $this->assertSame('10.500', InventoryDecimal::formatQuantity(10_500));

        foreach (['1000000000', '1.0001', 'abc', '', '1e3', '1,5', '+1.000'] as $invalid) {
            try {
                InventoryDecimal::quantity($invalid);
                $this->fail("La cantidad inválida {$invalid} debía rechazarse.");
            } catch (InvalidArgumentException) {
                // Esperado.
            }
        }

        foreach ([1.5, null, true] as $invalid) {
            try {
                InventoryDecimal::quantity($invalid);
                $this->fail('La cantidad no textual debía rechazarse.');
            } catch (InvalidArgumentException) {
                // Esperado.
            }
        }

        $this->assertSame(99_999_999_999_999, InventoryDecimal::cost('9999999999.9999'));

        foreach (['10000000000', '-1.0000', '1.00001'] as $invalid) {
            try {
                InventoryDecimal::cost($invalid);
                $this->fail("El costo inválido {$invalid} debía rechazarse.");
            } catch (InvalidArgumentException) {
                // Esperado.
            }
        }
    }

    public function test_servicio_rechaza_cantidad_cero_tipo_no_permitido_y_campos_no_allowlisted(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-011');

        foreach ([
            ['type' => 'reversal', 'quantity' => '1.000'],
            ['type' => 'adjustment', 'quantity' => '0.000'],
            ['type' => 'adjustment', 'quantity' => '1.000', 'balance_after' => '9.000'],
            ['type' => 'adjustment', 'quantity' => '1.000', 'reservation_id' => 4],
        ] as $payload) {
            try {
                $this->ledger()->recordMovement(array_merge([
                    'branch_id' => $branch->id,
                    'variant_id' => $variantId,
                    'type' => 'adjustment',
                    'quantity' => '1.000',
                ], $payload));
                $this->fail('El payload no permitido debía rechazarse.');
            } catch (InvalidArgumentException) {
                // Esperado.
            }
        }

        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_overflow_al_sumar_sobre_el_maximo_revierte_todo(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-012');

        $this->recordMovement($branch->id, $variantId, 'initial', '999999999.999');

        try {
            $this->recordMovement($branch->id, $variantId, 'adjustment', '0.001');
            $this->fail('La suma fuera de rango debía fallar.');
        } catch (InvalidArgumentException) {
            // Esperado.
        }

        $this->assertSame(1, DB::table('stock_movements')->count());
        $this->assertQuantity('999999999.999', $this->onHand($branch->id, $variantId));
    }

    public function test_guardar_un_producto_no_crea_ni_cambia_stock(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-013');
        $this->recordMovement($branch->id, $variantId, 'initial', '7.000');

        $beforeStock = $this->tableRows('current_stock');
        $beforeMovements = $this->tableRows('stock_movements');

        $this->actingAs($this->admin)
            ->post('/catalog/products', [
                'type' => 'simple',
                'name' => 'Producto sin efecto en stock',
                'tracks_stock' => true,
                'is_sellable' => true,
                'is_active' => true,
                'tag_ids' => [],
                'variants' => [[
                    'sku' => 'NO-STOCK-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
                    'price' => '1000.00',
                    'cost_price' => '500.0000',
                    'unit' => 'unit',
                    'sold_by_weight' => false,
                    'allow_price_override' => true,
                    'combo_deducts_components' => true,
                    'is_active' => true,
                    'attribute_value_ids' => [],
                ]],
            ])
            ->assertRedirect();

        $this->assertSame($beforeStock, $this->tableRows('current_stock'));
        $this->assertSame($beforeMovements, $this->tableRows('stock_movements'));
    }

    public function test_reservas_no_existen_ni_generan_movimientos_en_esta_ola(): void
    {
        $this->assertFalse(Schema::hasTable('stock_reservations'));
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_escrituras_secuenciales_desde_cero_no_pierden_balance_en_sqlite(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('LEDGER-014');

        $this->recordMovement($branch->id, $variantId, 'initial', '1.500');
        $this->recordMovement($branch->id, $variantId, 'adjustment', '2.250');

        $movements = DB::table('stock_movements')->orderBy('id')->get();
        $this->assertQuantity('1.500', $movements[0]->balance_after);
        $this->assertQuantity('3.750', $movements[1]->balance_after);
        $this->assertQuantity('3.750', $this->onHand($branch->id, $variantId));
    }

    private function onHand(int $branchId, int $variantId): mixed
    {
        return DB::table('current_stock')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->value('on_hand');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableRows(string $table): array
    {
        $query = DB::table($table);

        if ($table === 'current_stock') {
            $query->orderBy('branch_id')->orderBy('variant_id');
        } else {
            $query->orderBy('id');
        }

        return $query->get()->map(fn (object $row): array => (array) $row)->all();
    }
}
