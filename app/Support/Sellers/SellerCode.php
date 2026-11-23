<?php

namespace App\Support\Sellers;

use App\Models\Seller;

/**
 * Política de código de vendedor (RN-13/RN-14).
 *
 * El código se normaliza a mayúsculas y se valida contra el patrón aprobado
 * `^[A-Z0-9._-]{1,20}$`. Identifica, nunca autentica. La fila reservada `NONE`
 * no es un vendedor editable.
 */
final class SellerCode
{
    public const PATTERN = '/^[A-Z0-9._-]{1,20}$/';

    public static function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1 && $value !== Seller::RESERVED_CODE;
    }
}
