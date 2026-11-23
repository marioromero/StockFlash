<?php

namespace App\Support\Sellers;

use InvalidArgumentException;

/**
 * Comisión configurada del vendedor (SEL-01; Parte 2 §6.5).
 *
 * Acepta solo cadenas decimales 0..100 con hasta dos decimales y devuelve la
 * forma canónica `entero.DD`. No hace aritmética de punto flotante ni calcula
 * comisiones ganadas; solo guarda el porcentaje contratado.
 */
final class SellerCommission
{
    public const PATTERN = '/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/';

    /**
     * @throws InvalidArgumentException
     */
    public static function canonical(?string $value): string
    {
        $value = $value === null ? '' : trim($value);

        if ($value === '') {
            return '0.00';
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('La comisión debe ser una cadena decimal entre 0 y 100 con hasta dos decimales.');
        }

        [$integer, $decimals] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $decimals = str_pad($decimals, 2, '0');

        if ((int) $integer > 100) {
            throw new InvalidArgumentException('La comisión debe estar entre 0 y 100.');
        }

        if ((int) $integer === 100 && $decimals !== '00') {
            throw new InvalidArgumentException('La comisión máxima permitida es 100.00.');
        }

        return $integer.'.'.$decimals;
    }
}
