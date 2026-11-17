<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProductVariantTest extends ProductTestCase
{
    public function test_agrega_varante_a_producto_variable_con_atributos(): void
    {
        $product = $this->createVariableProduct();
        [$attribute, $value] = $this->makeAttributeValue('Color', 'Verde');

        $this->actingAs($this->admin)
            ->post("/catalog/products/{$product->id}/variants", $this->variantPayload([
                'sku' => 'SKU-VERDE',
                'attribute_value_ids' => [$value->id],
            ]))
            ->assertRedirect();

        $variant = ProductVariant::query()->where('sku', 'SKU-VERDE')->firstOrFail();

        $this->assertSame(3, $product->variants()->count());
        $this->assertDatabaseHas('product_variant_attribute_values', [
            'variant_id' => $variant->id,
            'attribute_value_id' => $value->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'catalog.product_variants.created']);
    }

    public function test_producto_simple_no_admite_segunda_variante(): void
    {
        $product = $this->createSimpleProduct();

        $this->actingAs($this->admin)
            ->post("/catalog/products/{$product->id}/variants", $this->variantPayload())
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_variable_no_puede_quedar_con_una_sola_variante_al_eliminar(): void
    {
        $product = $this->createVariableProduct();
        $variant = $product->variants()->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}/variants/{$variant->id}")
            ->assertSessionHasErrors('type');

        $this->assertNull(DB::table('product_variants')->where('id', $variant->id)->value('deleted_at'));

        $this->addVariant($product);
        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}/variants/{$variant->id}")
            ->assertRedirect();

        $this->assertNotNull(DB::table('product_variants')->where('id', $variant->id)->value('deleted_at'));
        $this->assertSame(2, $product->variants()->count());
    }

    public function test_actualiza_variante_y_reemplaza_atributos(): void
    {
        $product = $this->createVariableProduct();
        $variant = $product->variants()->orderBy('id')->firstOrFail();
        [$attribute, $value] = $this->makeAttributeValue('Talla', 'M');

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                'sku' => $variant->sku,
                'price' => '2500.50',
                'attribute_value_ids' => [$value->id],
            ]))
            ->assertRedirect();

        $variant->refresh();
        $this->assertSame('2500.50', (string) $variant->price);
        $this->assertDatabaseHas('product_variant_attribute_values', [
            'variant_id' => $variant->id,
            'attribute_value_id' => $value->id,
        ]);
    }

    public function test_actor_sin_view_cost_no_puede_filtrar_ni_sobrescribir_costos(): void
    {
        $product = $this->createSimpleProduct([], ['cost_price' => '500.0000']);
        $variant = $product->variants()->firstOrFail();
        $manager = $this->makeManagerWithoutCostPermission();

        $this->actingAs($manager)
            ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                'sku' => $variant->sku,
                'price' => '1300.00',
                'cost_price' => '9999.9999',
                'last_cost' => '8888.0000',
            ]))
            ->assertRedirect();

        $variant->refresh();

        $this->assertSame('1300.00', (string) $variant->price);
        $this->assertSame('500.0000', (string) $variant->cost_price);
        $this->assertNull($variant->last_cost);

        $audit = DB::table('audit_logs')->where('event', 'catalog.product_variants.updated')->latest('id')->value('new_values');
        $this->assertIsString($audit);
        $this->assertStringNotContainsString('cost_price', $audit);
        $this->assertStringNotContainsString('9999', $audit);
    }

    public function test_costo_nulo_o_vacio_con_permiso_responde_422_sin_escritura(): void
    {
        $product = $this->createSimpleProduct([], ['cost_price' => '500.0000']);
        $variant = $product->variants()->firstOrFail();

        foreach ([null, ''] as $invalidCost) {
            $this->actingAs($this->admin)
                ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                    'sku' => $variant->sku,
                    'cost_price' => $invalidCost,
                ]))
                ->assertSessionHasErrors('cost_price');

            $this->actingAs($this->admin)
                ->post('/catalog/products', $this->productPayload([
                    'variants' => [$this->variantPayload(['cost_price' => $invalidCost])],
                ]))
                ->assertSessionHasErrors('variants.0.cost_price');
        }

        $this->assertSame(1, Product::query()->count());
        $this->assertSame('500.0000', (string) $variant->refresh()->cost_price);
    }

    public function test_costo_nulo_o_vacio_sin_permiso_preserva_costo_existente(): void
    {
        $product = $this->createSimpleProduct([], ['cost_price' => '500.0000']);
        $variant = $product->variants()->firstOrFail();
        $manager = $this->makeManagerWithoutCostPermission();

        foreach ([null, ''] as $ignoredCost) {
            $this->actingAs($manager)
                ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                    'sku' => $variant->sku,
                    'price' => '1300.00',
                    'cost_price' => $ignoredCost,
                ]))
                ->assertRedirect();
        }

        $variant->refresh();
        $this->assertSame('1300.00', (string) $variant->price);
        $this->assertSame('500.0000', (string) $variant->cost_price);
    }

    public function test_actor_con_view_cost_si_actualiza_costo(): void
    {
        $product = $this->createSimpleProduct([], ['cost_price' => '500.0000']);
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                'sku' => $variant->sku,
                'cost_price' => '650.2500',
            ]))
            ->assertRedirect();

        $this->assertSame('650.2500', (string) $variant->refresh()->cost_price);
    }

    public function test_rechaza_mas_de_un_valor_por_atributo_en_creacion_y_edicion(): void
    {
        [$attribute, $red] = $this->makeAttributeValue('Color', 'Rojo');
        $blue = \App\Models\AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Azul']);

        $payload = $this->productPayload(['type' => 'variable']);
        $payload['variants'] = [
            $this->variantPayload(['attribute_value_ids' => [$red->id, $blue->id]]),
            $this->variantPayload(),
        ];

        $this->actingAs($this->admin)
            ->post('/catalog/products', $payload)
            ->assertSessionHasErrors('variants.0.attribute_value_ids');

        $product = $this->createVariableProduct();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                'sku' => $variant->sku,
                'attribute_value_ids' => [$red->id, $blue->id],
            ]))
            ->assertSessionHasErrors('attribute_value_ids');
    }

    public function test_duplicado_de_sku_responde_422_y_base_rechaza(): void
    {
        $product = $this->createVariableProduct();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/catalog/products/{$product->id}/variants", $this->variantPayload(['sku' => $variant->sku]))
            ->assertSessionHasErrors('sku');

        $this->expectException(QueryException::class);
        DB::table('product_variants')->insert([
            'product_id' => $product->id,
            'sku' => $variant->sku,
            'price' => 1,
            'cost_price' => 0,
            'unit' => 'unit',
            'sold_by_weight' => 0,
            'allow_price_override' => 1,
            'combo_deducts_components' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_soft_delete_de_variante_conserva_sku_unico(): void
    {
        $product = $this->createVariableProduct();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}/variants/{$variant->id}")
            ->assertSessionHasErrors('type');

        $this->addVariant($product);
        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}/variants/{$variant->id}")
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post("/catalog/products/{$product->id}/variants", $this->variantPayload(['sku' => $variant->sku]))
            ->assertSessionHasErrors('sku');
    }
}
