<?php

namespace App\Support\Customers;

use InvalidArgumentException;

/**
 * Porcentajes de descuento con precisión decimal exacta (RN-02 · CUS-02/03).
 *
 * Acepta solo cadenas decimales 0..100 con hasta dos decimales y devuelve la
 * forma canónica `entero.DD`. No hace aritmética de punto flotante.
 */
final class DiscountPercent
{
    /**
     * @throws InvalidArgumentException
     */
    public static function canonical(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('El porcentaje debe ser una cadena decimal de 0 a 100 con hasta dos decimales.');
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $decimals = str_pad($matches[2] ?? '', 2, '0');

        if ((int) $integer > 100) {
            throw new InvalidArgumentException('El porcentaje debe estar entre 0 y 100.');
        }

        if ((int) $integer === 100 && $decimals !== '00') {
            throw new InvalidArgumentException('El porcentaje máximo permitido es 100.00.');
        }

        return $integer.'.'.$decimals;
    }
}
