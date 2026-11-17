<?php

namespace Tests\Feature\Catalog;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CATDB-001 - Constraints reales de Catalogo P0 sobre SQLite :memory:.
 *
 * El guard se ejecuta antes de cualquier DDL disparado por RefreshDatabase.
 * No se ejecuta `artisan migrate` de CLI ni se toca ninguna base remota.
 */
class CatalogConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'CATDB-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'CATDB-001 exige SQLite :memory:.');
    }

    public function test_insercion_valida_de_extremo_a_extremo(): void
    {
        $graph = $this->fullGraph();

        foreach (['tax_rate', 'category', 'brand', 'tag', 'attribute', 'attribute_value', 'product', 'variant', 'barcode'] as $key) {
            $this->assertGreaterThan(0, $graph[$key]);
        }

        $product = DB::table('products')->where('id', $graph['product'])->first();
        $this->assertSame('simple', $product->type);
        $this->assertSame('pos', $product->origin);
        $this->assertSame(1, (int) $product->tracks_stock);

        $variant = DB::table('product_variants')->where('id', $graph['variant'])->first();
        $this->assertSame('unit', $variant->unit);
        $this->assertSame(1, (int) $variant->is_active);

        $barcode = DB::table('product_barcodes')->where('id', $graph['barcode'])->first();
        $this->assertSame('ean13', $barcode->type);

        $this->assertSame(1, DB::table('product_tag')->count());
        $this->assertSame(1, DB::table('product_variant_attribute_values')->count());
    }

    public function test_unique_impide_duplicados_en_todas_las_claves(): void
    {
        $graph = $this->fullGraph();

        $this->assertViolatesUnique(fn () => $this->taxRate('IVA19'));
        $this->assertViolatesUnique(fn () => $this->category('cat-root'));
        $this->assertViolatesUnique(fn () => $this->brand('brand-1'));
        $this->assertViolatesUnique(fn () => $this->tag('tag-1'));
        $this->assertViolatesUnique(fn () => $this->attribute('Color'));
        $this->assertViolatesUnique(fn () => $this->attributeValue($graph['attribute'], 'Rojo'));

        $this->assertViolatesUnique(fn () => DB::table('products')->insert([
            'name' => 'Otro producto',
            'internal_code' => 'P-1',
        ]));

        $this->assertViolatesUnique(fn () => DB::table('product_variants')->insert([
            'product_id' => $graph['product'],
            'sku' => 'SKU-1',
        ]));

        $this->assertViolatesUnique(fn () => DB::table('product_barcodes')->insert([
            'variant_id' => $graph['variant'],
            'barcode' => '1234567890123',
        ]));
    }

    public function test_fk_invalida_produce_query_exception(): void
    {
        $this->assertQueryException(fn () => DB::table('categories')->insert([
            'parent_id' => 999999,
            'name' => 'Hija huerfana',
            'slug' => 'hija-huerfana',
        ]));

        $this->assertQueryException(fn () => DB::table('product_variants')->insert([
            'product_id' => 999999,
            'sku' => 'SKU-HUERFANO',
        ]));
    }

    public function test_restrict_impide_borrar_categoria_padre_con_hijas(): void
    {
        $parent = $this->category('cat-padre');
        $this->category('cat-hija', $parent);

        $this->assertQueryException(fn () => DB::table('categories')->where('id', $parent)->delete());
        $this->assertSame(2, DB::table('categories')->count());
    }

    public function test_cascade_borrar_producto_elimina_variantes_barcodes_etiquetas_y_atributos(): void
    {
        $graph = $this->fullGraph();

        DB::table('products')->where('id', $graph['product'])->delete();

        $this->assertSame(0, DB::table('product_variants')->count());
        $this->assertSame(0, DB::table('product_barcodes')->count());
        $this->assertSame(0, DB::table('product_tag')->count());
        $this->assertSame(0, DB::table('product_variant_attribute_values')->count());
        // Las entidades independientes del producto no se borran.
        $this->assertSame(1, DB::table('tags')->count());
        $this->assertSame(1, DB::table('attributes')->count());
    }

    public function test_cascade_borrar_variante_elimina_barcodes(): void
    {
        $graph = $this->fullGraph();

        DB::table('product_variants')->where('id', $graph['variant'])->delete();

        $this->assertSame(0, DB::table('product_barcodes')->count());
        $this->assertSame(0, DB::table('product_variant_attribute_values')->count());
    }

    public function test_cascade_borrar_attribute_elimina_valores_y_pivote(): void
    {
        $graph = $this->fullGraph();

        DB::table('attributes')->where('id', $graph['attribute'])->delete();

        $this->assertSame(0, DB::table('attribute_values')->count());
        $this->assertSame(0, DB::table('product_variant_attribute_values')->count());
    }

    public function test_cascade_borrar_tag_elimina_product_tag(): void
    {
        $graph = $this->fullGraph();

        DB::table('tags')->where('id', $graph['tag'])->delete();

        $this->assertSame(0, DB::table('product_tag')->count());
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_set_null_en_tax_rates_deja_referencias_nulas(): void
    {
        $productTax = $this->taxRate('IVA19');
        $variantTax = $this->taxRate('IVA5');
        $categoryTax = $this->taxRate('EXENTO');

        $category = $this->category('cat-con-impuesto', null, $categoryTax);
        $product = $this->product(null, null, $productTax, 'P-SET', 'Producto SET NULL');
        $variant = $this->variant($product, 'SKU-SET', $variantTax);

        DB::table('tax_rates')->where('id', $productTax)->delete();
        DB::table('tax_rates')->where('id', $variantTax)->delete();
        DB::table('tax_rates')->where('id', $categoryTax)->delete();

        $this->assertNull(DB::table('products')->where('id', $product)->value('tax_rate_id'));
        $this->assertNull(DB::table('product_variants')->where('id', $variant)->value('tax_rate_id'));
        $this->assertNull(DB::table('categories')->where('id', $category)->value('default_tax_rate_id'));
    }

    public function test_set_null_en_category_y_brand_deja_producto_sin_referencias(): void
    {
        $category = $this->category('cat-x');
        $brand = $this->brand('brand-x');
        $product = $this->product($category, $brand, null, 'P-X', 'Producto X');

        DB::table('categories')->where('id', $category)->delete();
        DB::table('brands')->where('id', $brand)->delete();

        $row = DB::table('products')->where('id', $product)->first();
        $this->assertNull($row->category_id);
        $this->assertNull($row->brand_id);
    }

    public function test_soft_delete_persiste_fila_y_no_libera_unique(): void
    {
        $category = $this->category('cat-soft');
        $brand = $this->brand('brand-soft');
        $product = $this->product(null, null, null, 'P-SOFT', 'Producto soft');
        $variant = $this->variant($product, 'SKU-SOFT');

        $now = now();

        DB::table('categories')->where('id', $category)->update(['deleted_at' => $now]);
        DB::table('brands')->where('id', $brand)->update(['deleted_at' => $now]);
        DB::table('products')->where('id', $product)->update(['deleted_at' => $now]);
        DB::table('product_variants')->where('id', $variant)->update(['deleted_at' => $now]);

        $this->assertNotNull(DB::table('categories')->where('id', $category)->value('deleted_at'));
        $this->assertNotNull(DB::table('brands')->where('id', $brand)->value('deleted_at'));
        $this->assertNotNull(DB::table('products')->where('id', $product)->value('deleted_at'));
        $this->assertNotNull(DB::table('product_variants')->where('id', $variant)->value('deleted_at'));

        $this->assertViolatesUnique(fn () => $this->category('cat-soft'));
        $this->assertViolatesUnique(fn () => $this->brand('brand-soft'));
        $this->assertViolatesUnique(fn () => DB::table('products')->insert([
            'name' => 'Producto soft duplicado',
            'internal_code' => 'P-SOFT',
        ]));
        $this->assertViolatesUnique(fn () => DB::table('product_variants')->insert([
            'product_id' => $product,
            'sku' => 'SKU-SOFT',
        ]));
    }

    /**
     * @return array<string, int>
     */
    private function fullGraph(): array
    {
        $tax = $this->taxRate('IVA19');
        $category = $this->category('cat-root', null, $tax);
        $brand = $this->brand('brand-1');
        $tag = $this->tag('tag-1');
        $attribute = $this->attribute('Color');
        $value = $this->attributeValue($attribute, 'Rojo');
        $product = $this->product($category, $brand, $tax, 'P-1', 'Producto 1');
        $variant = $this->variant($product, 'SKU-1', $tax);
        $barcode = $this->barcode($variant, '1234567890123');

        DB::table('product_tag')->insert(['product_id' => $product, 'tag_id' => $tag]);
        DB::table('product_variant_attribute_values')->insert([
            'variant_id' => $variant,
            'attribute_value_id' => $value,
        ]);

        return [
            'tax_rate' => $tax,
            'category' => $category,
            'brand' => $brand,
            'tag' => $tag,
            'attribute' => $attribute,
            'attribute_value' => $value,
            'product' => $product,
            'variant' => $variant,
            'barcode' => $barcode,
        ];
    }

    private function taxRate(string $code): int
    {
        return DB::table('tax_rates')->insertGetId([
            'code' => $code,
            'name' => 'Impuesto '.$code,
            'rate' => 19.00,
        ]);
    }

    private function category(string $slug, ?int $parentId = null, ?int $taxRateId = null): int
    {
        return DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'name' => 'Categoria '.$slug,
            'slug' => $slug,
            'default_tax_rate_id' => $taxRateId,
        ]);
    }

    private function brand(string $slug): int
    {
        return DB::table('brands')->insertGetId([
            'name' => 'Marca '.$slug,
            'slug' => $slug,
        ]);
    }

    private function tag(string $slug): int
    {
        return DB::table('tags')->insertGetId([
            'name' => 'Etiqueta '.$slug,
            'slug' => $slug,
        ]);
    }

    private function attribute(string $name): int
    {
        return DB::table('attributes')->insertGetId(['name' => $name]);
    }

    private function attributeValue(int $attributeId, string $value): int
    {
        return DB::table('attribute_values')->insertGetId([
            'attribute_id' => $attributeId,
            'value' => $value,
        ]);
    }

    private function product(?int $categoryId, ?int $brandId, ?int $taxRateId, string $internalCode, string $name): int
    {
        return DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'internal_code' => $internalCode,
            'tax_rate_id' => $taxRateId,
        ]);
    }

    private function variant(int $productId, string $sku, ?int $taxRateId = null): int
    {
        return DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => $sku,
            'price' => 1000.00,
            'cost_price' => 500.0000,
            'tax_rate_id' => $taxRateId,
        ]);
    }

    private function barcode(int $variantId, string $barcode): int
    {
        return DB::table('product_barcodes')->insertGetId([
            'variant_id' => $variantId,
            'barcode' => $barcode,
        ]);
    }

    private function assertViolatesUnique(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba una violacion UNIQUE.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    private function assertQueryException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba una QueryException por restriccion de base de datos.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
