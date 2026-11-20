<?php

namespace App\Support\Inventory;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de traspasos entre sucursales (INVTRANSFER-001 · RN-26/INV-06).
 *
 * El alcance siempre es una sucursal resuelta en servidor: una fila se publica
 * si la sucursal es origen O destino. No se expone el numero interno de
 * borrador, costos, precios, margenes ni datos personales. Las variantes y
 * productos historicos inactivos o eliminados logicamente siguen visibles en el
 * detalle porque el registro de traspaso debe conservarse.
 *
 * El listado ordena por id descendente y acota la pagina a 1..100 (default 25).
 */
final class StockTransferQueryService
{
    /** @var list<string> */
    public const STATUSES = [
        'draft',
        'requested',
        'in_transit',
        'received',
        'partially_received',
        'cancelled',
    ];

    private const STATUS_LABELS = [
        'draft' => 'Borrador',
        'requested' => 'Solicitado',
        'in_transit' => 'En tránsito',
        'received' => 'Recibido',
        'partially_received' => 'Recibido parcialmente',
        'cancelled' => 'Cancelado',
    ];

    public function paginate(
        int $branchId,
        ?string $status = null,
        string $number = '',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $perPage = max(1, min(100, $perPage));
        $number = mb_substr(trim($number), 0, 30);

        $query = DB::table('stock_transfers as st')
            ->join('branches as fb', 'fb.id', '=', 'st.from_branch_id')
            ->join('branches as tb', 'tb.id', '=', 'st.to_branch_id')
            ->where(function ($query) use ($branchId): void {
                $query->where('st.from_branch_id', $branchId)
                    ->orWhere('st.to_branch_id', $branchId);
            })
            ->select([
                'st.id',
                'st.number',
                'st.status',
                'st.from_branch_id',
                'st.to_branch_id',
                'st.requested_at',
                'st.shipped_at',
                'st.received_at',
                'fb.code as from_branch_code',
                'fb.name as from_branch_name',
                'tb.code as to_branch_code',
                'tb.name as to_branch_name',
            ])
            ->orderBy('st.id', 'desc');

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $query->where('st.status', $status);
        }

        if ($number !== '') {
            $query->where('st.number', 'like', '%'.$number.'%');
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $transferIds = $paginator->getCollection()
            ->map(fn (object $row): int => (int) $row->id)
            ->all();
        $aggregates = $this->aggregates($transferIds);

        $paginator->setCollection(
            $paginator->getCollection()->map(function (object $row) use ($branchId, $aggregates): array {
                $id = (int) $row->id;
                $summary = $aggregates[$id] ?? [
                    'requested' => 0,
                    'shipped' => 0,
                    'received' => 0,
                    'pending' => 0,
                    'line_count' => 0,
                ];

                return [
                    'id' => $id,
                    'number' => $this->publicNumber((string) $row->number, (string) $row->status),
                    'status' => (string) $row->status,
                    'status_label' => $this->statusLabel((string) $row->status),
                    'from_branch' => [
                        'id' => (int) $row->from_branch_id,
                        'code' => (string) $row->from_branch_code,
                        'name' => (string) $row->from_branch_name,
                    ],
                    'to_branch' => [
                        'id' => (int) $row->to_branch_id,
                        'code' => (string) $row->to_branch_code,
                        'name' => (string) $row->to_branch_name,
                    ],
                    'is_origin' => (int) $row->from_branch_id === $branchId,
                    'is_destination' => (int) $row->to_branch_id === $branchId,
                    'line_count' => $summary['line_count'],
                    'requested_total' => InventoryDecimal::formatQuantity($summary['requested']),
                    'shipped_total' => InventoryDecimal::formatQuantity($summary['shipped']),
                    'received_total' => InventoryDecimal::formatQuantity($summary['received']),
                    'pending_total' => InventoryDecimal::formatQuantity($summary['pending']),
                    'requested_at' => $row->requested_at === null ? null : (string) $row->requested_at,
                    'shipped_at' => $row->shipped_at === null ? null : (string) $row->shipped_at,
                    'received_at' => $row->received_at === null ? null : (string) $row->received_at,
                ];
            }),
        );

        return $paginator;
    }

    /**
     * Detalle visible solo para la sucursal de origen o destino.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $transferId, int $branchId): ?array
    {
        $row = DB::table('stock_transfers as st')
            ->join('branches as fb', 'fb.id', '=', 'st.from_branch_id')
            ->join('branches as tb', 'tb.id', '=', 'st.to_branch_id')
            ->where('st.id', $transferId)
            ->first([
                'st.id',
                'st.number',
                'st.status',
                'st.from_branch_id',
                'st.to_branch_id',
                'st.notes',
                'st.requested_at',
                'st.shipped_at',
                'st.received_at',
                'fb.code as from_branch_code',
                'fb.name as from_branch_name',
                'tb.code as to_branch_code',
                'tb.name as to_branch_name',
            ]);

        if ($row === null) {
            return null;
        }

        $fromBranchId = (int) $row->from_branch_id;
        $toBranchId = (int) $row->to_branch_id;

        if ($branchId !== $fromBranchId && $branchId !== $toBranchId) {
            return null;
        }

        $items = DB::table('stock_transfer_items as sti')
            ->join('product_variants as pv', 'pv.id', '=', 'sti.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('sti.transfer_id', $transferId)
            ->orderBy('sti.variant_id')
            ->orderBy('sti.id')
            ->get([
                'sti.id',
                'sti.variant_id',
                'sti.requested_qty',
                'sti.shipped_qty',
                'sti.received_qty',
                'pv.sku',
                'pv.unit',
                'p.name as product_name',
            ]);

        $lines = [];
        $requestedTotal = 0;
        $shippedTotal = 0;
        $receivedTotal = 0;
        $pendingTotal = 0;

        foreach ($items as $item) {
            $requested = InventoryDecimal::quantityFromStorage($item->requested_qty);
            $shipped = InventoryDecimal::quantityFromStorage($item->shipped_qty);
            $received = InventoryDecimal::quantityFromStorage($item->received_qty);
            $pending = $shipped - $received;

            $requestedTotal += $requested;
            $shippedTotal += $shipped;
            $receivedTotal += $received;
            $pendingTotal += $pending;

            $lines[] = [
                'id' => (int) $item->id,
                'variant_id' => (int) $item->variant_id,
                'sku' => (string) $item->sku,
                'product_name' => (string) $item->product_name,
                'unit' => (string) $item->unit,
                'requested_qty' => InventoryDecimal::formatQuantity($requested),
                'shipped_qty' => InventoryDecimal::formatQuantity($shipped),
                'received_qty' => InventoryDecimal::formatQuantity($received),
                'pending_qty' => InventoryDecimal::formatQuantity($pending),
            ];
        }

        $status = (string) $row->status;
        $isOrigin = $branchId === $fromBranchId;
        $isDestination = $branchId === $toBranchId;

        return [
            'id' => (int) $row->id,
            'number' => $this->publicNumber((string) $row->number, $status),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'from_branch' => [
                'id' => $fromBranchId,
                'code' => (string) $row->from_branch_code,
                'name' => (string) $row->from_branch_name,
            ],
            'to_branch' => [
                'id' => $toBranchId,
                'code' => (string) $row->to_branch_code,
                'name' => (string) $row->to_branch_name,
            ],
            'notes' => $row->notes === null ? null : (string) $row->notes,
            'is_origin' => $isOrigin,
            'is_destination' => $isDestination,
            'requested_at' => $row->requested_at === null ? null : (string) $row->requested_at,
            'shipped_at' => $row->shipped_at === null ? null : (string) $row->shipped_at,
            'received_at' => $row->received_at === null ? null : (string) $row->received_at,
            'line_count' => count($lines),
            'requested_total' => InventoryDecimal::formatQuantity($requestedTotal),
            'shipped_total' => InventoryDecimal::formatQuantity($shippedTotal),
            'received_total' => InventoryDecimal::formatQuantity($receivedTotal),
            'pending_total' => InventoryDecimal::formatQuantity($pendingTotal),
            'lines' => $lines,
            'actions' => [
                'request' => $isOrigin && $status === 'draft',
                'dispatch' => $isOrigin && $status === 'requested',
                'cancel' => $isOrigin && in_array($status, ['draft', 'requested'], true),
                'receive' => $isDestination && $status === 'in_transit',
            ],
        ];
    }

    /**
     * @param  list<int>  $transferIds
     * @return array<int, array{requested: int, shipped: int, received: int, pending: int, line_count: int}>
     */
    private function aggregates(array $transferIds): array
    {
        if ($transferIds === []) {
            return [];
        }

        $rows = DB::table('stock_transfer_items')
            ->whereIn('transfer_id', $transferIds)
            ->get(['transfer_id', 'requested_qty', 'shipped_qty', 'received_qty']);

        $aggregates = [];

        foreach ($rows as $row) {
            $id = (int) $row->transfer_id;
            $aggregates[$id] ??= [
                'requested' => 0,
                'shipped' => 0,
                'received' => 0,
                'pending' => 0,
                'line_count' => 0,
            ];

            $shipped = InventoryDecimal::quantityFromStorage($row->shipped_qty);
            $received = InventoryDecimal::quantityFromStorage($row->received_qty);

            $aggregates[$id]['requested'] += InventoryDecimal::quantityFromStorage($row->requested_qty);
            $aggregates[$id]['shipped'] += $shipped;
            $aggregates[$id]['received'] += $received;
            $aggregates[$id]['pending'] += $shipped - $received;
            $aggregates[$id]['line_count']++;
        }

        return $aggregates;
    }

    /**
     * Oculta solo el borrador y el marcador interno exacto (`DRAFT-` + 24
     * hexadecimales) que conserva un borrador cancelado. Un numero canonico
     * real puede empezar con `DRAFT-` si ese es el codigo de la sucursal de
     * origen: prefijo <= 10 + padding 6..18 nunca alcanza 30 caracteres.
     */
    private function publicNumber(string $number, string $status): ?string
    {
        if ($status === 'draft' || preg_match('/^DRAFT-[0-9A-F]{24}$/', $number) === 1) {
            return null;
        }

        return $number;
    }

    private function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }
}
