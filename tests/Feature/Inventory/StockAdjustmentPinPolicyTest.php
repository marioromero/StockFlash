<?php

namespace Tests\Feature\Inventory;

use App\Support\Inventory\StockAdjustmentPinPolicy;
use App\Support\Inventory\StockWriteException;
use Illuminate\Support\Facades\DB;

/**
 * Política autoritativa de PIN bajo locks (INVWRITE-001 · corrección i=005).
 *
 * Solo SQLite en memoria: `lockForUpdate()` es no-op y la transacción de
 * prueba serializa. La concurrencia real de MariaDB queda NOT_RUN.
 */
class StockAdjustmentPinPolicyTest extends InventoryWriteTestCase
{
    public function test_umbral_estricto_con_costo_promedio(): void
    {
        [, $variantId] = $this->makeProductVariant('POL-001');
        $this->updateVariantCosts($variantId, '500.0000');
        $this->setCostMethod('average');

        $this->setThreshold('500.001');
        $this->assertFalse($this->requiresPin($variantId, 1000));

        // Igual al umbral no exige PIN (comparación estricta).
        $this->setThreshold('500');
        $this->assertFalse($this->requiresPin($variantId, 1000));

        $this->setThreshold('499.999');
        $this->assertTrue($this->requiresPin($variantId, 1000));
    }

    public function test_umbral_cero_vacio_o_ausente_desactiva_el_pin(): void
    {
        [, $variantId] = $this->makeProductVariant('POL-002');
        $this->updateVariantCosts($variantId, '9999.0000');

        foreach (['0', '0.0000000', ''] as $threshold) {
            $this->setThreshold($threshold);
            $this->assertFalse($this->requiresPin($variantId, 1000), "Umbral '{$threshold}' debe desactivar.");
        }

        DB::table('system_configs')
            ->where('config_key', 'stock_adjust_pin_value_threshold')
            ->delete();

        $this->assertFalse($this->requiresPin($variantId, 1000));
    }

    public function test_cambiar_el_costo_cambia_la_decision(): void
    {
        [, $variantId] = $this->makeProductVariant('POL-003');
        $this->setCostMethod('average');
        $this->setThreshold('600');

        $this->updateVariantCosts($variantId, '500.0000');
        $this->assertFalse($this->requiresPin($variantId, 1000));

        $this->updateVariantCosts($variantId, '700.0000');
        $this->assertTrue($this->requiresPin($variantId, 1000));
    }

    public function test_cambiar_el_umbral_cambia_la_decision(): void
    {
        [, $variantId] = $this->makeProductVariant('POL-004');
        $this->updateVariantCosts($variantId, '500.0000');
        $this->setCostMethod('average');

        $this->setThreshold('600');
        $this->assertFalse($this->requiresPin($variantId, 1000));

        $this->setThreshold('400');
        $this->assertTrue($this->requiresPin($variantId, 1000));
    }

    public function test_last_cost_solo_se_usa_con_metodo_last_y_valor_no_nulo(): void
    {
        [, $variantId] = $this->makeProductVariant('POL-005');
        $this->updateVariantCosts($variantId, '100.0000', '1000.0000');
        $this->setThreshold('500');

        $this->setCostMethod('average');
        $this->assertFalse($this->requiresPin($variantId, 1000));

        $this->setCostMethod('last');
        $this->assertTrue($this->requiresPin($variantId, 1000));

        // Sin `last_cost` el método `last` vuelve al costo promedio.
        $this->updateVariantCosts($variantId, '100.0000', null);
        $this->assertFalse($this->requiresPin($variantId, 1000));
    }

    public function test_cantidad_negativa_usa_valor_absoluto(): void
    {
        [, $variantId] = $this->makeProductVariant('POL-006');
        $this->updateVariantCosts($variantId, '500.0000');
        $this->setThreshold('500');

        $this->assertFalse($this->requiresPin($variantId, -1000));
        $this->assertTrue($this->requiresPin($variantId, -1001));
    }

    public function test_variante_inelegible_o_borrada_lanza_stock_write_exception(): void
    {
        [$productId, $variantId] = $this->makeProductVariant('POL-007');
        $this->setThreshold('1');

        DB::table('product_variants')->where('id', $variantId)->update([
            'is_active' => 0,
            'updated_at' => now(),
        ]);
        $this->assertPolicyRejectsVariant($variantId);

        DB::table('product_variants')->where('id', $variantId)->update([
            'is_active' => 1,
            'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $productId)->update([
            'tracks_stock' => 0,
            'updated_at' => now(),
        ]);
        $this->assertPolicyRejectsVariant($variantId);

        DB::table('products')->where('id', $productId)->update([
            'tracks_stock' => 1,
            'updated_at' => now(),
        ]);
        DB::table('product_variants')->where('id', $variantId)->update([
            'deleted_at' => now(),
        ]);
        $this->assertPolicyRejectsVariant($variantId);
    }

    private function requiresPin(int $variantId, int $scaledQuantity): bool
    {
        return DB::transaction(
            fn (): bool => app(StockAdjustmentPinPolicy::class)
                ->requiresPinUnderLock($variantId, $scaledQuantity),
        );
    }

    private function assertPolicyRejectsVariant(int $variantId): void
    {
        try {
            $this->requiresPin($variantId, 1000);

            $this->fail('La política debió rechazar la variante inelegible.');
        } catch (StockWriteException $exception) {
            $this->assertSame('variant_id', $exception->field);
        }
    }
}
