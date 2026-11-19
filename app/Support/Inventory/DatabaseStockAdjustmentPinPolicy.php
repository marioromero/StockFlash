<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Implementación de base de datos de la política de PIN (INVWRITE-001).
 *
 * Uso exclusivo dentro de una transacción y después del lock de licencia. Bajo
 * ese contexto:
 * 1. bloquea y relee la variante/producto aplicando la misma elegibilidad que
 *    la escritura de inventario (activo, no borrado, `tracks_stock=1`, tipo
 *    simple/variable);
 * 2. bloquea `system_configs` en orden determinista (`config_key` ascendente);
 * 3. calcula el valor con aritmética decimal exacta (`InventoryDecimal`, sin
 *    floats ni bcmath): `abs(cantidad) * costo`, donde el costo es `cost_price`
 *    salvo que `cost_method='last'` y `last_cost` no sea nulo;
 * 4. devuelve verdadero solo si el umbral está habilitado (ausente/cero lo
 *    desactiva) y el valor es estrictamente mayor.
 *
 * No bloquea usuarios, sucursales ni roles: esos locks pertenecen a COREPIN.
 * En MariaDB el orden es licencia → variante/producto → `system_configs`.
 */
final class DatabaseStockAdjustmentPinPolicy implements StockAdjustmentPinPolicy
{
    public function requiresPinUnderLock(int $variantId, int $scaledQuantity): bool
    {
        $variant = $this->lockedEligibleVariant($variantId);

        $configs = DB::table('system_configs')
            ->whereIn('config_key', ['cost_method', 'stock_adjust_pin_value_threshold'])
            ->orderBy('config_key')
            ->lockForUpdate()
            ->pluck('config_value', 'config_key')
            ->all();

        $method = $configs['cost_method'] ?? null;
        $method = is_string($method) ? trim($method) : null;

        $threshold = $this->normalizeThreshold(
            $configs['stock_adjust_pin_value_threshold'] ?? null,
        );

        if ($threshold === null) {
            return false;
        }

        $rawCost = ($method === 'last' && $variant->last_cost !== null)
            ? $variant->last_cost
            : $variant->cost_price;

        $unitCost = $rawCost === null ? 0 : (InventoryDecimal::costFromStorage($rawCost) ?? 0);

        try {
            return InventoryDecimal::compareProductToThreshold(
                abs($scaledQuantity),
                $unitCost,
                $threshold,
            ) > 0;
        } catch (InvalidArgumentException) {
            throw new StockWriteException(
                'adjustment',
                'La configuración del umbral de autorización no es válida; contacte al administrador.',
            );
        }
    }

    /**
     * Relee la variante bajo lock con los mismos criterios de elegibilidad que
     * `StockWriteService`/`StockLedger`. En SQLite `lockForUpdate()` es no-op y
     * la transacción de prueba serializa; en MariaDB bloquea variante y
     * producto.
     */
    private function lockedEligibleVariant(int $variantId): object
    {
        $variant = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pv.id', $variantId)
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('pv.is_active', 1)
            ->where('p.is_active', 1)
            ->where('p.tracks_stock', 1)
            ->whereIn('p.type', ['simple', 'variable'])
            ->lockForUpdate()
            ->first(['pv.id', 'pv.cost_price', 'pv.last_cost']);

        if ($variant === null) {
            throw new StockWriteException(
                'variant_id',
                'La variante no existe, está inactiva o no controla existencias físicas.',
            );
        }

        return $variant;
    }

    /**
     * Umbral ausente, vacío o cero (incluido `0.0000000`) desactiva el PIN.
     */
    private function normalizeThreshold(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $isZero = InventoryDecimal::compareProductToThreshold(0, 0, $value) === 0;
        } catch (InvalidArgumentException) {
            throw new StockWriteException(
                'adjustment',
                'La configuración del umbral de autorización no es válida; contacte al administrador.',
            );
        }

        return $isZero ? null : $value;
    }
}
