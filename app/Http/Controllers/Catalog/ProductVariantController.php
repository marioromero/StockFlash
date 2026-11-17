<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Catalog\ProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Catalog\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestión de variantes dentro de un producto.
 *
 * Las reglas de cantidad por tipo (simple = 1, variable >= 2) se revalidan de
 * forma transaccional en el servicio para evitar carreras entre request y
 * guardado.
 */
class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductCatalogService $service) {}

    public function store(ProductVariantRequest $request, Product $product): RedirectResponse
    {
        $this->service->createVariant(
            $product,
            $request->validated(),
            $this->actorCanViewCost($request),
        );

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Variante creada correctamente.');
    }

    public function update(
        ProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
    ): RedirectResponse {
        $this->service->updateVariant(
            $product,
            $variant,
            $request->validated(),
            $this->actorCanViewCost($request),
        );

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Variante actualizada correctamente.');
    }

    public function destroy(
        Request $request,
        Product $product,
        ProductVariant $variant,
    ): RedirectResponse {
        $this->service->deleteVariant(
            $product,
            $variant,
            $this->actorCanViewCost($request),
        );

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Variante eliminada lógicamente.');
    }

    private function actorCanViewCost(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && CheckPermission::allows($user, 'catalog.view_cost');
    }
}
