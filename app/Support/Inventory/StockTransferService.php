<?php

namespace App\Support\Inventory;

use App\Support\AuditLogger;
use App\Support\DocumentSequenceAllocator;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ciclo de vida de traspasos entre sucursales (INVTRANSFER-001 · RN-26/INV-06).
 *
 * Estados aprobados: draft -> requested -> in_transit -> received |
 * partially_received, y draft|requested -> cancelled. El envio descuenta
 * fisicamente solo en el origen; la recepcion suma fisicamente solo en el
 * destino. Toda la escritura de existencias pasa por StockLedger: este servicio
 * nunca escribe directo `current_stock` ni `stock_movements`.
 *
 * Cada transicion es UNA transaccion con `attempts=3`, bloquea la fila del
 * traspaso y revalida sucursal/estado/actor. La numeracion canonica se reserva
 * con DocumentSequenceAllocator dentro de la misma transaccion al solicitar.
 * Mientras el traspaso es borrador se usa un marcador interno unico
 * `DRAFT-` + 24 hexadecimales; nunca se acepta numero del cliente.
 *
 * Diferencias de recepcion (`shipped_qty - received_qty`) quedan pendientes y
 * visibles: no se inventan ajustes, reversas, reclamos ni transiciones extra.
 * La comprobacion de existencia en el envio es acotada a traspasos: exige
 * disponible >= solicitado sin aplicar el interruptor POS RN-22 de stock
 * negativo. MariaDB/concurrencia real permanece NOT_RUN; las pruebas usan
 * SQLite :memory:, donde lockForUpdate es no-op y la transaccion serializa.
 */
final class StockTransferService
{
    private const TRANSFERS = 'stock_transfers';

    private const ITEMS = 'stock_transfer_items';

    private const SOURCE_TYPE = 'stock_transfer_item';

    private const MAX_LINES = 100;

    private const DRAFT_PREFIX = 'DRAFT-';

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly InventoryWriteGuard $guard,
        private readonly AuditLogger $audit,
        private readonly DocumentSequenceAllocator $sequences,
    ) {}

    /**
     * Crea un borrador sin efecto fisico. La sucursal de destino y el actor
     * salen del servidor; las lineas se resuelven por SKU a una unica variante
     * elegible y las cantidades deben ser DECIMAL(12,3) estrictamente > 0.
     *
     * @param  list<array{sku: mixed, quantity: mixed}>  $lines
     */
    public function createDraft(
        int $fromBranchId,
        mixed $toBranchId,
        int $actorId,
        array $lines,
        ?string $notes,
    ): int {
        $toBranchId = $this->positiveIdentifier($toBranchId, 'to_branch_id', 'La sucursal de destino no es válida.');
        $actorId = $this->positiveIdentifier($actorId, 'actor', 'El usuario responsable no es válido.');
        $fromBranchId = $this->positiveIdentifier($fromBranchId, 'branch', 'La sucursal de origen no es válida.');

        if ($fromBranchId === $toBranchId) {
            throw new StockTransferException(
                'to_branch_id',
                'La sucursal de destino debe ser distinta de la sucursal de origen.',
            );
        }

        $normalizedLines = $this->normalizeRequestedLines($lines);
        $normalizedNotes = $this->normalizeNotes($notes);

        return DB::transaction(function () use (
            $fromBranchId,
            $toBranchId,
            $actorId,
            $normalizedLines,
            $normalizedNotes,
        ): int {
            $this->guard->assertWritable();
            $this->assertBranchesActive([$fromBranchId, $toBranchId]);

            $resolved = [];
            $seenVariants = [];

            foreach ($normalizedLines as $index => $line) {
                $variants = DB::table('product_variants as pv')
                    ->join('products as p', 'p.id', '=', 'pv.product_id')
                    ->whereRaw('lower(pv.sku) = ?', [mb_strtolower($line['sku'])])
                    ->whereNull('pv.deleted_at')
                    ->whereNull('p.deleted_at')
                    ->where('pv.is_active', 1)
                    ->where('p.is_active', 1)
                    ->where('p.tracks_stock', 1)
                    ->whereIn('p.type', ['simple', 'variable'])
                    ->lockForUpdate()
                    ->get(['pv.id', 'pv.sku']);

                if ($variants->isEmpty()) {
                    throw new StockTransferException(
                        'lines',
                        'Una o más variantes no existen, están inactivas o no controlan existencias físicas.',
                    );
                }

                if ($variants->count() > 1) {
                    throw new StockTransferException(
                        'lines',
                        'El SKU indicado coincide con más de una variante; use un SKU único.',
                    );
                }

                $variantId = (int) $variants->first()->id;

                if (isset($seenVariants[$variantId])) {
                    throw new StockTransferException(
                        'lines',
                        'No se puede repetir la misma variante en un traspaso.',
                    );
                }

                $seenVariants[$variantId] = true;
                $resolved[] = [
                    'index' => $index,
                    'variant_id' => $variantId,
                    'requested_qty' => $line['quantity'],
                ];
            }

            $transferId = (int) DB::table(self::TRANSFERS)->insertGetId([
                'number' => $this->draftNumber(),
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => 'draft',
                'notes' => $normalizedNotes,
            ]);

            foreach ($resolved as $line) {
                DB::table(self::ITEMS)->insert([
                    'transfer_id' => $transferId,
                    'variant_id' => $line['variant_id'],
                    'requested_qty' => InventoryDecimal::formatQuantity($line['requested_qty']),
                    'shipped_qty' => InventoryDecimal::formatQuantity(0),
                    'received_qty' => InventoryDecimal::formatQuantity(0),
                    'unit_cost_snapshot' => null,
                ]);
            }

            $totalRequested = array_sum(array_column($resolved, 'requested_qty'));

            $this->audit->record(
                'inventory.transfer_created',
                self::TRANSFERS,
                $transferId,
                $fromBranchId,
                null,
                [
                    'status' => 'draft',
                    'from_branch_id' => $fromBranchId,
                    'to_branch_id' => $toBranchId,
                    'line_count' => count($resolved),
                    'total_requested' => InventoryDecimal::formatQuantity($totalRequested),
                    'actor_user_id' => $actorId,
                ],
                'inventory',
            );

            return $transferId;
        }, 3);
    }

    /**
     * Reserva el numero canonico y pasa de borrador a solicitado. No mueve
     * existencias. Si la sucursal de destino quedo inactiva, falla y no se
     * consume el correlativo.
     */
    public function request(int $transferId, int $originBranchId, int $actorId): string
    {
        $transferId = $this->positiveIdentifier($transferId, 'transfer', 'El traspaso no es válido.');
        $originBranchId = $this->positiveIdentifier($originBranchId, 'branch', 'La sucursal de origen no es válida.');
        $actorId = $this->positiveIdentifier($actorId, 'actor', 'El usuario responsable no es válido.');

        return DB::transaction(function () use ($transferId, $originBranchId, $actorId): string {
            $this->guard->assertWritable();
            $transfer = $this->lockTransfer($transferId);

            if ((int) $transfer->from_branch_id !== $originBranchId) {
                throw new StockTransferException('transfer', 'Solo la sucursal de origen puede solicitar este traspaso.');
            }

            if ((string) $transfer->status !== 'draft') {
                throw new StockTransferException('transfer', 'Solo un borrador puede pasar a solicitado.');
            }

            $this->assertBranchesActive([
                (int) $transfer->from_branch_id,
                (int) $transfer->to_branch_id,
            ]);

            try {
                $number = $this->sequences->allocate('transfer', $originBranchId);
            } catch (InvalidArgumentException|DomainException) {
                throw new StockTransferException(
                    'transfer',
                    'No fue posible asignar el número del traspaso; revise el código de la sucursal e intente nuevamente.',
                );
            }

            DB::table(self::TRANSFERS)
                ->where('id', $transferId)
                ->update([
                    'number' => $number,
                    'status' => 'requested',
                    'requested_by_user_id' => $actorId,
                    'requested_at' => now()->format('Y-m-d H:i:s'),
                ]);

            $summary = $this->summary($transferId);

            $this->audit->record(
                'inventory.transfer_requested',
                self::TRANSFERS,
                $transferId,
                $originBranchId,
                null,
                [
                    'status' => 'requested',
                    'number' => $number,
                    'from_branch_id' => (int) $transfer->from_branch_id,
                    'to_branch_id' => (int) $transfer->to_branch_id,
                    'line_count' => $summary['line_count'],
                    'total_requested' => InventoryDecimal::formatQuantity($summary['requested']),
                    'actor_user_id' => $actorId,
                ],
                'inventory',
            );

            return $number;
        }, 3);
    }

    /**
     * Envia el traspaso completo: exige disponible suficiente en el origen y
     * agrega, por cada linea, un unico movimiento NEGATIVO `transfer_out`
     * source_type `stock_transfer_item`, source_id = item.id. Todo o nada.
     */
    public function dispatch(int $transferId, int $originBranchId, int $actorId): void
    {
        $transferId = $this->positiveIdentifier($transferId, 'transfer', 'El traspaso no es válido.');
        $originBranchId = $this->positiveIdentifier($originBranchId, 'branch', 'La sucursal de origen no es válida.');
        $actorId = $this->positiveIdentifier($actorId, 'actor', 'El usuario responsable no es válido.');

        DB::transaction(function () use ($transferId, $originBranchId, $actorId): void {
            $this->guard->assertWritable();
            $transfer = $this->lockTransfer($transferId);

            if ((int) $transfer->from_branch_id !== $originBranchId) {
                throw new StockTransferException('transfer', 'Solo la sucursal de origen puede enviar este traspaso.');
            }

            if ((string) $transfer->status !== 'requested') {
                throw new StockTransferException('transfer', 'Solo un traspaso solicitado puede enviarse.');
            }

            $this->assertBranchesActive([
                (int) $transfer->from_branch_id,
                (int) $transfer->to_branch_id,
            ]);

            $items = DB::table(self::ITEMS)
                ->where('transfer_id', $transferId)
                ->orderBy('variant_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'variant_id', 'requested_qty', 'shipped_qty']);

            if ($items->isEmpty()) {
                throw new StockTransferException('lines', 'El traspaso no tiene líneas para enviar.');
            }

            $this->assertVariantsDispatchable(
                $items->map(fn (object $item): int => (int) $item->variant_id)->all(),
            );

            $totalRequested = 0;

            foreach ($items as $item) {
                $requested = InventoryDecimal::quantityFromStorage($item->requested_qty);

                if ($requested <= 0) {
                    throw new StockTransferException('lines', 'Todas las líneas deben tener cantidad solicitada positiva.');
                }

                $stock = DB::table('current_stock')
                    ->where('branch_id', $originBranchId)
                    ->where('variant_id', (int) $item->variant_id)
                    ->lockForUpdate()
                    ->first(['on_hand', 'reserved', 'available']);

                $available = $stock === null
                    ? 0
                    : InventoryDecimal::quantityFromStorage($stock->available);

                if ($available < $requested) {
                    throw new StockTransferException(
                        'lines',
                        'No hay existencia disponible suficiente en la sucursal de origen para una o más variantes.',
                    );
                }

                $totalRequested += $requested;
            }

            foreach ($items as $item) {
                $requested = InventoryDecimal::quantityFromStorage($item->requested_qty);

                $this->ledger->recordMovement([
                    'branch_id' => $originBranchId,
                    'variant_id' => (int) $item->variant_id,
                    'type' => 'transfer_out',
                    'quantity' => InventoryDecimal::formatQuantity($requested * -1),
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => (int) $item->id,
                    'user_id' => $actorId,
                ]);

                DB::table(self::ITEMS)
                    ->where('id', (int) $item->id)
                    ->update(['shipped_qty' => InventoryDecimal::formatQuantity($requested)]);
            }

            DB::table(self::TRANSFERS)
                ->where('id', $transferId)
                ->update([
                    'status' => 'in_transit',
                    'shipped_by_user_id' => $actorId,
                    'shipped_at' => now()->format('Y-m-d H:i:s'),
                ]);

            $this->audit->record(
                'inventory.transfer_dispatched',
                self::TRANSFERS,
                $transferId,
                $originBranchId,
                null,
                [
                    'status' => 'in_transit',
                    'number' => $this->publicNumber($transfer),
                    'from_branch_id' => (int) $transfer->from_branch_id,
                    'to_branch_id' => (int) $transfer->to_branch_id,
                    'line_count' => $items->count(),
                    'total_shipped' => InventoryDecimal::formatQuantity($totalRequested),
                    'actor_user_id' => $actorId,
                ],
                'inventory',
            );
        }, 3);
    }

    /**
     * Recepcion declarada por linea. Solo las cantidades positivas generan un
     * movimiento POSITIVO `transfer_in` en destino. La diferencia enviada no
     * recibida queda pendiente y visible; no se compensa ni se resuelve sola.
     *
     * @param  list<array{item_id: mixed, received_qty: mixed}>  $receivedByItem
     */
    public function receive(
        int $transferId,
        int $destinationBranchId,
        int $actorId,
        array $receivedByItem,
    ): void {
        $transferId = $this->positiveIdentifier($transferId, 'transfer', 'El traspaso no es válido.');
        $destinationBranchId = $this->positiveIdentifier($destinationBranchId, 'branch', 'La sucursal de destino no es válida.');
        $actorId = $this->positiveIdentifier($actorId, 'actor', 'El usuario responsable no es válido.');

        DB::transaction(function () use ($transferId, $destinationBranchId, $actorId, $receivedByItem): void {
            $this->guard->assertWritable();
            $transfer = $this->lockTransfer($transferId);

            if ((int) $transfer->to_branch_id !== $destinationBranchId) {
                throw new StockTransferException('transfer', 'Solo la sucursal de destino puede recibir este traspaso.');
            }

            if ((string) $transfer->status !== 'in_transit') {
                throw new StockTransferException('transfer', 'Solo un traspaso en tránsito puede recibirse.');
            }

            $this->assertBranchesActive([
                (int) $transfer->from_branch_id,
                (int) $transfer->to_branch_id,
            ]);

            $items = DB::table(self::ITEMS)
                ->where('transfer_id', $transferId)
                ->orderBy('variant_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'variant_id', 'requested_qty', 'shipped_qty']);

            if ($items->isEmpty()) {
                throw new StockTransferException('lines', 'El traspaso no tiene líneas para recibir.');
            }

            $declared = $this->normalizeReceivedLines($receivedByItem);
            $itemIds = $items->map(fn (object $item): int => (int) $item->id)->all();

            foreach (array_keys($declared) as $declaredId) {
                if (! in_array($declaredId, $itemIds, true)) {
                    throw new StockTransferException('lines', 'La recepción incluye una línea que no pertenece al traspaso.');
                }
            }

            if (count($declared) !== count($itemIds)) {
                throw new StockTransferException('lines', 'Debe declarar una cantidad recibida para cada línea del traspaso.');
            }

            $totalReceived = 0;
            $totalPending = 0;
            $allReceived = true;

            foreach ($items as $item) {
                $itemId = (int) $item->id;

                if (! array_key_exists($itemId, $declared)) {
                    throw new StockTransferException('lines', 'Debe declarar una cantidad recibida para cada línea del traspaso.');
                }

                $shipped = InventoryDecimal::quantityFromStorage($item->shipped_qty);
                $received = $declared[$itemId];

                if ($received > $shipped) {
                    throw new StockTransferException('lines', 'La cantidad recibida no puede superar la cantidad enviada.');
                }

                if ($received > 0) {
                    $this->ledger->recordMovement([
                        'branch_id' => $destinationBranchId,
                        'variant_id' => (int) $item->variant_id,
                        'type' => 'transfer_in',
                        'quantity' => InventoryDecimal::formatQuantity($received),
                        'source_type' => self::SOURCE_TYPE,
                        'source_id' => $itemId,
                        'user_id' => $actorId,
                    ]);
                }

                DB::table(self::ITEMS)
                    ->where('id', $itemId)
                    ->update(['received_qty' => InventoryDecimal::formatQuantity($received)]);

                $totalReceived += $received;
                $totalPending += $shipped - $received;

                if ($received !== $shipped) {
                    $allReceived = false;
                }
            }

            $status = $allReceived ? 'received' : 'partially_received';

            DB::table(self::TRANSFERS)
                ->where('id', $transferId)
                ->update([
                    'status' => $status,
                    'received_by_user_id' => $actorId,
                    'received_at' => now()->format('Y-m-d H:i:s'),
                ]);

            $this->audit->record(
                'inventory.transfer_received',
                self::TRANSFERS,
                $transferId,
                $destinationBranchId,
                null,
                [
                    'status' => $status,
                    'number' => $this->publicNumber($transfer),
                    'from_branch_id' => (int) $transfer->from_branch_id,
                    'to_branch_id' => (int) $transfer->to_branch_id,
                    'line_count' => $items->count(),
                    'total_received' => InventoryDecimal::formatQuantity($totalReceived),
                    'total_pending' => InventoryDecimal::formatQuantity($totalPending),
                    'actor_user_id' => $actorId,
                ],
                'inventory',
            );
        }, 3);
    }

    /**
     * Cancela un borrador o solicitud sin efecto fisico. No se permite despues
     * del envio ni de una recepcion.
     */
    public function cancel(int $transferId, int $originBranchId, int $actorId): void
    {
        $transferId = $this->positiveIdentifier($transferId, 'transfer', 'El traspaso no es válido.');
        $originBranchId = $this->positiveIdentifier($originBranchId, 'branch', 'La sucursal de origen no es válida.');
        $actorId = $this->positiveIdentifier($actorId, 'actor', 'El usuario responsable no es válido.');

        DB::transaction(function () use ($transferId, $originBranchId, $actorId): void {
            $this->guard->assertWritable();
            $transfer = $this->lockTransfer($transferId);

            if ((int) $transfer->from_branch_id !== $originBranchId) {
                throw new StockTransferException('transfer', 'Solo la sucursal de origen puede cancelar este traspaso.');
            }

            if (! in_array((string) $transfer->status, ['draft', 'requested'], true)) {
                throw new StockTransferException('transfer', 'Solo un traspaso en borrador o solicitado puede cancelarse.');
            }

            $this->assertBranchesActive([$originBranchId]);

            DB::table(self::TRANSFERS)
                ->where('id', $transferId)
                ->update(['status' => 'cancelled']);

            $summary = $this->summary($transferId);

            $this->audit->record(
                'inventory.transfer_cancelled',
                self::TRANSFERS,
                $transferId,
                $originBranchId,
                null,
                [
                    'status' => 'cancelled',
                    'number' => $this->publicNumber($transfer),
                    'from_branch_id' => (int) $transfer->from_branch_id,
                    'to_branch_id' => (int) $transfer->to_branch_id,
                    'line_count' => $summary['line_count'],
                    'actor_user_id' => $actorId,
                ],
                'inventory',
            );
        }, 3);
    }

    /**
     * @return object{id: int, number: string, from_branch_id: int, to_branch_id: int, status: string}
     */
    private function lockTransfer(int $transferId): object
    {
        $transfer = DB::table(self::TRANSFERS)
            ->where('id', $transferId)
            ->lockForUpdate()
            ->first(['id', 'number', 'from_branch_id', 'to_branch_id', 'status']);

        if ($transfer === null) {
            throw new StockTransferException('transfer', 'El traspaso no existe.');
        }

        return $transfer;
    }

    /**
     * Bloquea las sucursales en orden ascendente y exige que esten activas y
     * no eliminadas logicamente.
     *
     * @param  list<int>  $branchIds
     */
    private function assertBranchesActive(array $branchIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $branchIds)));
        sort($ids);

        $rows = DB::table('branches')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'is_active', 'deleted_at']);

        if ($rows->count() !== count($ids)) {
            throw new StockTransferException('branch', 'Una de las sucursales del traspaso no existe.');
        }

        foreach ($rows as $row) {
            if ($row->deleted_at !== null || ! (bool) $row->is_active) {
                throw new StockTransferException('branch', 'Las sucursales del traspaso deben estar activas.');
            }
        }
    }

    /**
     * Un envio nuevo no puede operar sobre variantes/productos inactivos,
     * eliminados o sin control fisico de existencias. Las recepciones de
     * envios ya realizados no pasan por esta comprobacion para conservar el
     * historico.
     *
     * @param  list<int>  $variantIds
     */
    private function assertVariantsDispatchable(array $variantIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $variantIds)));
        sort($ids);

        $eligible = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereIn('pv.id', $ids)
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('pv.is_active', 1)
            ->where('p.is_active', 1)
            ->where('p.tracks_stock', 1)
            ->whereIn('p.type', ['simple', 'variable'])
            ->lockForUpdate()
            ->pluck('pv.id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($eligible !== $ids) {
            throw new StockTransferException(
                'lines',
                'Una o más variantes ya no están activas o no controlan existencias físicas; no se puede enviar el traspaso.',
            );
        }
    }

    /**
     * @param  list<array{sku: mixed, quantity: mixed}>  $lines
     * @return list<array{sku: string, quantity: int}>
     */
    private function normalizeRequestedLines(array $lines): array
    {
        if ($lines === [] || count($lines) > self::MAX_LINES) {
            throw new StockTransferException('lines', 'El traspaso debe incluir entre 1 y 100 líneas.');
        }

        $normalized = [];
        $seenSkus = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new StockTransferException('lines', 'Las líneas del traspaso no son válidas.');
            }

            $sku = $line['sku'] ?? null;

            if (! is_string($sku) || trim($sku) === '') {
                throw new StockTransferException('lines', 'Cada línea debe incluir un SKU.');
            }

            $sku = trim($sku);

            if (mb_strlen($sku) > 100) {
                throw new StockTransferException('lines', 'El SKU no puede superar 100 caracteres.');
            }

            $comparisonKey = mb_strtolower($sku);

            if (isset($seenSkus[$comparisonKey])) {
                throw new StockTransferException('lines', 'No se puede repetir un SKU en el mismo traspaso.');
            }

            $seenSkus[$comparisonKey] = true;

            $quantity = $this->parseQuantity($line['quantity'] ?? null, 'lines', 'La cantidad solicitada');

            if ($quantity <= 0) {
                throw new StockTransferException('lines', 'La cantidad solicitada debe ser mayor que cero.');
            }

            $normalized[] = ['sku' => $sku, 'quantity' => $quantity];
        }

        return $normalized;
    }

    /**
     * @param  list<array{item_id: mixed, received_qty: mixed}>  $lines
     * @return array<int, int>
     */
    private function normalizeReceivedLines(array $lines): array
    {
        if ($lines === [] || count($lines) > self::MAX_LINES) {
            throw new StockTransferException('lines', 'La recepción debe incluir una línea por ítem del traspaso.');
        }

        $declared = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new StockTransferException('lines', 'Las líneas de recepción no son válidas.');
            }

            $itemId = $this->positiveIdentifier(
                $line['item_id'] ?? null,
                'lines',
                'La línea de recepción no es válida.',
            );

            if (array_key_exists($itemId, $declared)) {
                throw new StockTransferException('lines', 'No se puede declarar dos veces la misma línea.');
            }

            $received = $this->parseQuantity(
                $line['received_qty'] ?? null,
                'lines',
                'La cantidad recibida',
            );

            if ($received < 0) {
                throw new StockTransferException('lines', 'La cantidad recibida no puede ser negativa.');
            }

            $declared[$itemId] = $received;
        }

        return $declared;
    }

    private function parseQuantity(mixed $value, string $field, string $label): int
    {
        try {
            return InventoryDecimal::quantity($value);
        } catch (InvalidArgumentException) {
            throw new StockTransferException(
                $field,
                "{$label} debe ser un decimal de hasta 9 enteros y 3 decimales.",
            );
        }
    }

    private function positiveIdentifier(mixed $value, string $field, string $message): int
    {
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;
        } else {
            throw new StockTransferException($field, $message);
        }

        if ($int <= 0) {
            throw new StockTransferException($field, $message);
        }

        return $int;
    }

    private function normalizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim($notes);

        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > 500) {
            throw new StockTransferException('notes', 'Las notas no pueden superar 500 caracteres.');
        }

        return $notes;
    }

    private function draftNumber(): string
    {
        return self::DRAFT_PREFIX.strtoupper(bin2hex(random_bytes(12)));
    }

    private function publicNumber(object $transfer): ?string
    {
        if ((string) $transfer->status === 'draft') {
            return null;
        }

        $number = (string) $transfer->number;

        return str_starts_with($number, self::DRAFT_PREFIX) ? null : $number;
    }

    /**
     * @return array{line_count: int, requested: int}
     */
    private function summary(int $transferId): array
    {
        $rows = DB::table(self::ITEMS)
            ->where('transfer_id', $transferId)
            ->get(['requested_qty']);

        $requested = 0;

        foreach ($rows as $row) {
            $requested += InventoryDecimal::quantityFromStorage($row->requested_qty);
        }

        return ['line_count' => $rows->count(), 'requested' => $requested];
    }
}
