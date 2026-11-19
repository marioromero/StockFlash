<?php

namespace App\Support\Inventory;

/**
 * Decisión autoritativa de PIN para un ajuste de existencias (INVWRITE-001).
 *
 * El llamador DEBE invocarla dentro de una transacción y después de adquirir
 * el lock de escritura/licencia del inventario. La implementación bloquea y
 * relee la elegibilidad de la variante/producto y los `system_configs`
 * relevantes (`cost_method`, `stock_adjust_pin_value_threshold`) en orden
 * determinista. Así el valor al costo vigente y el umbral usados para decidir
 * son los mismos con los que se confirmará la escritura: ninguna decisión
 * preliminar puede omitir el PIN que la configuración vigente exige.
 *
 * Si la variante o el producto dejaron de ser elegibles lanza
 * `StockWriteException('variant_id', ...)` y la transacción revierte.
 */
interface StockAdjustmentPinPolicy
{
    public function requiresPinUnderLock(int $variantId, int $scaledQuantity): bool;
}
