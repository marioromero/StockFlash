<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Support\Inventory\InventoryDecimal;
use App\Support\Inventory\StockTransferException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Ciclo de vida de traspasos: borrador, solicitud, envio, recepcion y cancelacion
 * (INVTRANSFER-001 · INV-06/RN-26).
 */
class StockTransferLifecycleTest extends StockTransferTestCase
{
    public function test_borrador_oculta_el_numero_placeholder_y_no_mueve_existencias(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-A']);
        $destination = $this->makeBranch(['code' => 'DES-A']);
        [, $variantId] = $this->makeProductVariant('TRF-DRAFT-001');
        $this->createStockRow($origin->id, $variantId, '10.000');

        $transferId = $this->draft(
            $origin,
            $destination,
            [['sku' => 'TRF-DRAFT-001', 'quantity' => '2.500']],
            'Reposición de bodega',
        );

        $transfer = $this->transferRow($transferId);
        $this->assertSame('draft', $transfer->status);
        $this->assertStringStartsWith('DRAFT-', (string) $transfer->number);
        $this->assertSame(30, strlen((string) $transfer->number));
        $this->assertMatchesRegularExpression('/^DRAFT-[0-9A-F]{24}$/', (string) $transfer->number);
        $this->assertSame((int) $origin->id, (int) $transfer->from_branch_id);
        $this->assertSame((int) $destination->id, (int) $transfer->to_branch_id);
        $this->assertNull($transfer->requested_by_user_id);
        $this->assertNull($transfer->requested_at);

        $item = $this->itemRows($transferId)->first();
        $this->assertNotNull($item);
        $this->assertQuantity('2.500', $item->requested_qty);
        $this->assertQuantity('0.000', $item->shipped_qty);
        $this->assertQuantity('0.000', $item->received_qty);
        $this->assertNull($item->unit_cost_snapshot);

        $this->assertSame('10.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.transfer_created')
            ->where('auditable_id', $transferId)
            ->count());

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/TransferShow')
                ->where('transfer.number', null)
                ->where('transfer.status', 'draft')
                ->where('transfer.status_label', 'Borrador')
                ->where('transfer.requested_total', '2.500')
                ->where('transfer.actions.request', true)
                ->missing('transfer.lines.0.unit_cost_snapshot')
                ->missing('transfer.lines.0.cost_price')
                ->missing('transfer.lines.0.price')
                ->missing('transfer.lines.0.margin'));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Transfers')
                ->where('transfers.data.0.id', $transferId)
                ->where('transfers.data.0.number', null)
                ->where('transfers.data.0.requested_total', '2.500')
                ->missing('transfers.data.0.cost_price')
                ->missing('transfers.data.0.unit_cost_snapshot'));
    }

    public function test_creacion_rechaza_sucursales_skus_y_cantidades_invalidas(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-VAL']);
        $destination = $this->makeBranch(['code' => 'DES-VAL']);
        $inactive = $this->makeBranch(['code' => 'DES-OFF', 'is_active' => false]);
        [, $variantId] = $this->makeProductVariant('TRF-VAL-001');

        $this->assertDraftRejected(
            $origin,
            $origin,
            [['sku' => 'TRF-VAL-001', 'quantity' => '1.000']],
            'to_branch_id',
        );
        $this->assertDraftRejected(
            $origin,
            $inactive,
            [['sku' => 'TRF-VAL-001', 'quantity' => '1.000']],
            'branch',
        );
        $this->assertDraftRejected(
            $origin,
            $destination,
            [
                ['sku' => 'TRF-VAL-001', 'quantity' => '1.000'],
                ['sku' => 'trf-val-001', 'quantity' => '2.000'],
            ],
            'lines',
        );

        foreach (['0.000', '-1.000', '1.0000', '1,5', '1e3', '', '1.'] as $invalidQuantity) {
            $this->assertDraftRejected(
                $origin,
                $destination,
                [['sku' => 'TRF-VAL-001', 'quantity' => $invalidQuantity]],
                'lines',
            );
        }

        [, $inactiveVariantId] = $this->makeProductVariant('TRF-VAL-INACTIVE');
        DB::table('product_variants')->where('id', $inactiveVariantId)->update(['is_active' => 0]);
        $this->assertDraftRejected(
            $origin,
            $destination,
            [['sku' => 'TRF-VAL-INACTIVE', 'quantity' => '1.000']],
            'lines',
        );

        [$noStockProductId] = $this->makeProductVariant('TRF-VAL-NOSTOCK');
        DB::table('products')->where('id', $noStockProductId)->update(['tracks_stock' => 0]);
        $this->assertDraftRejected(
            $origin,
            $destination,
            [['sku' => 'TRF-VAL-NOSTOCK', 'quantity' => '1.000']],
            'lines',
        );

        [$comboProductId] = $this->makeProductVariant('TRF-VAL-COMBO');
        DB::table('products')->where('id', $comboProductId)->update(['type' => 'combo']);
        $this->assertDraftRejected(
            $origin,
            $destination,
            [['sku' => 'TRF-VAL-COMBO', 'quantity' => '1.000']],
            'lines',
        );

        [$deletedProductId] = $this->makeProductVariant('TRF-VAL-DELETED');
        DB::table('products')->where('id', $deletedProductId)->update(['deleted_at' => now()]);
        $this->assertDraftRejected(
            $origin,
            $destination,
            [['sku' => 'TRF-VAL-DELETED', 'quantity' => '1.000']],
            'lines',
        );

        $this->assertSame(0, DB::table('stock_transfers')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame('10.000', InventoryDecimal::formatQuantity(10000));
        $this->assertNotNull($variantId);
    }

    public function test_solicitud_asigna_correlativo_por_origen_sin_mover_existencias(): void
    {
        $originA = $this->makeBranch(['code' => 'ORI-A']);
        $originB = $this->makeBranch(['code' => 'ORI-B']);
        $destination = $this->makeBranch(['code' => 'DES-B']);
        [, $variantId] = $this->makeProductVariant('TRF-REQ-001');
        $this->createStockRow($originA->id, $variantId, '8.000');
        $this->createStockRow($originB->id, $variantId, '8.000');

        $firstA = $this->draft($originA, $destination, [['sku' => 'TRF-REQ-001', 'quantity' => '1.000']]);
        $numberA = $this->service()->request($firstA, (int) $originA->id, (int) $this->admin->id);
        $this->assertSame('ORI-A000001', $numberA);

        $firstB = $this->draft($originB, $destination, [['sku' => 'TRF-REQ-001', 'quantity' => '2.000']]);
        $numberB = $this->service()->request($firstB, (int) $originB->id, (int) $this->admin->id);
        $this->assertSame('ORI-B000001', $numberB);

        $secondA = $this->draft($originA, $destination, [['sku' => 'TRF-REQ-001', 'quantity' => '3.000']]);
        $this->assertSame(
            'ORI-A000002',
            $this->service()->request($secondA, (int) $originA->id, (int) $this->admin->id),
        );

        $row = $this->transferRow($firstA);
        $this->assertSame('requested', $row->status);
        $this->assertSame('ORI-A000001', $row->number);
        $this->assertSame((int) $this->admin->id, (int) $row->requested_by_user_id);
        $this->assertNotNull($row->requested_at);

        $this->assertSame('8.000', $this->onHand((int) $originA->id, $variantId));
        $this->assertSame('8.000', $this->onHand((int) $originB->id, $variantId));
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.transfer_requested')
            ->where('auditable_id', $firstA)
            ->count());
    }

    public function test_solicitud_fallida_no_consume_el_correlativo(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RB']);
        $destination = $this->makeBranch(['code' => 'DES-RB']);
        [, $variantId] = $this->makeProductVariant('TRF-REQ-RB');
        $this->createStockRow($origin->id, $variantId, '1.000');

        $transferId = $this->draft($origin, $destination, [['sku' => 'TRF-REQ-RB', 'quantity' => '1.000']]);
        DB::table('branches')->where('id', $destination->id)->update(['is_active' => 0]);

        try {
            $this->service()->request($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('La solicitud debía fallar con destino inactivo.');
        } catch (StockTransferException $exception) {
            $this->assertSame('branch', $exception->field);
        }

        $this->assertSame(0, DB::table('document_sequences')
            ->where('doc_type', 'transfer')
            ->where('scope_id', $origin->id)
            ->count());
        $this->assertSame('draft', $this->transferRow($transferId)->status);

        DB::table('branches')->where('id', $destination->id)->update(['is_active' => 1]);

        $this->assertSame(
            'ORI-RB000001',
            $this->service()->request($transferId, (int) $origin->id, (int) $this->admin->id),
        );
    }

    public function test_codigos_de_origen_mayores_a_diez_caracteres_solicitan_y_envian_con_prefijo_tecnico(): void
    {
        $origin11 = $this->makeBranch(['code' => 'ABCDEFGHIJK']);
        $origin30 = $this->makeBranch(['code' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234']);
        $destination = $this->makeBranch(['code' => 'DES-LONG']);
        [, $variantId] = $this->makeProductVariant('TRF-LONG-CODE');
        $this->createStockRow($origin11->id, $variantId, '5.000');
        $this->createStockRow($origin30->id, $variantId, '5.000');

        $draft11 = $this->draft($origin11, $destination, [['sku' => 'TRF-LONG-CODE', 'quantity' => '2.000']]);
        $draft30 = $this->draft($origin30, $destination, [['sku' => 'TRF-LONG-CODE', 'quantity' => '2.000']]);

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame('5.000', $this->onHand((int) $origin11->id, $variantId));
        $this->assertSame('5.000', $this->onHand((int) $origin30->id, $variantId));

        $number11 = $this->service()->request($draft11, (int) $origin11->id, (int) $this->admin->id);
        $number30 = $this->service()->request($draft30, (int) $origin30->id, (int) $this->admin->id);

        $this->assertSame($this->transferPrefixFor($origin11).'000001', $number11);
        $this->assertSame($this->transferPrefixFor($origin30).'000001', $number30);
        $this->assertSame(16, strlen($number11));
        $this->assertSame(16, strlen($number30));
        $this->assertNotSame($number11, $number30);
        $this->assertSame($number11, (string) $this->transferRow($draft11)->number);
        $this->assertSame($number30, (string) $this->transferRow($draft30)->number);

        $second11 = $this->draft($origin11, $destination, [['sku' => 'TRF-LONG-CODE', 'quantity' => '1.000']]);
        $this->assertSame(
            $this->transferPrefixFor($origin11).'000002',
            $this->service()->request($second11, (int) $origin11->id, (int) $this->admin->id),
        );

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame('5.000', $this->onHand((int) $origin11->id, $variantId));
        $this->assertSame('5.000', $this->onHand((int) $origin30->id, $variantId));

        $this->service()->dispatch($draft11, (int) $origin11->id, (int) $this->admin->id);
        $this->service()->dispatch($draft30, (int) $origin30->id, (int) $this->admin->id);

        $this->assertSame('in_transit', $this->transferRow($draft11)->status);
        $this->assertSame('in_transit', $this->transferRow($draft30)->status);
        $this->assertSame('3.000', $this->onHand((int) $origin11->id, $variantId));
        $this->assertSame('3.000', $this->onHand((int) $origin30->id, $variantId));
        $this->assertSame('0.000', $this->onHand((int) $destination->id, $variantId));
        $this->assertSame(2, DB::table('stock_movements')->count());
    }

    public function test_fallo_de_auditoria_en_solicitud_conserva_borrador_y_no_consume_correlativo(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RAUD']);
        $destination = $this->makeBranch(['code' => 'DES-RAUD']);
        [, $variantId] = $this->makeProductVariant('TRF-REQ-AUD');
        $this->createStockRow($origin->id, $variantId, '4.000');

        $transferId = $this->draft($origin, $destination, [
            ['sku' => 'TRF-REQ-AUD', 'quantity' => '2.000'],
        ]);

        DB::statement(
            "CREATE TRIGGER fail_transfer_request_audit BEFORE INSERT ON audit_logs "
            ."WHEN NEW.event = 'inventory.transfer_requested' "
            ."BEGIN SELECT RAISE(ABORT, 'audit failure'); END",
        );

        try {
            $this->service()->request($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('El fallo de auditoría debía revertir la solicitud.');
        } catch (QueryException) {
            $this->assertTrue(true);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_transfer_request_audit');
        }

        $row = $this->transferRow($transferId);
        $this->assertSame('draft', $row->status);
        $this->assertMatchesRegularExpression('/^DRAFT-[0-9A-F]{24}$/', (string) $row->number);
        $this->assertNull($row->requested_by_user_id);
        $this->assertNull($row->requested_at);
        $this->assertSame(0, DB::table('document_sequences')
            ->where('doc_type', 'transfer')
            ->where('scope_id', $origin->id)
            ->count());
        $this->assertSame('4.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('audit_logs')
            ->where('event', 'inventory.transfer_requested')
            ->count());

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transfer.number', null)
                ->where('transfer.status', 'draft'));

        $this->assertSame(
            'ORI-RAUD000001',
            $this->service()->request($transferId, (int) $origin->id, (int) $this->admin->id),
        );
        $this->assertSame(2, (int) DB::table('document_sequences')
            ->where('doc_type', 'transfer')
            ->where('scope_id', $origin->id)
            ->value('next_number'));
    }

    public function test_cancelar_borrador_o_solicitud_no_mueve_stock_y_no_permite_cancelar_enviado(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-CAN']);
        $destination = $this->makeBranch(['code' => 'DES-CAN']);
        [, $variantId] = $this->makeProductVariant('TRF-CANCEL');
        $this->createStockRow($origin->id, $variantId, '6.000');

        $draftId = $this->draft($origin, $destination, [['sku' => 'TRF-CANCEL', 'quantity' => '1.000']]);
        $this->service()->cancel($draftId, (int) $origin->id, (int) $this->admin->id);
        $this->assertSame('cancelled', $this->transferRow($draftId)->status);

        $requestedId = $this->requested($origin, $destination, [['sku' => 'TRF-CANCEL', 'quantity' => '1.000']]);
        $this->service()->cancel($requestedId, (int) $origin->id, (int) $this->admin->id);
        $this->assertSame('cancelled', $this->transferRow($requestedId)->status);

        $dispatchedId = $this->dispatched($origin, $destination, [['sku' => 'TRF-CANCEL', 'quantity' => '1.000']]);

        try {
            $this->service()->cancel($dispatchedId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('Un traspaso enviado no puede cancelarse.');
        } catch (StockTransferException $exception) {
            $this->assertSame('transfer', $exception->field);
        }

        $this->assertSame('5.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame(1, DB::table('stock_movements')
            ->where('branch_id', $origin->id)
            ->where('variant_id', $variantId)
            ->where('type', 'transfer_out')
            ->count());
    }

    public function test_envio_descuenta_solo_origen_respeta_reservado_y_publica_movimientos_por_item(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-SND']);
        $destination = $this->makeBranch(['code' => 'DES-SND']);
        [, $firstVariant] = $this->makeProductVariant('TRF-SEND-001');
        [, $secondVariant] = $this->makeProductVariant('TRF-SEND-002');
        $this->createStockRow($origin->id, $firstVariant, '10.000', '1.000');
        $this->createStockRow($origin->id, $secondVariant, '5.000', '0.000');

        $transferId = $this->requested($origin, $destination, [
            ['sku' => 'TRF-SEND-001', 'quantity' => '2.500'],
            ['sku' => 'TRF-SEND-002', 'quantity' => '1.250'],
        ]);

        $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);

        $this->assertSame('in_transit', $this->transferRow($transferId)->status);
        $this->assertSame('7.500', $this->onHand((int) $origin->id, $firstVariant));
        $this->assertSame('1.000', $this->reserved((int) $origin->id, $firstVariant));
        $this->assertSame('3.750', $this->onHand((int) $origin->id, $secondVariant));
        $this->assertSame('0.000', $this->onHand((int) $destination->id, $firstVariant));
        $this->assertSame('0.000', $this->onHand((int) $destination->id, $secondVariant));

        $itemIds = $this->itemIdsByVariant($transferId);
        $movements = DB::table('stock_movements')
            ->where('branch_id', $origin->id)
            ->orderBy('variant_id')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertSame('transfer_out', $movements[0]->type);
        $this->assertSame('stock_transfer_item', $movements[0]->source_type);
        $this->assertSame($itemIds[$firstVariant], (int) $movements[0]->source_id);
        $this->assertQuantity('-2.500', $movements[0]->quantity);
        $this->assertSame('transfer_out', $movements[1]->type);
        $this->assertSame($itemIds[$secondVariant], (int) $movements[1]->source_id);
        $this->assertQuantity('-1.250', $movements[1]->quantity);
        $this->assertSame(0, DB::table('stock_movements')->where('branch_id', $destination->id)->count());

        foreach ($this->itemRows($transferId) as $item) {
            $this->assertQuantity(
                InventoryDecimal::formatQuantity(InventoryDecimal::quantityFromStorage($item->requested_qty)),
                $item->shipped_qty,
            );
        }

        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.transfer_dispatched')
            ->where('auditable_id', $transferId)
            ->count());
    }

    public function test_envio_falla_si_no_hay_disponible_suficiente_aunque_la_sucursal_permita_negativos(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-NEG', 'allow_negative_stock' => 1]);
        $destination = $this->makeBranch(['code' => 'DES-NEG']);
        [, $variantId] = $this->makeProductVariant('TRF-SEND-NEG');
        $this->createStockRow($origin->id, $variantId, '5.000', '3.000');

        $transferId = $this->requested($origin, $destination, [
            ['sku' => 'TRF-SEND-NEG', 'quantity' => '3.000'],
        ]);

        try {
            $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('El envío debía fallar por disponible insuficiente.');
        } catch (StockTransferException $exception) {
            $this->assertSame('lines', $exception->field);
        }

        $this->assertSame('requested', $this->transferRow($transferId)->status);
        $this->assertSame('5.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame('3.000', $this->reserved((int) $origin->id, $variantId));
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, (int) $this->itemRows($transferId)->first()->shipped_qty);
    }

    public function test_envio_falla_si_falta_la_fila_de_stock(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-NOROW']);
        $destination = $this->makeBranch(['code' => 'DES-NOROW']);
        $this->makeProductVariant('TRF-SEND-NOROW');

        $transferId = $this->requested($origin, $destination, [
            ['sku' => 'TRF-SEND-NOROW', 'quantity' => '1.000'],
        ]);

        try {
            $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('El envío debía fallar sin fila de stock.');
        } catch (StockTransferException $exception) {
            $this->assertSame('lines', $exception->field);
        }

        $this->assertSame('requested', $this->transferRow($transferId)->status);
        $this->assertSame(0, DB::table('current_stock')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_doble_envio_falla_y_no_duplica_movimientos(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-DBL']);
        $destination = $this->makeBranch(['code' => 'DES-DBL']);
        [, $variantId] = $this->makeProductVariant('TRF-SEND-DBL');
        $this->createStockRow($origin->id, $variantId, '5.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-SEND-DBL', 'quantity' => '2.000'],
        ]);

        try {
            $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('El segundo envío debía fallar.');
        } catch (StockTransferException $exception) {
            $this->assertSame('transfer', $exception->field);
        }

        $this->assertSame('in_transit', $this->transferRow($transferId)->status);
        $this->assertSame('3.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame(1, DB::table('stock_movements')->count());
    }

    public function test_envio_rechaza_variante_o_sucursal_inactiva(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-INA']);
        $destination = $this->makeBranch(['code' => 'DES-INA']);
        [, $variantId] = $this->makeProductVariant('TRF-SEND-INA');
        $this->createStockRow($origin->id, $variantId, '5.000');

        $transferId = $this->requested($origin, $destination, [
            ['sku' => 'TRF-SEND-INA', 'quantity' => '1.000'],
        ]);

        DB::table('product_variants')->where('id', $variantId)->update(['is_active' => 0]);

        try {
            $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('La variante inactiva debía bloquear el envío.');
        } catch (StockTransferException $exception) {
            $this->assertSame('lines', $exception->field);
        }

        DB::table('product_variants')->where('id', $variantId)->update(['is_active' => 1]);
        DB::table('branches')->where('id', $destination->id)->update(['is_active' => 0]);

        try {
            $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('La sucursal inactiva debía bloquear el envío.');
        } catch (StockTransferException $exception) {
            $this->assertSame('branch', $exception->field);
        }

        $this->assertSame('requested', $this->transferRow($transferId)->status);
        $this->assertSame('5.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_recepcion_parcial_acredita_solo_destino_y_deja_diferencia_pendiente(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RCV']);
        $destination = $this->makeBranch(['code' => 'DES-RCV']);
        [, $firstVariant] = $this->makeProductVariant('TRF-RECV-001');
        [, $secondVariant] = $this->makeProductVariant('TRF-RECV-002');
        $this->createStockRow($origin->id, $firstVariant, '10.000');
        $this->createStockRow($origin->id, $secondVariant, '10.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-RECV-001', 'quantity' => '4.000'],
            ['sku' => 'TRF-RECV-002', 'quantity' => '3.000'],
        ]);

        $itemIds = $this->itemIdsByVariant($transferId);
        $this->service()->receive(
            $transferId,
            (int) $destination->id,
            (int) $this->admin->id,
            [
                ['item_id' => $itemIds[$firstVariant], 'received_qty' => '4.000'],
                ['item_id' => $itemIds[$secondVariant], 'received_qty' => '1.000'],
            ],
        );

        $this->assertSame('partially_received', $this->transferRow($transferId)->status);
        $this->assertSame('6.000', $this->onHand((int) $origin->id, $firstVariant));
        $this->assertSame('7.000', $this->onHand((int) $origin->id, $secondVariant));
        $this->assertSame('4.000', $this->onHand((int) $destination->id, $firstVariant));
        $this->assertSame('1.000', $this->onHand((int) $destination->id, $secondVariant));

        $incoming = DB::table('stock_movements')
            ->where('branch_id', $destination->id)
            ->where('type', 'transfer_in')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $incoming);
        $this->assertSame('stock_transfer_item', $incoming[0]->source_type);
        $this->assertSame($itemIds[$firstVariant], (int) $incoming[0]->source_id);
        $this->assertQuantity('4.000', $incoming[0]->quantity);
        $this->assertSame($itemIds[$secondVariant], (int) $incoming[1]->source_id);
        $this->assertQuantity('1.000', $incoming[1]->quantity);
        $this->assertSame(0, DB::table('stock_movements')->where('type', 'reversal')->count());

        $received = $this->itemRows($transferId)
            ->mapWithKeys(fn (object $item): array => [(int) $item->variant_id => $item]);
        $this->assertQuantity('4.000', $received[$firstVariant]->received_qty);
        $this->assertQuantity('1.000', $received[$secondVariant]->received_qty);

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transfer.status', 'partially_received')
                ->where('transfer.pending_total', '2.000')
                ->where('transfer.lines.1.pending_qty', '2.000'));

        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.transfer_received')
            ->where('auditable_id', $transferId)
            ->count());
    }

    public function test_recepcion_total_marca_recibido_y_doble_recepcion_falla(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RALL']);
        $destination = $this->makeBranch(['code' => 'DES-RALL']);
        [, $variantId] = $this->makeProductVariant('TRF-RECV-ALL');
        $this->createStockRow($origin->id, $variantId, '5.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-RECV-ALL', 'quantity' => '5.000'],
        ]);
        $itemId = $this->itemIdsByVariant($transferId)[$variantId];

        $this->service()->receive(
            $transferId,
            (int) $destination->id,
            (int) $this->admin->id,
            [['item_id' => $itemId, 'received_qty' => '5.000']],
        );

        $this->assertSame('received', $this->transferRow($transferId)->status);
        $this->assertSame('5.000', $this->onHand((int) $destination->id, $variantId));
        $this->assertSame('0.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame(2, DB::table('stock_movements')->count());

        try {
            $this->service()->receive(
                $transferId,
                (int) $destination->id,
                (int) $this->admin->id,
                [['item_id' => $itemId, 'received_qty' => '5.000']],
            );
            $this->fail('Una segunda recepción debía fallar.');
        } catch (StockTransferException $exception) {
            $this->assertSame('transfer', $exception->field);
        }

        $this->assertSame(2, DB::table('stock_movements')->count());
        $this->assertSame('5.000', $this->onHand((int) $destination->id, $variantId));
    }

    public function test_recepcion_cero_no_crea_movimiento_y_deja_todo_pendiente(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RZERO']);
        $destination = $this->makeBranch(['code' => 'DES-RZERO']);
        [, $variantId] = $this->makeProductVariant('TRF-RECV-ZERO');
        $this->createStockRow($origin->id, $variantId, '2.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-RECV-ZERO', 'quantity' => '2.000'],
        ]);
        $itemId = $this->itemIdsByVariant($transferId)[$variantId];

        $this->service()->receive(
            $transferId,
            (int) $destination->id,
            (int) $this->admin->id,
            [['item_id' => $itemId, 'received_qty' => '0.000']],
        );

        $this->assertSame('partially_received', $this->transferRow($transferId)->status);
        $this->assertSame(0, DB::table('stock_movements')->where('branch_id', $destination->id)->count());
        $this->assertSame('0.000', $this->onHand((int) $destination->id, $variantId));
        $this->assertQuantity('0.000', $this->itemRows($transferId)->first()->received_qty);
    }

    public function test_recepcion_rechaza_lineas_invalidas_sin_cambiar_estado_ni_stock(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RBAD']);
        $destination = $this->makeBranch(['code' => 'DES-RBAD']);
        [, $firstVariant] = $this->makeProductVariant('TRF-RECV-BAD1');
        [, $secondVariant] = $this->makeProductVariant('TRF-RECV-BAD2');
        $this->createStockRow($origin->id, $firstVariant, '10.000');
        $this->createStockRow($origin->id, $secondVariant, '10.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-RECV-BAD1', 'quantity' => '2.000'],
            ['sku' => 'TRF-RECV-BAD2', 'quantity' => '2.000'],
        ]);
        $itemIds = $this->itemIdsByVariant($transferId);
        $firstItem = $itemIds[$firstVariant];
        $secondItem = $itemIds[$secondVariant];

        $invalid = [
            [['item_id' => $firstItem, 'received_qty' => '2.001'], ['item_id' => $secondItem, 'received_qty' => '1.000']],
            [['item_id' => $firstItem, 'received_qty' => '-1.000'], ['item_id' => $secondItem, 'received_qty' => '1.000']],
            [['item_id' => $firstItem, 'received_qty' => '1.000'], ['item_id' => $firstItem, 'received_qty' => '1.000']],
            [['item_id' => $firstItem, 'received_qty' => '1.000'], ['item_id' => $secondItem + 9999, 'received_qty' => '1.000']],
            [['item_id' => $firstItem, 'received_qty' => '1.000']],
        ];

        foreach ($invalid as $lines) {
            try {
                $this->service()->receive(
                    $transferId,
                    (int) $destination->id,
                    (int) $this->admin->id,
                    $lines,
                );
                $this->fail('La recepción inválida debía fallar.');
            } catch (StockTransferException $exception) {
                $this->assertSame('lines', $exception->field);
            }
        }

        $this->assertSame('in_transit', $this->transferRow($transferId)->status);
        $this->assertSame(0, DB::table('stock_movements')->where('branch_id', $destination->id)->count());
        $this->assertSame('0.000', $this->onHand((int) $destination->id, $firstVariant));
    }

    public function test_fallo_de_auditoria_revierte_estado_y_stock(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-AUD']);
        $destination = $this->makeBranch(['code' => 'DES-AUD']);
        [, $variantId] = $this->makeProductVariant('TRF-AUDIT');
        $this->createStockRow($origin->id, $variantId, '4.000');

        $transferId = $this->requested($origin, $destination, [
            ['sku' => 'TRF-AUDIT', 'quantity' => '2.000'],
        ]);

        DB::statement(
            "CREATE TRIGGER fail_transfer_audit BEFORE INSERT ON audit_logs "
            ."WHEN NEW.event = 'inventory.transfer_dispatched' "
            ."BEGIN SELECT RAISE(ABORT, 'audit failure'); END",
        );

        try {
            $this->service()->dispatch($transferId, (int) $origin->id, (int) $this->admin->id);
            $this->fail('El fallo de auditoría debía revertir el envío.');
        } catch (QueryException) {
            $this->assertTrue(true);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_transfer_audit');
        }

        $this->assertSame('requested', $this->transferRow($transferId)->status);
        $this->assertSame('4.000', $this->onHand((int) $origin->id, $variantId));
        $this->assertSame('0.000', $this->onHand((int) $destination->id, $variantId));
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('audit_logs')
            ->where('event', 'inventory.transfer_dispatched')
            ->count());
    }

    public function test_servicio_no_escribe_directo_las_tablas_del_ledger(): void
    {
        $source = file_get_contents(app_path('Support/Inventory/StockTransferService.php'));
        $this->assertIsString($source);

        $this->assertDoesNotMatchRegularExpression(
            "/DB::table\\('current_stock'\\)\\s*->\\s*(insert|insertGetId|update|delete)/",
            $source,
        );
        $this->assertStringNotContainsString("DB::table('stock_movements')", $source);
        $this->assertStringContainsString("DB::table('current_stock')", $source);
        $this->assertStringContainsString('lockForUpdate()', $source);
    }

    private function transferPrefixFor(Branch $branch): string
    {
        return 'T'.substr(str_pad((string) $branch->id, 9, '0', STR_PAD_LEFT), -9);
    }

    /**
     * @param  list<array{sku: string, quantity: string}>  $lines
     */
    private function assertDraftRejected(Branch $from, Branch $to, array $lines, string $field): void
    {
        try {
            $this->draft($from, $to, $lines);
            $this->fail('La creación del borrador debía fallar.');
        } catch (StockTransferException $exception) {
            $this->assertSame($field, $exception->field, $exception->getMessage());
        }
    }
}
