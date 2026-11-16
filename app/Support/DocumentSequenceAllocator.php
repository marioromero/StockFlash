<?php

namespace App\Support;

use App\Models\Branch;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Asignación de numeración transaccional por ámbito (CORE-13 / RN-74).
 *
 * Usa únicamente la tabla ya migrada `document_sequences` (Parte 2 §4.14) y no
 * expone rutas, controladores ni UI: es un servicio interno para POS, Bodega y
 * Compras.
 *
 * Los tipos `ticket`, `sale`, `return`, `transfer`, `count`, `goods_receipt` y
 * `cash_session` numeran por sucursal (`scope_type=branch`, `scope_id=branches.id`);
 * `purchase_order` numera por instalación (`scope_type=installation`, `scope_id=0`).
 * `transfer` debe recibir explícitamente la sucursal de origen.
 *
 * Debe invocarse dentro de la transacción del documento receptor. La reserva
 * usa una transacción anidada (savepoint en MariaDB/SQLite) y `lockForUpdate`;
 * si el receptor revierte antes del commit exterior, la fila y el contador se
 * revierten con él. Un rollback consolidado después del commit no puede
 * revertir la numeración: se garantiza unicidad y ausencia de duplicados, no la
 * ausencia total de saltos.
 */
class DocumentSequenceAllocator
{
    /** @var list<string> */
    private const BRANCH_TYPES = [
        'ticket',
        'sale',
        'return',
        'transfer',
        'count',
        'goods_receipt',
        'cash_session',
    ];

    /** @var list<string> */
    private const INSTALLATION_TYPES = ['purchase_order'];

    private const DEFAULT_PADDING = 6;

    private const MAX_PREFIX_LENGTH = 10;

    private const MIN_PADDING = 1;

    private const MAX_PADDING = 18;

    /**
     * Reserva el siguiente número visible de un ámbito y persiste el contador.
     *
     * `$prefixOverride` se usa solo al crear la fila (por ejemplo, si el código
     * de la sucursal supera los 10 caracteres de `prefix`). Las filas existentes
     * conservan siempre su `prefix`, `padding` y correlativo.
     *
     * @return array{doc_type: string, scope_type: string, scope_id: int, number: int, visible: string}
     *
     * @throws InvalidArgumentException Cuando el tipo, la sucursal o el prefijo no son válidos.
     * @throws DomainException Cuando la fila existente está corrupta o no queda capacidad numérica.
     */
    public function reserve(string $docType, ?int $branchId = null, ?string $prefixOverride = null): array
    {
        [$scopeType, $scopeId, $newPrefix] = $this->resolveScope($docType, $branchId, $prefixOverride);

        return DB::transaction(function () use ($docType, $scopeType, $scopeId, $newPrefix): array {
            $timestamp = now();

            DB::table('document_sequences')->insertOrIgnore([
                'doc_type' => $docType,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'prefix' => $newPrefix,
                'next_number' => 1,
                'padding' => self::DEFAULT_PADDING,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $row = DB::table('document_sequences')
                ->where('doc_type', $docType)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new DomainException('Document sequence row missing after idempotent insert.');
            }

            $prefix = $this->existingPrefix($row->prefix ?? null);
            $padding = $this->existingPadding($row->padding ?? null);
            $number = $this->existingNextNumber($row->next_number ?? null, $padding);

            $visible = $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

            if ($visible === '' || strlen($visible) !== strlen($prefix) + $padding) {
                throw new DomainException('Document sequence formatted number is inconsistent.');
            }

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update([
                    'next_number' => $number + 1,
                    'updated_at' => $timestamp,
                ]);

            return [
                'doc_type' => $docType,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'number' => $number,
                'visible' => $visible,
            ];
        }, 3);
    }

    /**
     * Reserva el siguiente número y devuelve solo el valor visible formateado.
     *
     * @throws InvalidArgumentException Cuando el tipo, la sucursal o el prefijo no son válidos.
     * @throws DomainException Cuando la fila existente está corrupta o no queda capacidad numérica.
     */
    public function allocate(string $docType, ?int $branchId = null, ?string $prefixOverride = null): string
    {
        return $this->reserve($docType, $branchId, $prefixOverride)['visible'];
    }

    /**
     * @return array{0: string, 1: int, 2: string}
     */
    private function resolveScope(string $docType, ?int $branchId, ?string $prefixOverride): array
    {
        $isBranchType = in_array($docType, self::BRANCH_TYPES, true);
        $isInstallationType = in_array($docType, self::INSTALLATION_TYPES, true);

        if (! $isBranchType && ! $isInstallationType) {
            throw new InvalidArgumentException("Unsupported document type: {$docType}.");
        }

        $override = $this->validatedPrefixOverride($prefixOverride);

        if ($isInstallationType) {
            if ($branchId !== null && $branchId !== 0) {
                throw new InvalidArgumentException('Installation scoped document type does not accept a branch.');
            }

            return ['installation', 0, $override ?? ''];
        }

        if ($branchId === null || $branchId <= 0) {
            throw new InvalidArgumentException('Branch scoped document type requires a positive branch id.');
        }

        $branch = Branch::query()->whereKey($branchId)->first();

        if ($branch === null) {
            throw new InvalidArgumentException("Branch {$branchId} does not exist.");
        }

        if ($override !== null) {
            return ['branch', $branchId, $override];
        }

        $code = (string) $branch->code;

        if (strlen($code) > self::MAX_PREFIX_LENGTH) {
            throw new InvalidArgumentException('Branch code exceeds the prefix capacity; provide a prefixOverride.');
        }

        return ['branch', $branchId, $code];
    }

    private function validatedPrefixOverride(?string $prefixOverride): ?string
    {
        if ($prefixOverride === null) {
            return null;
        }

        $length = strlen($prefixOverride);

        if ($length < 1 || $length > self::MAX_PREFIX_LENGTH || preg_match('/[\s\x00-\x1F\x7F]/', $prefixOverride) === 1) {
            throw new InvalidArgumentException('prefixOverride must be 1..10 characters without whitespace or control characters.');
        }

        return $prefixOverride;
    }

    private function existingPrefix(mixed $prefix): string
    {
        if (! is_string($prefix) || strlen($prefix) > self::MAX_PREFIX_LENGTH) {
            throw new DomainException('document_sequences.prefix is corrupted.');
        }

        return $prefix;
    }

    private function existingPadding(mixed $padding): int
    {
        $value = $this->integerValue($padding, 'padding');

        if ($value < self::MIN_PADDING || $value > self::MAX_PADDING) {
            throw new DomainException('document_sequences.padding is out of range.');
        }

        return $value;
    }

    private function existingNextNumber(mixed $nextNumber, int $padding): int
    {
        $value = $this->integerValue($nextNumber, 'next_number');
        $maximum = (10 ** $padding) - 1;

        if ($value < 1 || $value > $maximum) {
            throw new DomainException('document_sequences.next_number is out of range or exhausted.');
        }

        return $value;
    }

    private function integerValue(mixed $value, string $column): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        throw new DomainException("document_sequences.{$column} is corrupted.");
    }
}
