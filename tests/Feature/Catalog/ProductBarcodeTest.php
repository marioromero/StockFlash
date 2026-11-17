<?php

namespace Tests\Feature\Catalog;

use App\Models\ProductVariant;
use App\Support\Catalog\CatalogUniqueViolation;
use App\Support\Catalog\ProductCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

class ProductBarcodeTest extends ProductTestCase
{
    public function test_gestiona_varios_codigos_y_desplaza_el_primario(): void
    {
        $product = $this->createSimpleProduct();
        $variant = $product->variants()->firstOrFail();

        $firstId = $this->addBarcode($product, $variant, ['barcode' => '7800000000011', 'is_primary' => true]);
        $secondId = $this->addBarcode($product, $variant, ['barcode' => '7800000000028', 'is_primary' => true]);

        $this->assertSame(0, (int) DB::table('product_barcodes')->where('id', $firstId)->value('is_primary'));
        $this->assertSame(1, (int) DB::table('product_barcodes')->where('id', $secondId)->value('is_primary'));
        $this->assertSame(2, DB::table('product_barcodes')->where('variant_id', $variant->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'catalog.product_barcodes.primary_displaced']);
    }

    public function test_actualiza_y_elimina_codigo(): void
    {
        $product = $this->createSimpleProduct();
        $variant = $product->variants()->firstOrFail();
        $barcodeId = $this->addBarcode($product, $variant, ['barcode' => '7800000000035']);

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}/variants/{$variant->id}/barcodes/{$barcodeId}", [
                'barcode' => '7800000000042',
                'type' => 'internal',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_barcodes', [
            'id' => $barcodeId,
            'barcode' => '7800000000042',
            'type' => 'internal',
            'is_primary' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}/variants/{$variant->id}/barcodes/{$barcodeId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('product_barcodes', ['id' => $barcodeId]);
    }

    public function test_detalle_ordena_el_codigo_primario_primero(): void
    {
        $product = $this->createSimpleProduct();
        $variant = $product->variants()->firstOrFail();

        $secondaryId = $this->addBarcode($product, $variant, [
            'barcode' => '7800000000011',
            'is_primary' => false,
        ]);
        $primaryId = $this->addBarcode($product, $variant, [
            'barcode' => '7800000000028',
            'is_primary' => true,
        ]);

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$product->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.barcodes.0.id', $primaryId)
                ->where('selected.variants.0.barcodes.0.is_primary', true)
                ->where('selected.variants.0.barcodes.1.id', $secondaryId)
                ->where('selected.variants.0.barcodes.1.is_primary', false)
            );
    }

    public function test_barcode_es_unico_global_entre_variantes(): void
    {
        $product = $this->createVariableProduct();
        $variants = $product->variants()->orderBy('id')->get();
        $this->addBarcode($product, $variants[0], ['barcode' => '7800000000059']);

        $this->actingAs($this->admin)
            ->post(
                "/catalog/products/{$product->id}/variants/{$variants[1]->id}/barcodes",
                $this->barcodePayload(['barcode' => '7800000000059']),
            )
            ->assertSessionHasErrors('barcode');

        $this->assertSame(1, DB::table('product_barcodes')->where('barcode', '7800000000059')->count());
    }

    public function test_valida_tipo_de_codigo(): void
    {
        $product = $this->createSimpleProduct();
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(
                "/catalog/products/{$product->id}/variants/{$variant->id}/barcodes",
                $this->barcodePayload(['type' => 'qr']),
            )
            ->assertSessionHasErrors('type');
    }

    public function test_codigo_de_otra_variante_en_la_ruta_responde_404(): void
    {
        $product = $this->createVariableProduct();
        $variants = $product->variants()->orderBy('id')->get();
        $barcodeId = $this->addBarcode($product, $variants[0], ['barcode' => '7800000000066']);

        $this->actingAs($this->admin)
            ->put(
                "/catalog/products/{$product->id}/variants/{$variants[1]->id}/barcodes/{$barcodeId}",
                $this->barcodePayload(['barcode' => '7800000000073']),
            )
            ->assertNotFound();
    }

    public function test_duplicado_directo_en_base_es_rechazado(): void
    {
        $product = $this->createSimpleProduct();
        $variant = $product->variants()->firstOrFail();
        $this->addBarcode($product, $variant, ['barcode' => '7800000000080']);

        $this->expectException(QueryException::class);
        DB::table('product_barcodes')->insert([
            'variant_id' => $variant->id,
            'barcode' => '7800000000080',
            'type' => 'ean13',
            'is_primary' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fallo_de_unicidad_revierte_desplazamiento_primario(): void
    {
        $product = $this->createSimpleProduct();
        $variant = $product->variants()->firstOrFail();
        $primaryId = $this->addBarcode($product, $variant, ['barcode' => '7800000000097', 'is_primary' => true]);
        $duplicateId = $this->addBarcode($product, $variant, ['barcode' => '7800000000103']);

        $service = app(ProductCatalogService::class);
        $this->assertInstanceOf(ProductCatalogService::class, $service);

        try {
            $service->createBarcode($variant, [
                'barcode' => '7800000000103',
                'type' => 'ean13',
                'is_primary' => true,
            ]);
            $this->fail('Se esperaba una violación UNIQUE mapeada a validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('barcode', $exception->errors());
        }

        $this->assertSame(1, (int) DB::table('product_barcodes')->where('id', $primaryId)->value('is_primary'));
        $this->assertSame(0, (int) DB::table('product_barcodes')->where('id', $duplicateId)->value('is_primary'));
        $this->assertSame(2, DB::table('product_barcodes')->where('variant_id', $variant->id)->count());
    }
}
