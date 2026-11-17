<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxRate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCrudTest extends ProductTestCase
{
    public function test_crea_producto_simple_con_variante_tags_y_atributos_en_una_transaccion(): void
    {
        [$attribute, $value] = $this->makeAttributeValue('Color', 'Rojo');
        $tag = $this->makeTag('Oferta');
        $manager = $this->makeManagerWithoutCostPermission();

        $payload = $this->productPayload([
            'name' => 'Arroz Grado 1',
            'internal_code' => 'ARR-001',
            'tag_ids' => [$tag->id],
        ]);
        $payload['variants'][0]['attribute_value_ids'] = [$value->id];

        $this->actingAs($manager)
            ->post('/catalog/products', $payload)
            ->assertRedirect();

        $product = Product::query()->where('internal_code', 'ARR-001')->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('simple', $product->type);
        $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->count());
        $this->assertSame(0.0, (float) $variant->cost_price);
        $this->assertDatabaseHas('product_tag', ['product_id' => $product->id, 'tag_id' => $tag->id]);
        $this->assertDatabaseHas('product_variant_attribute_values', [
            'variant_id' => $variant->id,
            'attribute_value_id' => $value->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'catalog.products.created', 'module_code' => 'catalog']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'catalog.product_variants.created', 'module_code' => 'catalog']);

        $audit = DB::table('audit_logs')->where('event', 'catalog.product_variants.created')->value('new_values');
        $this->assertIsString($audit);
        $this->assertStringNotContainsString('cost_price', $audit);
    }

    public function test_campos_extra_y_last_cost_son_ignorados_por_allowlist(): void
    {
        $otherBranch = $this->makeBranch();

        $payload = $this->productPayload([
            'percentage' => 15,
            'invented_column' => 'x',
            'current_stock' => 999,
            'branch_id' => $otherBranch->id,
        ]);
        $payload['variants'][0]['last_cost'] = '999.0000';
        $payload['variants'][0]['percentage'] = 50;
        $payload['variants'][0]['invented'] = true;
        $payload['variants'][0]['current_stock'] = 999;

        $this->actingAs($this->admin)
            ->post('/catalog/products', $payload)
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, ProductVariant::query()->count());
        $this->assertSame('pos', $product->origin);
        $productAttributes = $product->getAttributes();
        $variantAttributes = $variant->getAttributes();
        $this->assertArrayNotHasKey('percentage', $productAttributes);
        $this->assertArrayNotHasKey('invented_column', $productAttributes);
        $this->assertArrayNotHasKey('current_stock', $productAttributes);
        $this->assertArrayNotHasKey('branch_id', $productAttributes);
        $this->assertArrayNotHasKey('percentage', $variantAttributes);
        $this->assertArrayNotHasKey('invented', $variantAttributes);
        $this->assertArrayNotHasKey('current_stock', $variantAttributes);
        $this->assertNull($variant->last_cost);
        $this->assertSame('1000.00', (string) $variant->price);
        $this->assertSame('500.0000', (string) $variant->cost_price);
    }

    public function test_producto_simple_exige_exactamente_una_variante(): void
    {
        $two = $this->productPayload();
        $two['variants'][] = $this->variantPayload();

        $this->actingAs($this->admin)
            ->post('/catalog/products', $two)
            ->assertSessionHasErrors('variants');

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['variants' => []]))
            ->assertSessionHasErrors('variants');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_producto_variable_exige_al_menos_dos_variantes_y_combo_una(): void
    {
        $one = $this->productPayload(['type' => 'variable']);

        $this->actingAs($this->admin)
            ->post('/catalog/products', $one)
            ->assertSessionHasErrors('variants');

        $variable = $this->productPayload(['type' => 'variable']);
        $variable['variants'] = [$this->variantPayload(), $this->variantPayload()];

        $this->actingAs($this->admin)
            ->post('/catalog/products', $variable)
            ->assertRedirect();

        $combo = $this->productPayload(['type' => 'combo']);

        $this->actingAs($this->admin)
            ->post('/catalog/products', $combo)
            ->assertRedirect();

        $this->assertSame(1, Product::query()->where('type', 'variable')->count());
        $this->assertSame(1, Product::query()->where('type', 'combo')->count());
    }

    public function test_valida_fk_activas_y_no_eliminadas(): void
    {
        $inactiveCategory = Category::create([
            'name' => 'Inactiva',
            'slug' => 'cat-inactiva-'.Str::lower(Str::random(4)),
            'is_active' => false,
        ]);
        $deletedBrand = Brand::create([
            'name' => 'Eliminada',
            'slug' => 'brand-eliminada-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);
        $deletedBrand->delete();
        $inactiveTax = TaxRate::create([
            'code' => 'T-'.Str::upper(Str::random(4)),
            'name' => 'Tasa inactiva',
            'rate' => 19,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['category_id' => $inactiveCategory->id]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['brand_id' => $deletedBrand->id]))
            ->assertSessionHasErrors('brand_id');

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['tax_rate_id' => $inactiveTax->id]))
            ->assertSessionHasErrors('tax_rate_id');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_valida_enums_precision_negativos_y_fk_de_variante(): void
    {
        $cases = [
            ['variants' => [$this->variantPayload(['unit' => 'ton'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['price' => '-1.00'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['price' => '10.123'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['max_discount_percent' => '100.01'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['min_price' => '-0.01'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['cost_price' => '1.23456'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['tax_rate_id' => 999999])], 'type' => 'simple'],
        ];

        foreach ($cases as $overrides) {
            $payload = $this->productPayload($overrides);

            $this->actingAs($this->admin)
                ->post('/catalog/products', $payload)
                ->assertSessionHasErrors();
        }

        $this->assertSame(0, Product::query()->count());
    }

    public function test_rechaza_decimales_fuera_de_precision_contractual(): void
    {
        $cases = [
            // DECIMAL(14,4): máximo 10 dígitos enteros.
            ['variants' => [$this->variantPayload(['cost_price' => '99999999999.0000'])], 'type' => 'simple'],
            // DECIMAL(14,2): máximo 12 dígitos enteros.
            ['variants' => [$this->variantPayload(['price' => '9999999999999.00'])], 'type' => 'simple'],
            ['variants' => [$this->variantPayload(['min_price' => '9999999999999.00'])], 'type' => 'simple'],
        ];

        foreach ($cases as $overrides) {
            $this->actingAs($this->admin)
                ->post('/catalog/products', $this->productPayload($overrides))
                ->assertSessionHasErrors();
        }

        $this->assertSame(0, Product::query()->count());

        $product = $this->createSimpleProduct([], ['cost_price' => '500.0000']);
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}/variants/{$variant->id}", $this->variantPayload([
                'sku' => $variant->sku,
                'cost_price' => '99999999999.0000',
            ]))
            ->assertSessionHasErrors('cost_price');

        $this->assertSame('500.0000', (string) $variant->refresh()->cost_price);
    }

    public function test_tags_no_admiten_duplicados_en_el_pivote(): void
    {
        $tag = $this->makeTag('Duplicada');

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['tag_ids' => [$tag->id, $tag->id]]))
            ->assertSessionHasErrors('tag_ids.1');

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, DB::table('product_tag')->count());
    }

    public function test_actualiza_producto_etiquetas_y_rechaza_cambio_de_tipo_incompatible(): void
    {
        $product = $this->createVariableProduct();
        $tag = $this->makeTag('Nueva etiqueta');

        $update = $this->productPayload([
            'type' => 'variable',
            'name' => 'Variable actualizada',
            'tag_ids' => [$tag->id],
        ]);
        $update['variants'] = [];

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}", $update)
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('Variable actualizada', $product->name);
        $this->assertDatabaseHas('product_tag', ['product_id' => $product->id, 'tag_id' => $tag->id]);

        $toSimple = $this->productPayload(['type' => 'simple', 'name' => 'No debe cambiar']);

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}", $toSimple)
            ->assertSessionHasErrors('type');

        $this->assertNotSame('No debe cambiar', $product->refresh()->name);
    }

    public function test_duplicados_de_internal_code_y_sku_responden_422_y_la_base_tambien_rechaza(): void
    {
        $product = $this->createSimpleProduct();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['internal_code' => $product->internal_code]))
            ->assertSessionHasErrors('internal_code');

        $duplicateSku = $this->productPayload();
        $duplicateSku['variants'][0]['sku'] = $variant->sku;

        $this->actingAs($this->admin)
            ->post('/catalog/products', $duplicateSku)
            ->assertSessionHasErrors('variants.0.sku');

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

    public function test_unicidad_directa_de_internal_code_en_base(): void
    {
        $product = $this->createSimpleProduct();

        $this->expectException(QueryException::class);
        DB::table('products')->insert([
            'name' => 'Duplicado directo',
            'internal_code' => $product->internal_code,
            'type' => 'simple',
            'origin' => 'pos',
            'tracks_stock' => 1,
            'is_sellable' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_soft_delete_conserva_identificadores_y_no_permite_reutilizarlos(): void
    {
        $product = $this->createSimpleProduct();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $barcodeId = $this->addBarcode($product, $variant, ['barcode' => '7800000000001']);

        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}")
            ->assertRedirect(route('catalog.products.index'));

        $this->assertNotNull(DB::table('products')->where('id', $product->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('product_variants')->where('id', $variant->id)->value('deleted_at'));
        $this->assertDatabaseHas('product_barcodes', ['id' => $barcodeId, 'barcode' => '7800000000001']);

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload([
                'name' => 'Reutilizado',
                'internal_code' => $product->internal_code,
            ]))
            ->assertSessionHasErrors('internal_code');

        $reusedSku = $this->productPayload(['name' => 'SKU reutilizado']);
        $reusedSku['variants'][0]['sku'] = $variant->sku;

        $this->actingAs($this->admin)
            ->post('/catalog/products', $reusedSku)
            ->assertSessionHasErrors('variants.0.sku');

        $this->assertSame(1, DB::table('products')->count());
    }
}
