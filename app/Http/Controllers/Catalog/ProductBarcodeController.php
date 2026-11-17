<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductBarcodeRequest;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Support\Catalog\ProductCatalogService;
use Illuminate\Http\RedirectResponse;

/**
 * Códigos de barras por variante. Un máximo de un primario por variante se
 * desplaza dentro de la misma transacción, sin agregar un UNIQUE de esquema.
 */
class ProductBarcodeController extends Controller
{
    public function __construct(private readonly ProductCatalogService $service) {}

    public function store(
        ProductBarcodeRequest $request,
        Product $product,
        ProductVariant $variant,
    ): RedirectResponse {
        $this->assertVariantBelongsToProduct($product, $variant);

        $this->service->createBarcode($variant, $request->validated());

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Código de barras creado correctamente.');
    }

    public function update(
        ProductBarcodeRequest $request,
        Product $product,
        ProductVariant $variant,
        ProductBarcode $barcode,
    ): RedirectResponse {
        $this->assertVariantBelongsToProduct($product, $variant);

        $this->service->updateBarcode($variant, $barcode, $request->validated());

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Código de barras actualizado correctamente.');
    }

    public function destroy(
        Product $product,
        ProductVariant $variant,
        ProductBarcode $barcode,
    ): RedirectResponse {
        $this->assertVariantBelongsToProduct($product, $variant);

        $this->service->deleteBarcode($variant, $barcode);

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Código de barras eliminado.');
    }

    private function assertVariantBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            abort(404);
        }
    }
}
