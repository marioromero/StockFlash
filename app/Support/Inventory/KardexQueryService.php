<?php

namespace App\Support\Inventory;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

/**
 * Reporte Kardex fisico por sucursal (INV-08, RN-19/RN-21, Parte 2 §7.4).
 *
 * Lee exclusivamente `stock_movements` unido a `product_variants` y
 * `products`. No aplica filtros de borrado logico ni de actividad: los
 * movimientos historicos de variantes inactivas o eliminadas deben seguir
 * visibles. El saldo se muestra tal como quedo registrado (`balance_after`);
 * nunca se recalcula ni se ordena por `occurred_at`. El orden es el de
 * anexado del ledger (`stock_movements.id` descendente).
 *
 * Solo se seleccionan columnas no sensibles: identificador, fecha, tipo,
 * cantidad, saldo, reversa, variante, SKU y nombre de producto. No se leen
 * costos, precios, margenes, usuarios, autorizadores, PIN ni datos personales.
 */
final class KardexQueryService
{
    /** Clave del cursor de Laravel: debe coincidir con el ORDER BY. */
    private const CURSOR_KEY = 'sm.id';

    private const TYPE_LABELS = [
        'initial' => 'Carga inicial',
        'purchase_receipt' => 'Recepción de compra',
        'adjustment' => 'Ajuste',
        'count_adjustment' => 'Ajuste por conteo',
        'waste' => 'Merma',
        'sale_pos' => 'Venta POS',
        'sale_online' => 'Venta online',
        'customer_return' => 'Devolución de cliente',
        'supplier_return' => 'Devolución a proveedor',
        'transfer_out' => 'Traspaso enviado',
        'transfer_in' => 'Traspaso recibido',
        'reversal' => 'Corrección (reversa)',
    ];

    public function paginate(
        int $branchId,
        string $search = '',
        ?int $variantId = null,
        ?string $from = null,
        ?string $to = null,
        int $perPage = 25,
        ?string $cursor = null,
    ): CursorPaginator {
        $search = mb_substr(trim($search), 0, 100);
        $perPage = max(1, min(100, $perPage));

        $this->assertValidCursor($cursor);

        $query = DB::table('stock_movements as sm')
            ->join('product_variants as pv', 'pv.id', '=', 'sm.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('sm.branch_id', $branchId)
            ->select([
                'sm.id as movement_id',
                'sm.occurred_at',
                'sm.type',
                'sm.quantity',
                'sm.balance_after',
                'sm.reverses_movement_id',
                'sm.variant_id',
                'pv.sku',
                'p.name as product_name',
            ])
            ->orderBy('sm.id', 'desc');

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($query) use ($like): void {
                $query->where('pv.sku', 'like', $like)
                    ->orWhere('p.name', 'like', $like);
            });
        }

        if ($variantId !== null) {
            $query->where('sm.variant_id', $variantId);
        }

        if ($from !== null) {
            $query->where(
                'sm.occurred_at',
                '>=',
                Carbon::parse($from, config('app.timezone'))->startOfDay()->format('Y-m-d H:i:s'),
            );
        }

        if ($to !== null) {
            $query->where(
                'sm.occurred_at',
                '<=',
                Carbon::parse($to, config('app.timezone'))->endOfDay()->format('Y-m-d H:i:s'),
            );
        }

        try {
            $paginator = $query
                ->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
                ->withQueryString();
        } catch (UnexpectedValueException) {
            throw $this->invalidCursor();
        }

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => [
                'id' => (int) $row->movement_id,
                'occurred_at' => (string) $row->occurred_at,
                'type' => (string) $row->type,
                'type_label' => self::TYPE_LABELS[(string) $row->type] ?? (string) $row->type,
                'quantity' => InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->quantity),
                ),
                'balance_after' => InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->balance_after),
                ),
                'reverses_movement_id' => $row->reverses_movement_id === null
                    ? null
                    : (int) $row->reverses_movement_id,
                'is_reversal' => $row->reverses_movement_id !== null,
                'variant_id' => (int) $row->variant_id,
                'sku' => (string) $row->sku,
                'product_name' => (string) $row->product_name,
            ]),
        );

        return $paginator;
    }

    /**
     * Valida el cursor antes de consultar. Un cursor corrupto o fabricado debe
     * responder 422 y no provocar una consulta amplia ni un error 500.
     */
    private function assertValidCursor(?string $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        $decoded = Cursor::fromEncoded($cursor);

        if ($decoded === null) {
            throw $this->invalidCursor();
        }

        $parameters = $decoded->toArray();

        if (! array_key_exists(self::CURSOR_KEY, $parameters)) {
            throw $this->invalidCursor();
        }

        $rawId = $parameters[self::CURSOR_KEY];

        if (! is_int($rawId) && ! (is_string($rawId) && ctype_digit($rawId))) {
            throw $this->invalidCursor();
        }

        $id = filter_var($rawId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);

        if ($id === false) {
            throw $this->invalidCursor();
        }
    }

    private function invalidCursor(): ValidationException
    {
        return ValidationException::withMessages([
            'cursor' => 'El cursor de paginación no es válido; vuelva a abrir el reporte.',
        ]);
    }
}
