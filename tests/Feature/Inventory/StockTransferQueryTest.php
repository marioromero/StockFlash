<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Consulta, filtros, alcance de sucursal y mapeo al Kardex de traspasos
 * (INVTRANSFER-001 · INV-06).
 */
class StockTransferQueryTest extends StockTransferTestCase
{
    public function test_listado_es_visible_para_origen_y_destino_y_filtra_por_estado_y_numero(): void
    {
        $originA = $this->makeBranch(['code' => 'ORI-QA']);
        $destB = $this->makeBranch(['code' => 'DES-QB']);
        $otherC = $this->makeBranch(['code' => 'OTH-QC']);
        $otherD = $this->makeBranch(['code' => 'OTH-QD']);
        $this->makeProductVariant('TRF-QUERY');

        $draftId = $this->draft($originA, $destB, [['sku' => 'TRF-QUERY', 'quantity' => '1.000']]);
        $requestedId = $this->requested($originA, $otherC, [['sku' => 'TRF-QUERY', 'quantity' => '2.000']]);
        $unrelatedId = $this->requested($otherD, $destB, [['sku' => 'TRF-QUERY', 'quantity' => '3.000']]);
        $incomingId = $this->requested($otherD, $originA, [['sku' => 'TRF-QUERY', 'quantity' => '4.000']]);
        $requestedNumber = (string) $this->transferRow($requestedId)->number;

        $this->actingAs($this->admin)
            ->get($this->transferUrl($originA))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Transfers')
                ->has('transfers.data', 3)
                ->where('transfers.data.0.id', $incomingId)
                ->where('transfers.data.0.is_destination', true)
                ->where('transfers.data.1.id', $requestedId)
                ->where('transfers.data.1.is_origin', true)
                ->where('transfers.data.2.id', $draftId)
                ->where('transfers.data.2.number', null)
                ->missing('transfers.data.0.cost_price'));

        $this->assertNotContains($unrelatedId, array_column($this->pageProps(
            $this->actingAs($this->admin)->get($this->transferUrl($originA)),
        )['transfers']['data'], 'id'));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($originA, null, '?status=requested'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transfers.data', 2)
                ->where('transfers.data.0.id', $incomingId)
                ->where('transfers.data.1.id', $requestedId));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($originA, null, '?number='.$requestedNumber))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transfers.data', 1)
                ->where('transfers.data.0.id', $requestedId)
                ->where('transfers.data.0.number', $requestedNumber));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($destB))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transfers.data', 2)
                ->where('transfers.data.0.id', $unrelatedId)
                ->where('transfers.data.0.is_destination', true)
                ->where('transfers.data.1.id', $draftId));
    }

    public function test_paginacion_es_acotada_y_deterministica(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-PAGE']);
        $destination = $this->makeBranch(['code' => 'DES-PAGE']);
        $this->makeProductVariant('TRF-PAGE');

        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->draft($origin, $destination, [
                ['sku' => 'TRF-PAGE', 'quantity' => (string) ($i + 1).'.000'],
            ]);
        }

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, null, '?per_page=1000'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transfers.per_page', 100)
                ->has('transfers.data', 3)
                ->where('transfers.data.0.id', $ids[2])
                ->where('transfers.data.1.id', $ids[1])
                ->where('transfers.data.2.id', $ids[0]));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, null, '?per_page=0'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('transfers.per_page', 1));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, null, '?status=not-a-status&number=xx'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', null)
                ->has('transfers.data', 0));
    }

    public function test_detalle_conserva_variantes_inactivas_sin_exponer_costos(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-HIST']);
        $destination = $this->makeBranch(['code' => 'DES-HIST']);
        [$productId, $variantId] = $this->makeProductVariant('TRF-HISTORIC');
        $this->createStockRow($origin->id, $variantId, '5.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-HISTORIC', 'quantity' => '2.000'],
        ]);

        DB::table('product_variants')->where('id', $variantId)->update(['is_active' => 0]);
        DB::table('products')->where('id', $productId)->update(['is_active' => 0]);

        $this->actingAs($this->admin)
            ->get($this->transferUrl($destination, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transfer.lines.0.sku', 'TRF-HISTORIC')
                ->where('transfer.lines.0.product_name', DB::table('products')->where('id', $productId)->value('name'))
                ->where('transfer.lines.0.shipped_qty', '2.000')
                ->where('transfer.lines.0.pending_qty', '2.000')
                ->missing('transfer.lines.0.unit_cost_snapshot')
                ->missing('transfer.lines.0.cost_price')
                ->missing('transfer.lines.0.price')
                ->missing('transfer.lines.0.margin'));
    }

    public function test_kardex_mapea_salida_y_entrada_de_traspaso(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-KARD']);
        $destination = $this->makeBranch(['code' => 'DES-KARD']);
        [, $variantId] = $this->makeProductVariant('TRF-KARDEX');
        $this->createStockRow($origin->id, $variantId, '4.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-KARDEX', 'quantity' => '1.500'],
        ]);
        $itemId = $this->itemIdsByVariant($transferId)[$variantId];
        $this->service()->receive(
            $transferId,
            (int) $destination->id,
            (int) $this->admin->id,
            [['item_id' => $itemId, 'received_qty' => '1.500']],
        );

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$origin->id}/kardex")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('movements.data.0.type', 'transfer_out')
                ->where('movements.data.0.type_label', 'Traspaso enviado')
                ->where('movements.data.0.quantity', '-1.500'));

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$destination->id}/kardex")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('movements.data.0.type', 'transfer_in')
                ->where('movements.data.0.type_label', 'Traspaso recibido')
                ->where('movements.data.0.quantity', '1.500'));

        $movements = DB::table('stock_movements')
            ->where('source_type', 'stock_transfer_item')
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $movements);
        $this->assertSame($itemId, (int) $movements[0]->source_id);
        $this->assertSame($itemId, (int) $movements[1]->source_id);
        $this->assertSame('transfer_out', $movements[0]->type);
        $this->assertSame('transfer_in', $movements[1]->type);
        $this->assertNotContains($movements[0]->type, ['adjustment', 'count_adjustment', 'sale_pos']);
    }
}
