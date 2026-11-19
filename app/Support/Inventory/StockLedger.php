<?php

namespace App\Support\Inventory;

use App\Models\StockMovement;
use App\Support\AuditLogger;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * API interna unica del kardex fisico (INV-01/INV-02, RN-19/RN-21).
 *
 * Cada publicacion es UNA transaccion:
 * 1. valida licencia (lock de `license_states`),
 * 2. crea la fila `current_stock` si no existe (insertOrIgnore),
 * 3. bloquea esa fila (lockForUpdate; en SQLite es no-op y la transaccion
 *    serializa las escrituras de la conexion de prueba),
 * 3b. si se informa `$underLock`, lo ejecuta tras la guarda de duplicados y
 *    antes de calcular el saldo; si lanza, revierte toda la transaccion,
 * 4. calcula el nuevo `on_hand` exclusivamente desde la fila bloqueada,
 * 5. actualiza el cache y agrega el movimiento append-only con `balance_after`,
 * 6. registra auditoria no PII,
 * 7. confirma. Cualquier fallo revierte cache, kardex y auditoria.
 *
 * Idempotencia de origen: si `source_type` + `source_id` vienen informados, se
 * rechaza un segundo movimiento con la misma clave
 * (source_type, source_id, branch_id, variant_id, type) dentro de la misma
 * transaccion y bajo el bloqueo de la fila. No se agrega un indice unico no
 * aprobado en base de datos.
 *
 * Esta ola no expone rutas de escritura: el servicio es interno. Las reservas
 * no generan movimientos y no se aceptan valores de reserva desde el cliente.
 */
final class StockLedger
{
    /** Tipos fisicos que pueden publicarse con recordMovement(). */
    public const RECORDABLE_TYPES = [
        'initial',
        'purchase_receipt',
        'adjustment',
        'count_adjustment',
        'waste',
        'sale_pos',
        'sale_online',
        'customer_return',
        'supplier_return',
        'transfer_out',
        'transfer_in',
    ];

    public const ALL_TYPES = [
        ...self::RECORDABLE_TYPES,
        'reversal',
    ];

    private const ALLOWED_KEYS = [
        'branch_id', 'variant_id', 'type', 'quantity', 'unit_cost',
        'source_type', 'source_id', 'user_id', 'reason_code_id', 'note',
        'occurred_at',
    ];

    public function __construct(
        private readonly InventoryWriteGuard $guard,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  (callable(): mixed)|null  $underLock
     */
    public function recordMovement(array $data, ?callable $underLock = null): StockMovement
    {
        $movement = $this->normalizeRecordableMovement($data);

        return DB::transaction(function () use ($movement, $underLock): StockMovement {
            $this->guard->assertWritable();

            [$branchId, $variantId] = [$movement['branch_id'], $movement['variant_id']];
            $occurredAt = $movement['occurred_at'];

            $this->ensureCurrentStockRow($branchId, $variantId);

            $stock = DB::table('current_stock')
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                throw StockLedgerException::inconsistentStockRow();
            }

            if ($movement['source_type'] !== null) {
                $this->assertSourceNotPosted($movement);
            }

            // Hook bajo el lock autoritativo de la fila de stock, antes de
            // leer/escribir el saldo. Rechaza invariantes que dependen del
            // historial (p. ej. "sin movimientos previos" para INITIAL); si
            // lanza, toda la transaccion revierte.
            if ($underLock !== null) {
                $underLock();
            }

            $balance = InventoryDecimal::addQuantity(
                InventoryDecimal::quantityFromStorage($stock->on_hand),
                $movement['quantity'],
            );

            $this->writeStockRow($branchId, $variantId, $balance, $occurredAt);

            $movementId = $this->insertMovement([
                'branch_id' => $branchId,
                'variant_id' => $variantId,
                'type' => $movement['type'],
                'quantity' => $movement['quantity'],
                'balance_after' => $balance,
                'unit_cost' => $movement['unit_cost'],
                'source_type' => $movement['source_type'],
                'source_id' => $movement['source_id'],
                'reverses_movement_id' => null,
                'user_id' => $movement['user_id'],
                'reason_code_id' => $movement['reason_code_id'],
                'note' => $movement['note'],
                'occurred_at' => $occurredAt,
            ]);

            $this->auditLogger->record(
                'inventory.movement_recorded',
                'stock_movements',
                $movementId,
                $branchId,
                null,
                $this->auditValues($movement['type'], $movement['quantity'], $balance, $movement),
                'inventory',
            );

            return StockMovement::query()->findOrFail($movementId);
        }, 3);
    }

    /**
     * Agrega el movimiento inverso y nunca modifica el original.
     */
    public function reverseMovement(
        int $movementId,
        int $userId,
        ?int $reasonCodeId = null,
        ?string $note = null,
        ?DateTimeInterface $occurredAt = null,
    ): StockMovement {
        if ($movementId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('El movimiento y el usuario deben ser identificadores válidos.');
        }

        if ($reasonCodeId !== null && $reasonCodeId <= 0) {
            throw new InvalidArgumentException('El motivo debe ser un identificador válido.');
        }

        if ($note !== null && mb_strlen($note) > 255) {
            throw new InvalidArgumentException('La nota del movimiento no puede superar 255 caracteres.');
        }

        $occurred = $this->normalizeOccurredAt($occurredAt);

        return DB::transaction(function () use ($movementId, $userId, $reasonCodeId, $note, $occurred): StockMovement {
            $this->guard->assertWritable();

            $original = DB::table('stock_movements')
                ->where('id', $movementId)
                ->lockForUpdate()
                ->first();

            if ($original === null) {
                throw StockLedgerException::movementNotFound($movementId);
            }

            if ((string) $original->type === 'reversal') {
                throw StockLedgerException::reversalOfReversal();
            }

            $alreadyReversed = DB::table('stock_movements')
                ->where('type', 'reversal')
                ->where('reverses_movement_id', $movementId)
                ->exists();

            if ($alreadyReversed) {
                throw StockLedgerException::alreadyReversed($movementId);
            }

            $branchId = (int) $original->branch_id;
            $variantId = (int) $original->variant_id;

            $this->ensureCurrentStockRow($branchId, $variantId);

            $stock = DB::table('current_stock')
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                throw StockLedgerException::inconsistentStockRow();
            }

            $originalQuantity = InventoryDecimal::quantityFromStorage($original->quantity);
            $inverse = $originalQuantity * -1;
            InventoryDecimal::assertNonZeroQuantity($inverse);

            $balance = InventoryDecimal::addQuantity(
                InventoryDecimal::quantityFromStorage($stock->on_hand),
                $inverse,
            );

            $this->writeStockRow($branchId, $variantId, $balance, $occurred);

            $reversalId = $this->insertMovement([
                'branch_id' => $branchId,
                'variant_id' => $variantId,
                'type' => 'reversal',
                'quantity' => $inverse,
                'balance_after' => $balance,
                'unit_cost' => InventoryDecimal::costFromStorage($original->unit_cost),
                'source_type' => null,
                'source_id' => null,
                'reverses_movement_id' => $movementId,
                'user_id' => $userId,
                'reason_code_id' => $reasonCodeId,
                'note' => $note,
                'occurred_at' => $occurred,
            ]);

            $this->auditLogger->record(
                'inventory.movement_reversed',
                'stock_movements',
                $reversalId,
                $branchId,
                null,
                [
                    'movement_type' => 'reversal',
                    'reverses_movement_id' => $movementId,
                    'quantity' => InventoryDecimal::formatQuantity($inverse),
                    'balance_after' => InventoryDecimal::formatQuantity($balance),
                ],
                'inventory',
            );

            return StockMovement::query()->findOrFail($reversalId);
        }, 3);
    }

    private function ensureCurrentStockRow(int $branchId, int $variantId): void
    {
        DB::table('current_stock')->insertOrIgnore([
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'on_hand' => InventoryDecimal::formatQuantity(0),
            'reserved' => InventoryDecimal::formatQuantity(0),
            'last_movement_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function writeStockRow(int $branchId, int $variantId, int $balance, string $occurredAt): void
    {
        DB::table('current_stock')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->update([
                'on_hand' => InventoryDecimal::formatQuantity($balance),
                'last_movement_at' => $occurredAt,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $movement
     */
    private function assertSourceNotPosted(array $movement): void
    {
        $exists = DB::table('stock_movements')
            ->where('source_type', $movement['source_type'])
            ->where('source_id', $movement['source_id'])
            ->where('branch_id', $movement['branch_id'])
            ->where('variant_id', $movement['variant_id'])
            ->where('type', $movement['type'])
            ->exists();

        if ($exists) {
            throw new DuplicateStockMovementException(
                (string) $movement['source_type'],
                (int) $movement['source_id'],
                (int) $movement['branch_id'],
                (int) $movement['variant_id'],
                (string) $movement['type'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertMovement(array $row): int
    {
        return (int) DB::table('stock_movements')->insertGetId([
            'branch_id' => $row['branch_id'],
            'variant_id' => $row['variant_id'],
            'type' => $row['type'],
            'quantity' => InventoryDecimal::formatQuantity((int) $row['quantity']),
            'balance_after' => InventoryDecimal::formatQuantity((int) $row['balance_after']),
            'unit_cost' => $row['unit_cost'] === null
                ? null
                : InventoryDecimal::formatCost((int) $row['unit_cost']),
            'source_type' => $row['source_type'],
            'source_id' => $row['source_id'],
            'reservation_id' => null,
            'reverses_movement_id' => $row['reverses_movement_id'],
            'user_id' => $row['user_id'],
            'reason_code_id' => $row['reason_code_id'],
            'note' => $row['note'],
            'occurred_at' => $row['occurred_at'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRecordableMovement(array $data): array
    {
        $unknown = array_diff(array_keys($data), self::ALLOWED_KEYS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'El movimiento contiene campos no permitidos: '.implode(', ', $unknown).'.'
            );
        }

        $type = (string) ($data['type'] ?? '');

        if (! in_array($type, self::RECORDABLE_TYPES, true)) {
            throw new InvalidArgumentException('El tipo de movimiento no es válido para el kardex físico.');
        }

        $branchId = $this->positiveInt($data['branch_id'] ?? null, 'branch_id');
        $variantId = $this->positiveInt($data['variant_id'] ?? null, 'variant_id');
        $quantity = InventoryDecimal::quantity($data['quantity'] ?? null);
        InventoryDecimal::assertNonZeroQuantity($quantity);

        $unitCost = array_key_exists('unit_cost', $data) && $data['unit_cost'] !== null
            ? InventoryDecimal::cost($data['unit_cost'])
            : null;

        $sourceType = $this->nullableString($data['source_type'] ?? null, 50, 'source_type');
        $sourceId = $this->nullablePositiveInt($data['source_id'] ?? null, 'source_id');

        if (($sourceType === null) !== ($sourceId === null)) {
            throw new InvalidArgumentException('source_type y source_id deben informarse juntos o ambos nulos.');
        }

        $userId = $this->nullablePositiveInt($data['user_id'] ?? null, 'user_id');
        $reasonCodeId = $this->nullablePositiveInt($data['reason_code_id'] ?? null, 'reason_code_id');
        $note = $this->nullableString($data['note'] ?? null, 255, 'note');
        $occurredAt = $this->normalizeOccurredAt($data['occurred_at'] ?? null);

        return [
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'user_id' => $userId,
            'reason_code_id' => $reasonCodeId,
            'note' => $note,
            'occurred_at' => $occurredAt,
        ];
    }

    private function normalizeOccurredAt(mixed $value): string
    {
        if ($value === null) {
            return now()->format('Y-m-d H:i:s');
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                throw new InvalidArgumentException('occurred_at no es una fecha válida.');
            }
        }

        throw new InvalidArgumentException('occurred_at no es una fecha válida.');
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            throw new InvalidArgumentException("{$field} debe ser un identificador válido.");
        }

        $int = (int) $value;

        if ($int <= 0) {
            throw new InvalidArgumentException("{$field} debe ser un identificador válido.");
        }

        return $int;
    }

    private function nullablePositiveInt(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->positiveInt($value, $field);
    }

    private function nullableString(mixed $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} debe ser texto.");
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$field} no puede superar {$max} caracteres.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $movement
     * @return array<string, mixed>
     */
    private function auditValues(string $type, int $quantity, int $balance, array $movement): array
    {
        return [
            'movement_type' => $type,
            'quantity' => InventoryDecimal::formatQuantity($quantity),
            'balance_after' => InventoryDecimal::formatQuantity($balance),
            'source_type' => $movement['source_type'],
            'source_id' => $movement['source_id'],
        ];
    }
}
