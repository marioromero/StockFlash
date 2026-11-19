<?php

namespace App\Support\Inventory;

use RuntimeException;

/**
 * Error de dominio del kardex fisico de inventario.
 *
 * El servicio es interno en esta ola: estas excepciones no se exponen como
 * validacion de formulario porque aun no existen rutas de escritura publicas.
 */
class StockLedgerException extends RuntimeException
{
    public static function movementNotFound(int $movementId): self
    {
        return new self("El movimiento de inventario {$movementId} no existe.");
    }

    public static function reversalOfReversal(): self
    {
        return new self('Un movimiento de reversa no puede revertirse; revierta el movimiento original.');
    }

    public static function alreadyReversed(int $movementId): self
    {
        return new self("El movimiento de inventario {$movementId} ya fue revertido.");
    }

    public static function inconsistentStockRow(): self
    {
        return new self('No fue posible bloquear la fila de existencia de la sucursal y variante.');
    }
}
