<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Support\Catalog\CatalogWriteGuard;
use App\Support\Catalog\ProductCatalogService;
use App\Support\Catalog\ProductPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado y CRUD de productos (CATPROD-001 · CAT-01/03/04/05/07/08).
 *
 * La lectura exige `catalog.view`; las mutaciones `catalog.manage`. El costo y
 * sus derivados solo se serializan con `catalog.view_cost`. El catálogo es
 * común a la instalación: no usa `branch.access` ni acepta `branch_id` de la
 * request para ampliar el ámbito de autorización.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCatalogService $service,
        private readonly ProductPresenter $presenter,
        private readonly CatalogWriteGuard $guard,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $search = mb_substr($search, 0, 100);
        $canViewCost = $this->actorCanViewCost($request);

        $products = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'tags:id,name',
                'variants' => fn ($query) => $query->orderBy('id')->select(['id', 'product_id', 'sku']),
            ])
            ->withCount('variants')
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('internal_code', 'like', $like)
                        ->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', $like))
                        ->orWhereHas(
                            'variants.barcodes',
                            fn ($query) => $query->where('barcode', 'like', $like),
                        );
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Catalog/Products', $this->pageProps($request, $canViewCost) + [
            'products' => [
                'data' => collect($products->items())
                    ->map(fn (Product $product): array => $this->presenter->listRow($product))
                    ->values()
                    ->all(),
                'links' => $products->linkCollection()->values()->all(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
            'filters' => ['q' => $search],
            'selected' => null,
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        $canViewCost = $this->actorCanViewCost($request);
        $product->load([
            'category:id,name',
            'brand:id,name',
            'tags:id,name',
            'variants.attributeValues.attribute:id,name',
            'variants.barcodes',
        ]);

        $products = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'tags:id,name',
                'variants' => fn ($query) => $query->orderBy('id')->select(['id', 'product_id', 'sku']),
            ])
            ->withCount('variants')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Catalog/Products', $this->pageProps($request, $canViewCost) + [
            'products' => [
                'data' => collect($products->items())
                    ->map(fn (Product $item): array => $this->presenter->listRow($item))
                    ->values()
                    ->all(),
                'links' => $products->linkCollection()->values()->all(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
            'filters' => ['q' => ''],
            'selected' => $this->presenter->detail($product, $canViewCost),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->service->createProduct(
            $request->validated(),
            $this->actorCanViewCost($request),
        );

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Producto creado correctamente.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->updateProduct(
            $product,
            $request->validated(),
            $this->actorCanViewCost($request),
        );

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->deleteProduct($product);

        return redirect()
            ->route('catalog.products.index')
            ->with('success', 'Producto eliminado lógicamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, bool $canViewCost): array
    {
        $user = $request->user();

        return [
            'catalogs' => [
                'categories' => Category::query()
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Category $category): array => [
                        'id' => (int) $category->id,
                        'name' => $category->name,
                    ])
                    ->values()
                    ->all(),
                'brands' => Brand::query()
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Brand $brand): array => [
                        'id' => (int) $brand->id,
                        'name' => $brand->name,
                    ])
                    ->values()
                    ->all(),
                'tax_rates' => TaxRate::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (TaxRate $taxRate): array => [
                        'id' => (int) $taxRate->id,
                        'code' => $taxRate->code,
                        'name' => $taxRate->name,
                    ])
                    ->values()
                    ->all(),
                'tags' => Tag::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Tag $tag): array => [
                        'id' => (int) $tag->id,
                        'name' => $tag->name,
                    ])
                    ->values()
                    ->all(),
                'attributes' => Attribute::query()
                    ->with(['values' => fn ($query) => $query->orderBy('value')->select(['id', 'attribute_id', 'value'])])
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Attribute $attribute): array => [
                        'id' => (int) $attribute->id,
                        'name' => $attribute->name,
                        'values' => $attribute->values
                            ->map(fn ($value): array => [
                                'id' => (int) $value->id,
                                'value' => $value->value,
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'can_manage' => $user !== null && CheckPermission::allows($user, 'catalog.manage'),
            'can_view_cost' => $canViewCost,
            'read_only' => $this->guard->isReadOnly(),
        ];
    }

    private function actorCanViewCost(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && CheckPermission::allows($user, 'catalog.view_cost');
    }
}
