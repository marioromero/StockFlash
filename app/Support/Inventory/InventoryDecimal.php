<?php

namespace App\Support\Inventory;

use InvalidArgumentException;

/**
 * Aritmetica decimal exacta para inventario (INVCORE-001).
 *
 * No usa floats ni bcmath: las cantidades DECIMAL(12,3) se representan como
 * enteros en milésimas y los costos DECIMAL(14,4) como enteros en diezmilésimas.
 * La validacion es estricta por expresion regular y rango; no acepta notacion
 * cientifica, comas, vacios, NaN ni infinities.
 *
 * SQLite guarda DECIMAL con afinidad NUMERIC y puede devolver int/float al
 * leer. `*FromStorage()` normaliza ese valor de transporte a texto con la
 * escala contractual y luego aplica el mismo parser estricto. Ninguna suma,
 * resta ni comparacion se hace en coma flotante.
 */
final class InventoryDecimal
{
    public const QUANTITY_SCALE = 3;

    public const QUANTITY_FACTOR = 1000;

    /** DECIMAL(12,3): 9 digitos enteros y 3 decimales. */
    public const QUANTITY_MAX_ABS = 999_999_999_999;

    public const COST_SCALE = 4;

    public const COST_FACTOR = 10_000;

    /** DECIMAL(14,4): 10 digitos enteros y 4 decimales. */
    public const COST_MAX_ABS = 99_999_999_999_999;

    public static function quantity(mixed $value): int
    {
        return self::toScaled($value, self::QUANTITY_SCALE, self::QUANTITY_MAX_ABS, 'cantidad');
    }

    public static function cost(mixed $value): int
    {
        $scaled = self::toScaled($value, self::COST_SCALE, self::COST_MAX_ABS, 'costo');

        if ($scaled < 0) {
            throw new InvalidArgumentException('El costo no puede ser negativo.');
        }

        return $scaled;
    }

    public static function quantityFromStorage(mixed $value): int
    {
        return self::storageToScaled($value, self::QUANTITY_SCALE, self::QUANTITY_MAX_ABS, 'cantidad');
    }

    public static function costFromStorage(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::storageToScaled($value, self::COST_SCALE, self::COST_MAX_ABS, 'costo');
    }

    public static function formatQuantity(int $scaled): string
    {
        return self::formatScaled($scaled, self::QUANTITY_SCALE);
    }

    public static function formatCost(int $scaled): string
    {
        return self::formatScaled($scaled, self::COST_SCALE);
    }

    /**
     * Suma exacta con verificacion de rango DECIMAL(12,3).
     */
    public static function addQuantity(int $left, int $right): int
    {
        if (
            ($right > 0 && $left > self::QUANTITY_MAX_ABS - $right)
            || ($right < 0 && $left < -self::QUANTITY_MAX_ABS - $right)
        ) {
            throw new InvalidArgumentException('La existencia resultante excede el rango DECIMAL(12,3).');
        }

        return $left + $right;
    }

    public static function assertNonZeroQuantity(int $scaled): void
    {
        if ($scaled === 0) {
            throw new InvalidArgumentException('La cantidad del movimiento no puede ser cero.');
        }
    }

    private static function toScaled(mixed $value, int $scale, int $maxAbs, string $label): int
    {
        if (is_int($value)) {
            $factor = 10 ** $scale;
            $limit = intdiv($maxAbs, $factor);

            if ($value > $limit || $value < -$limit) {
                throw new InvalidArgumentException("El {$label} excede el rango permitido.");
            }

            return $value * $factor;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException("El {$label} debe enviarse como texto o entero, no como número decimal flotante.");
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("El {$label} debe ser numérico.");
        }

        $raw = trim($value);
        $integerDigits = $scale === self::QUANTITY_SCALE ? 9 : 10;
        $pattern = '/^-?\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/';

        if (preg_match($pattern, $raw) !== 1) {
            throw new InvalidArgumentException("El {$label} no tiene un formato decimal válido.");
        }

        $negative = str_starts_with($raw, '-');
        $unsigned = $negative ? substr($raw, 1) : $raw;
        $parts = explode('.', $unsigned, 2);
        $whole = $parts[0];
        $fraction = str_pad($parts[1] ?? '', $scale, '0');

        $scaled = ((int) $whole) * (10 ** $scale) + (int) $fraction;

        if ($negative) {
            $scaled *= -1;
        }

        if (abs($scaled) > $maxAbs) {
            throw new InvalidArgumentException("El {$label} excede el rango permitido.");
        }

        return $scaled;
    }

    private static function storageToScaled(mixed $value, int $scale, int $maxAbs, string $label): int
    {
        if (is_float($value)) {
            if (! is_finite($value) || abs($value) > $maxAbs / (10 ** $scale)) {
                throw new InvalidArgumentException("El {$label} almacenado excede el rango permitido.");
            }

            return self::toScaled(sprintf('%.'.$scale.'F', $value), $scale, $maxAbs, $label);
        }

        return self::toScaled($value, $scale, $maxAbs, $label);
    }

    private static function formatScaled(int $scaled, int $scale): string
    {
        $factor = 10 ** $scale;
        $sign = $scaled < 0 ? '-' : '';
        $absolute = abs($scaled);
        $whole = intdiv($absolute, $factor);
        $fraction = $absolute % $factor;

        return $sign.$whole.'.'.str_pad((string) $fraction, $scale, '0', STR_PAD_LEFT);
    }
}
