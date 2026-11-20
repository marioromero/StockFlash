<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Support\Inventory\StockTransferService;
use Illuminate\Support\Facades\DB;

/**
 * Base de pruebas INVTRANSFER-001. Solo SQLite en memoria; nunca base remota.
 */
abstract class StockTransferTestCase extends InventoryTestCase
{
    protected function service(): StockTransferService
    {
        return app(StockTransferService::class);
    }

    /**
     * @param  list<array{sku: string, quantity: string}>  $lines
     */
    protected function draft(Branch $from, Branch $to, array $lines, ?string $notes = null): int
    {
        return $this->service()->createDraft(
            (int) $from->id,
            (int) $to->id,
            (int) $this->admin->id,
            $lines,
            $notes,
        );
    }

    /**
     * @param  list<array{sku: string, quantity: string}>  $lines
     */
    protected function requested(Branch $from, Branch $to, array $lines, ?string $notes = null): int
    {
        $transferId = $this->draft($from, $to, $lines, $notes);
        $this->service()->request($transferId, (int) $from->id, (int) $this->admin->id);

        return $transferId;
    }

    /**
     * @param  list<array{sku: string, quantity: string}>  $lines
     */
    protected function dispatched(Branch $from, Branch $to, array $lines, ?string $notes = null): int
    {
        $transferId = $this->requested($from, $to, $lines, $notes);
        $this->service()->dispatch($transferId, (int) $from->id, (int) $this->admin->id);

        return $transferId;
    }

    protected function transferRow(int $transferId): object
    {
        $row = DB::table('stock_transfers')->where('id', $transferId)->first();
        $this->assertNotNull($row, "No existe el traspaso {$transferId}.");

        return $row;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function itemRows(int $transferId): \Illuminate\Support\Collection
    {
        return DB::table('stock_transfer_items')
            ->where('transfer_id', $transferId)
            ->orderBy('variant_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    protected function itemIdsByVariant(int $transferId): array
    {
        return $this->itemRows($transferId)
            ->mapWithKeys(fn (object $item): array => [(int) $item->variant_id => (int) $item->id])
            ->all();
    }

    protected function onHand(int $branchId, int $variantId): string
    {
        $row = DB::table('current_stock')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->first(['on_hand', 'reserved', 'available']);

        if ($row === null) {
            return '0.000';
        }

        return \App\Support\Inventory\InventoryDecimal::formatQuantity(
            \App\Support\Inventory\InventoryDecimal::quantityFromStorage($row->on_hand),
        );
    }

    protected function reserved(int $branchId, int $variantId): string
    {
        $row = DB::table('current_stock')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->first(['reserved']);

        if ($row === null) {
            return '0.000';
        }

        return \App\Support\Inventory\InventoryDecimal::formatQuantity(
            \App\Support\Inventory\InventoryDecimal::quantityFromStorage($row->reserved),
        );
    }

    protected function transferUrl(Branch $branch, ?int $transferId = null, string $suffix = ''): string
    {
        $url = "/inventory/stock/{$branch->id}/transfers";

        if ($transferId !== null) {
            $url .= "/{$transferId}";
        }

        return $url.$suffix;
    }

    protected function movementCount(int $branchId, int $variantId): int
    {
        return DB::table('stock_movements')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    protected function pageProps(\Illuminate\Testing\TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }
}
