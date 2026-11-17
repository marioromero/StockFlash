<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use Inertia\Testing\AssertableInertia as Assert;

class ProductSearchTest extends ProductTestCase
{
    public function test_busca_por_nombre_sku_y_codigo_de_barras_como_escaner(): void
    {
        $byName = $this->createSimpleProduct(['name' => 'Harina sin polvos']);
        $bySku = $this->createSimpleProduct([], ['sku' => 'ESCANER-SKU-001']);
        $byBarcode = $this->createSimpleProduct(['name' => 'Producto con código']);
        $variant = $byBarcode->variants()->firstOrFail();
        $this->addBarcode($byBarcode, $variant, ['barcode' => '7801234567890']);

        $this->actingAs($this->admin)
            ->get('/catalog/products?q=Harina')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'Harina')
                ->where('products.total', 1)
                ->where('products.data.0.id', $byName->id)
            );

        $this->actingAs($this->admin)
            ->get('/catalog/products?q=ESCANER-SKU-001')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.id', $bySku->id)
            );

        $this->actingAs($this->admin)
            ->get('/catalog/products?q=7801234567890')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.id', $byBarcode->id)
            );
    }

    public function test_pagina_y_preserva_filtros_en_los_enlaces(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->actingAs($this->admin)
                ->post('/catalog/products', $this->productPayload([
                    'name' => sprintf('Buscar producto %02d', $i),
                    'internal_code' => sprintf('BUS-%02d', $i),
                ]))
                ->assertRedirect();
        }

        $this->actingAs($this->admin)
            ->get('/catalog/products?q=Buscar')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'Buscar')
                ->where('products.total', 16)
                ->where('products.current_page', 1)
                ->where('products.last_page', 2)
                ->where('products.data', fn ($data): bool => count($data) === 15)
                ->where('products.links', fn ($links): bool => collect($links)->contains(
                    fn ($link): bool => is_string($link['url']) && str_contains($link['url'], 'q=Buscar'),
                ))
            );

        $this->actingAs($this->admin)
            ->get('/catalog/products?q=Buscar&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.current_page', 2)
                ->where('products.data', fn ($data): bool => count($data) === 1)
            );
    }

    public function test_busqueda_sin_resultados_devuelve_pagina_vacia(): void
    {
        $this->createSimpleProduct(['name' => 'Producto existente']);

        $this->actingAs($this->admin)
            ->get('/catalog/products?q=inexistente-xyz')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 0)
                ->where('products.data', [])
            );
    }

    public function test_busqueda_no_expone_datos_a_usuario_sin_permiso(): void
    {
        $this->createSimpleProduct(['name' => 'Producto reservado']);
        $branch = $this->makeBranch();
        $noPermission = $this->makeBranchUser($branch, $this->makeNoPermissionRole());

        $this->actingAs($noPermission)
            ->get('/catalog/products?q=Producto')
            ->assertForbidden();
    }

    public function test_listado_incluye_skus_y_tags_del_producto(): void
    {
        // Verifica la forma del payload; no mide el número de consultas SQL.
        $tag = $this->makeTag('Promoción');
        $product = $this->createSimpleProduct(['tag_ids' => [$tag->id]]);

        $this->actingAs($this->admin)
            ->get('/catalog/products')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.variant_count', 1)
                ->where('products.data.0.skus', fn ($skus): bool => count($skus) === 1)
                ->where('products.data.0.tags.0.name', 'Promoción')
            );
    }
}
