<?php

namespace App\Support\Inventory;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Panel de stock crítico y configuración de reposición por sucursal
 * (INVREORDER-001 · INV-07).
 *
 * Incluye las variantes elegibles (activas, con control de stock, de producto
 * simple|variable y sin borrado lógico) y, además, cualquier variante con una
 * fila histórica de `branch_variant_settings` en la sucursal, aunque hoy ya no
 * sea editable. La existencia se lee de `current_stock`; una fila ausente vale
 * 0.000. No se leen ni publican costos, precios, márgenes, `safety_stock` ni
 * `location_label`.
 *
 * Regla crítica exacta: hay fila de settings, `reorder_point > 0` y
 * `available <= reorder_point`. Un punto 0 (no configurado) nunca es crítico.
 */
final class ReorderQueryService
{
    public function paginate(
        int $branchId,
        string $search = '',
        bool $criticalOnly = false,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $search = mb_substr(trim($search), 0, 100);
        $perPage = max(1, min(100, $perPage));

        $query = $this->baseQuery($branchId);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($query) use ($like): void {
                $query->where('pv.sku', 'like', $like)
                    ->orWhere('p.name', 'like', $like);
            });
        }

        if ($criticalOnly) {
            $this->applyCriticalCondition($query);
        }

        $paginator = $query
            ->orderBy('p.name')
            ->orderBy('pv.sku')
            ->orderBy('pv.id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => $this->mapRow($row)),
        );

        return $paginator;
    }

    /**
     * Conteo de stock crítico de toda la sucursal, independiente del texto
     * buscado o del tamaño de página.
     */
    public function criticalCount(int $branchId): int
    {
        $query = $this->baseQuery($branchId);
        $this->applyCriticalCondition($query);

        return (int) $query->count();
    }

    private function baseQuery(int $branchId)
    {
        return DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('branch_variant_settings as bvs', function ($join) use ($branchId): void {
                $join->on('bvs.variant_id', '=', 'pv.id')
                    ->where('bvs.branch_id', '=', $branchId);
            })
            ->leftJoin('current_stock as cs', function ($join) use ($branchId): void {
                $join->on('cs.variant_id', '=', 'pv.id')
                    ->where('cs.branch_id', '=', $branchId);
            })
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('pv.deleted_at')
                        ->whereNull('p.deleted_at')
                        ->where('pv.is_active', 1)
                        ->where('p.is_active', 1)
                        ->where('p.tracks_stock', 1)
                        ->whereIn('p.type', ['simple', 'variable']);
                })->orWhereNotNull('bvs.variant_id');
            })
            ->select([
                'pv.id as variant_id',
                'pv.sku',
                'p.id as product_id',
                'p.name as product_name',
                'pv.is_active as variant_active',
                'pv.deleted_at as variant_deleted_at',
                'p.is_active as product_active',
                'p.deleted_at as product_deleted_at',
                'p.tracks_stock as product_tracks_stock',
                'p.type as product_type',
                'bvs.variant_id as settings_variant_id',
                'bvs.reorder_point as settings_reorder_point',
                'bvs.reorder_qty as settings_reorder_qty',
                'bvs.max_stock as settings_max_stock',
                DB::raw('COALESCE(cs.on_hand, 0) as on_hand'),
                DB::raw('COALESCE(cs.reserved, 0) as reserved'),
                DB::raw('COALESCE(cs.available, 0) as available'),
            ]);
    }

    private function applyCriticalCondition($query): void
    {
        $query->whereNotNull('bvs.variant_id')
            ->where('bvs.reorder_point', '>', 0)
            ->whereRaw('COALESCE(cs.available, 0) <= bvs.reorder_point');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(object $row): array
    {
        $hasSettings = $row->settings_variant_id !== null;
        $canEdit = $row->variant_deleted_at === null
            && $row->product_deleted_at === null
            && (int) $row->variant_active === 1
            && (int) $row->product_active === 1
            && (int) $row->product_tracks_stock === 1
            && in_array((string) $row->product_type, ['simple', 'variable'], true);

        $available = InventoryDecimal::quantityFromStorage($row->available);
        $reorderPoint = $hasSettings
            ? InventoryDecimal::quantityFromStorage($row->settings_reorder_point)
            : 0;

        // Comparación entera en milésimas: available <= punto, estricto para
        // punto > 0. Nunca usa floats y no aplica safety_stock.
        $isCritical = $hasSettings
            && $reorderPoint > 0
            && $available <= $reorderPoint;

        return [
            'variant_id' => (int) $row->variant_id,
            'product_id' => (int) $row->product_id,
            'sku' => (string) $row->sku,
            'product_name' => (string) $row->product_name,
            'can_edit' => $canEdit,
            'has_settings' => $hasSettings,
            'on_hand' => InventoryDecimal::formatQuantity(
                InventoryDecimal::quantityFromStorage($row->on_hand),
            ),
            'reserved' => InventoryDecimal::formatQuantity(
                InventoryDecimal::quantityFromStorage($row->reserved),
            ),
            'available' => InventoryDecimal::formatQuantity($available),
            'reorder_point' => InventoryDecimal::formatQuantity($reorderPoint),
            'reorder_qty' => ! $hasSettings || $row->settings_reorder_qty === null
                ? null
                : InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->settings_reorder_qty),
                ),
            'max_stock' => ! $hasSettings || $row->settings_max_stock === null
                ? null
                : InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->settings_max_stock),
                ),
            'is_critical' => $isCritical,
        ];
    }
}
