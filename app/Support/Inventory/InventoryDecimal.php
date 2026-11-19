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

    /** Escala del producto cantidad (3) * costo (4). */
    public const PRODUCT_SCALE = 7;

    /** Maximo de decimales aceptado en el umbral de ajuste configurado. */
    public const THRESHOLD_SCALE = 7;

    /** Maximo de digitos enteros aceptado en el umbral configurado. */
    public const THRESHOLD_INTEGER_DIGITS = 18;

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

    /**
     * Producto exacto `cantidad * costo` expresado con 7 decimales.
     *
     * Los argumentos deben venir ya escalados y no negativos (milésimas y
     * diezmilésimas). La multiplicación se hace con dígitos enteros como texto
     * para no desbordar enteros de 64 bits ni usar floats/bcmath.
     *
     * @return string Decimal con exactamente 7 decimales, por ejemplo "1001.0000000".
     */
    public static function multiplyQuantityByCost(int $quantityScaled, int $costScaled): string
    {
        if ($quantityScaled < 0 || $costScaled < 0) {
            throw new InvalidArgumentException('El producto de cantidad y costo no admite valores negativos.');
        }

        $digits = self::multiplyUnsignedDigits((string) $quantityScaled, (string) $costScaled);

        return self::formatUnsignedScaledDigits($digits, self::PRODUCT_SCALE);
    }

    /**
     * Compara `abs(cantidad) * costo` (escala 7) contra un umbral decimal.
     *
     * Devuelve -1 si el producto es menor, 0 si es igual y 1 si es mayor. El
     * umbral se valida como decimal no negativo estricto y se escala a 7
     * decimales; un valor igual al umbral NO lo supera (comparación estricta
     * se resuelve en el llamador con `> 0`).
     */
    public static function compareProductToThreshold(
        int $quantityScaledAbs,
        int $costScaled,
        mixed $threshold,
    ): int {
        if ($quantityScaledAbs < 0 || $costScaled < 0) {
            throw new InvalidArgumentException('La comparación de valor no admite cantidad ni costo negativos.');
        }

        $thresholdDigits = self::thresholdToScaledDigits($threshold);
        $productDigits = self::multiplyUnsignedDigits((string) $quantityScaledAbs, (string) $costScaled);

        return self::compareUnsignedDigits($productDigits, $thresholdDigits);
    }

    /**
     * @return string Dígitos enteros sin ceros a la izquierda del umbral escalado a 7 decimales.
     */
    private static function thresholdToScaledDigits(mixed $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('El umbral configurado no puede ser negativo.');
            }

            $whole = (string) $value;
            $fraction = '';
        } elseif (is_string($value)) {
            $raw = trim($value);
            $pattern = '/^\d{1,'.self::THRESHOLD_INTEGER_DIGITS.'}(?:\.(\d{1,'.self::THRESHOLD_SCALE.'}))?$/';

            if (preg_match($pattern, $raw, $matches) !== 1) {
                throw new InvalidArgumentException('El umbral configurado no tiene un formato decimal válido.');
            }

            $parts = explode('.', $raw, 2);
            $whole = $parts[0];
            $fraction = $matches[1] ?? '';
        } else {
            throw new InvalidArgumentException('El umbral configurado debe enviarse como texto o entero.');
        }

        $scaled = ltrim(
            $whole.str_pad($fraction, self::THRESHOLD_SCALE, '0'),
            '0',
        );

        return $scaled === '' ? '0' : $scaled;
    }

    /**
     * Multiplica dos enteros no negativos representados como texto.
     */
    private static function multiplyUnsignedDigits(string $left, string $right): string
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');

        if ($left === '' || $right === '') {
            return '0';
        }

        $leftDigits = array_reverse(array_map('intval', str_split($left)));
        $rightDigits = array_reverse(array_map('intval', str_split($right)));
        $result = array_fill(0, count($leftDigits) + count($rightDigits), 0);

        foreach ($leftDigits as $i => $leftDigit) {
            foreach ($rightDigits as $j => $rightDigit) {
                $result[$i + $j] += $leftDigit * $rightDigit;
            }
        }

        $carry = 0;

        foreach ($result as $index => $value) {
            $total = $value + $carry;
            $result[$index] = $total % 10;
            $carry = intdiv($total, 10);
        }

        while ($carry > 0) {
            $result[] = $carry % 10;
            $carry = intdiv($carry, 10);
        }

        $digits = ltrim(implode('', array_reverse($result)), '0');

        return $digits === '' ? '0' : $digits;
    }

    private static function compareUnsignedDigits(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        if (strlen($left) !== strlen($right)) {
            return strlen($left) < strlen($right) ? -1 : 1;
        }

        $comparison = strcmp($left, $right);

        return $comparison === 0 ? 0 : ($comparison < 0 ? -1 : 1);
    }

    private static function formatUnsignedScaledDigits(string $digits, int $scale): string
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);

        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.$fraction;
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
