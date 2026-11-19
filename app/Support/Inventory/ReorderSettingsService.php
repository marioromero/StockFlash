<?php

namespace App\Support\Inventory;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Configuración de punto de reposición, cantidad sugerida y máximo por
 * sucursal y variante (INVREORDER-001 · INV-07, Parte 2 §7.1).
 *
 * Solo se escriben las tres columnas aprobadas para esta ola. `safety_stock`
 * y `location_label` conservan su esquema y sus valores existentes, pero no se
 * editan ni se publican. La operación no toca `current_stock` ni
 * `stock_movements`: configurar umbrales no altera la existencia física.
 *
 * La licencia, la elegibilidad de la variante/producto y la fila de settings
 * se validan dentro de UNA transacción. La auditoría se registra en la misma
 * transacción: si falla, se revierte también la escritura de settings.
 */
final class ReorderSettingsService
{
    private const SETTINGS_TABLE = 'branch_variant_settings';

    private const AUDIT_EVENT = 'inventory.reorder_settings_updated';

    public function __construct(
        private readonly InventoryWriteGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{
     *     created: bool,
     *     old: array<string, string|null>|null,
     *     new: array<string, string|null>
     * }
     */
    public function update(
        int $branchId,
        int $variantId,
        mixed $reorderPoint,
        mixed $reorderQty,
        mixed $maxStock,
        int $actorUserId,
    ): array {
        if ($branchId < 1 || $variantId < 1 || $actorUserId < 1) {
            throw new ReorderSettingsException(
                'variant_id',
                'La sucursal, la variante o el usuario responsable no son válidos.',
            );
        }

        $point = $this->requiredQuantity(
            $reorderPoint,
            'reorder_point',
            'El punto de reposición',
        );
        $quantity = $this->optionalQuantity(
            $reorderQty,
            'reorder_qty',
            'La cantidad sugerida de reposición',
        );
        $maximum = $this->optionalQuantity(
            $maxStock,
            'max_stock',
            'El stock máximo',
        );

        return DB::transaction(function () use (
            $branchId,
            $variantId,
            $point,
            $quantity,
            $maximum,
        ): array {
            $this->guard->assertWritable();

            $this->assertEligibleVariantUnderLock($variantId);

            $existing = DB::table(self::SETTINGS_TABLE)
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first([
                    'reorder_point',
                    'reorder_qty',
                    'max_stock',
                ]);

            $new = [
                'reorder_point' => InventoryDecimal::formatQuantity($point),
                'reorder_qty' => $quantity === null
                    ? null
                    : InventoryDecimal::formatQuantity($quantity),
                'max_stock' => $maximum === null
                    ? null
                    : InventoryDecimal::formatQuantity($maximum),
            ];

            if ($existing === null) {
                DB::table(self::SETTINGS_TABLE)->insert([
                    'branch_id' => $branchId,
                    'variant_id' => $variantId,
                    'reorder_point' => $new['reorder_point'],
                    'reorder_qty' => $new['reorder_qty'],
                    'max_stock' => $new['max_stock'],
                    // Fases P1/P2: se inicializan solo al crear la fila y no
                    // se vuelven a tocar desde esta pantalla.
                    'safety_stock' => InventoryDecimal::formatQuantity(0),
                    'location_label' => null,
                ]);

                $old = null;
                $created = true;
            } else {
                $old = [
                    'reorder_point' => InventoryDecimal::formatQuantity(
                        InventoryDecimal::quantityFromStorage($existing->reorder_point),
                    ),
                    'reorder_qty' => $existing->reorder_qty === null
                        ? null
                        : InventoryDecimal::formatQuantity(
                            InventoryDecimal::quantityFromStorage($existing->reorder_qty),
                        ),
                    'max_stock' => $existing->max_stock === null
                        ? null
                        : InventoryDecimal::formatQuantity(
                            InventoryDecimal::quantityFromStorage($existing->max_stock),
                        ),
                ];

                DB::table(self::SETTINGS_TABLE)
                    ->where('branch_id', $branchId)
                    ->where('variant_id', $variantId)
                    ->update($new);

                $created = false;
            }

            // Auditoría atómica con la escritura: cualquier fallo revierte
            // ambos cambios. old/new contienen solo los tres ajustes, sin
            // costos, cantidades de stock, PII ni datos de otras sucursales.
            $this->audit->record(
                self::AUDIT_EVENT,
                self::SETTINGS_TABLE,
                $variantId,
                $branchId,
                $old,
                $new,
                'inventory',
            );

            return ['created' => $created, 'old' => $old, 'new' => $new];
        });
    }

    /**
     * Revalidación autoritativa bajo lock: la variante y su producto deben
     * existir, estar activos, no estar eliminados lógicamente, controlar
     * existencias físicas y ser de tipo simple|variable.
     */
    private function assertEligibleVariantUnderLock(int $variantId): void
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
            ->first(['pv.id']);

        if ($variant === null) {
            throw new ReorderSettingsException(
                'variant_id',
                'La variante no existe, está inactiva o no controla existencias físicas.',
            );
        }
    }

    private function requiredQuantity(mixed $value, string $field, string $label): int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw new ReorderSettingsException($field, "{$label} es obligatorio.");
        }

        return $this->parseQuantity($value, $field, $label);
    }

    private function optionalQuantity(mixed $value, string $field, string $label): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return $this->parseQuantity($value, $field, $label);
    }

    private function parseQuantity(mixed $value, string $field, string $label): int
    {
        try {
            $scaled = InventoryDecimal::quantity($value);
        } catch (InvalidArgumentException) {
            throw new ReorderSettingsException(
                $field,
                "{$label} debe ser un decimal no negativo de hasta 9 enteros y 3 decimales.",
            );
        }

        if ($scaled < 0) {
            throw new ReorderSettingsException($field, "{$label} no puede ser negativo.");
        }

        return $scaled;
    }
}
