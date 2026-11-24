<?php

namespace App\Support\PosStations;

/**
 * Política de código de estación POS (CSH-02).
 *
 * El código se recorta, se normaliza a mayúsculas y debe cumplir
 * `^[A-Z0-9._-]{1,30}$`. Es inmutable después de crear la estación y único
 * incluso frente a variantes de caja y filas con eliminación lógica.
 */
final class PosStationCode
{
    public const PATTERN = '/^[A-Z0-9._-]{1,30}$/';

    public static function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }
}
