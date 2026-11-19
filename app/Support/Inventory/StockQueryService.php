<?php

namespace App\Support\Inventory;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de existencias por sucursal (INV-03).
 *
 * El listado siempre parte de una sucursal resuelta en servidor; no acepta
 * `branch_id` desde la request. Incluye variantes sin fila en `current_stock`
 * con existencia cero, usando la columna generada `available` del esquema
 * (con COALESCE solo para la fila ausente). No escribe ni calcula reservas.
 */
final class StockQueryService
{
    public function paginate(int $branchId, string $search = '', int $perPage = 25): LengthAwarePaginator
    {
        $search = mb_substr(trim($search), 0, 100);
        $perPage = max(1, min(100, $perPage));

        $query = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('current_stock as cs', function ($join) use ($branchId): void {
                $join->on('cs.variant_id', '=', 'pv.id')
                    ->where('cs.branch_id', '=', $branchId);
            })
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->select([
                'pv.id as variant_id',
                'pv.sku',
                'p.id as product_id',
                'p.name as product_name',
                DB::raw('COALESCE(cs.on_hand, 0) as on_hand'),
                DB::raw('COALESCE(cs.reserved, 0) as reserved'),
                DB::raw('COALESCE(cs.available, 0) as available'),
                'cs.last_movement_at',
            ])
            ->orderBy('p.name')
            ->orderBy('pv.sku')
            ->orderBy('pv.id');

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($query) use ($like): void {
                $query->where('pv.sku', 'like', $like)
                    ->orWhere('p.name', 'like', $like);
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => [
                'variant_id' => (int) $row->variant_id,
                'product_id' => (int) $row->product_id,
                'sku' => (string) $row->sku,
                'product_name' => (string) $row->product_name,
                'on_hand' => InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->on_hand),
                ),
                'reserved' => InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->reserved),
                ),
                'available' => InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->available),
                ),
                'last_movement_at' => $row->last_movement_at === null
                    ? null
                    : (string) $row->last_movement_at,
            ]),
        );

        return $paginator;
    }
}
