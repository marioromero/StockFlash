<?php

namespace Tests\Feature\Inventory;

use App\Support\Inventory\InventoryDecimal;
use InvalidArgumentException;

/**
 * Aritmética decimal exacta del umbral de ajuste (INVWRITE-001).
 */
class InventoryDecimalPrecisionTest extends InventoryWriteTestCase
{
    public function test_producto_exacto_con_siete_decimales(): void
    {
        // 1.001 (milésimas) * 1.0000 (diezmilésimas) = 1.0010000
        $this->assertSame('1.0010000', InventoryDecimal::multiplyQuantityByCost(1001, 10000));

        // 123.456 * 78.9012 = 9740.?? se valida contra el mismo producto formateado.
        $product = InventoryDecimal::multiplyQuantityByCost(123456, 789012);
        $this->assertMatchesRegularExpression('/^\d+\.\d{7}$/', $product);
    }

    public function test_comparacion_estricta_contra_igual_y_una_milesima_arriba(): void
    {
        // 1.000 * 1.0000 = 1.0000000
        $this->assertSame(0, InventoryDecimal::compareProductToThreshold(1000, 10000, '1'));
        $this->assertSame(0, InventoryDecimal::compareProductToThreshold(1000, 10000, '1.0000000'));
        $this->assertSame(1, InventoryDecimal::compareProductToThreshold(1000, 10000, '0.9999999'));
        $this->assertSame(-1, InventoryDecimal::compareProductToThreshold(1000, 10000, '1.0000001'));

        // 1.001 * 1.0000 = 1.0010000: una milésima por sobre 1.0009999.
        $this->assertSame(1, InventoryDecimal::compareProductToThreshold(1001, 10000, '1.0009999'));
        $this->assertSame(0, InventoryDecimal::compareProductToThreshold(1001, 10000, '1.001'));
    }

    public function test_extremos_decimales_no_desbordan(): void
    {
        $product = InventoryDecimal::multiplyQuantityByCost(
            InventoryDecimal::QUANTITY_MAX_ABS,
            InventoryDecimal::COST_MAX_ABS,
        );

        $this->assertMatchesRegularExpression('/^\d+\.\d{7}$/', $product);
        $this->assertSame(1, InventoryDecimal::compareProductToThreshold(
            InventoryDecimal::QUANTITY_MAX_ABS,
            InventoryDecimal::COST_MAX_ABS,
            '999999999',
        ));
        $this->assertSame(1, InventoryDecimal::compareProductToThreshold(
            InventoryDecimal::QUANTITY_MAX_ABS,
            InventoryDecimal::COST_MAX_ABS,
            '999999999999999999.9999999',
        ));
        $this->assertSame(0, InventoryDecimal::compareProductToThreshold(1, 1, '0.0000001'));
    }

    public function test_umbral_invalido_o_negativo_es_rechazado(): void
    {
        foreach (['', '-1', '1e3', 'abc', '1,5', '1.12345678', '1234567890123456789'] as $invalid) {
            try {
                InventoryDecimal::compareProductToThreshold(1000, 10000, $invalid);
                $this->fail("El umbral {$invalid} debía ser rechazado.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        InventoryDecimal::compareProductToThreshold(-1, 10000, '1');
    }

    public function test_cantidad_o_costo_negativos_no_se_multiplican(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InventoryDecimal::multiplyQuantityByCost(-1, 10000);
    }
}
