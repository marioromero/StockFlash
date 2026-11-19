<?php

namespace App\Support\Inventory;

use RuntimeException;

/**
 * Intento de publicar dos veces el mismo documento de origen en el kardex.
 *
 * La proteccion es transaccional y no depende de un indice unico no aprobado
 * en base de datos. La clave es (source_type, source_id, branch_id, variant_id,
 * type). Un origen nulo nunca se considera duplicado.
 */
final class DuplicateStockMovementException extends RuntimeException
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly int $branchId,
        public readonly int $variantId,
        public readonly string $movementType,
    ) {
        parent::__construct(
            "El origen {$sourceType}#{$sourceId} ya generó un movimiento {$movementType} "
            ."para la sucursal {$branchId} y variante {$variantId}."
        );
    }
}
