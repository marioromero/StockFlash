<?php

namespace Tests\Feature\Catalog;

use App\Models\TaxRate;
use App\Support\Catalog\CatalogPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class ProductPricingTest extends ProductTestCase
{
    public function test_margen_usa_precio_neto_cuando_el_precio_incluye_impuesto(): void
    {
        $this->setPricesIncludeTax(true);
        $iva = $this->makeTaxRate('19.00');

        $product = $this->createSimpleProduct(
            ['tax_rate_id' => $iva->id],
            ['price' => '119.00', 'cost_price' => '100.0000', 'tax_rate_id' => null],
        );

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$product->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pricing.prices_include_tax', true)
                ->where('selected.variants.0.net_price', '100.0000')
                ->where('selected.variants.0.margin', '0.00')
                ->where('selected.variants.0.margin_percent', '0.00')
                ->where('selected.variants.0.markup_percent', '0.00')
                ->where('selected.variants.0.effective_tax_rate', '19.00')
                ->where('selected.variants.0.tax_is_exempt', false)
                ->where('selected.variants.0.tax_source', 'product')
            );
    }

    public function test_margen_usa_el_precio_tal_cual_cuando_no_incluye_impuesto(): void
    {
        $this->setPricesIncludeTax(false);
        $iva = $this->makeTaxRate('19.00');

        $product = $this->createSimpleProduct(
            ['tax_rate_id' => $iva->id],
            ['price' => '100.00', 'cost_price' => '80.0000', 'tax_rate_id' => null],
        );

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$product->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pricing.prices_include_tax', false)
                ->where('selected.variants.0.net_price', '100.0000')
                ->where('selected.variants.0.margin', '20.00')
                ->where('selected.variants.0.margin_percent', '20.00')
                ->where('selected.variants.0.markup_percent', '25.00')
                ->where('selected.variants.0.effective_tax_rate', '19.00')
            );
    }

    public function test_hereda_la_tasa_del_producto_y_luego_la_predeterminada(): void
    {
        $this->setPricesIncludeTax(true);
        $iva = $this->makeTaxRate('19.00', ['is_default' => true]);

        $fromProduct = $this->createSimpleProduct(
            ['tax_rate_id' => $iva->id],
            ['price' => '119.00', 'cost_price' => '100.0000', 'tax_rate_id' => null],
        );

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$fromProduct->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.tax_source', 'product')
                ->where('selected.variants.0.net_price', '100.0000')
            );

        $fromDefault = $this->createSimpleProduct(
            ['tax_rate_id' => null],
            ['price' => '119.00', 'cost_price' => '100.0000', 'tax_rate_id' => null],
        );

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$fromDefault->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.tax_source', 'default')
                ->where('selected.variants.0.net_price', '100.0000')
                ->where('selected.variants.0.margin_percent', '0.00')
            );
    }

    public function test_la_tasa_de_la_variante_prevalece_y_la_exencion_no_descuenta(): void
    {
        $this->setPricesIncludeTax(true);
        $iva = $this->makeTaxRate('19.00');
        $exento = $this->makeTaxRate('0.00', ['is_exempt' => true, 'code' => 'EXENTO']);

        $product = $this->createSimpleProduct(
            ['tax_rate_id' => $iva->id],
            ['price' => '119.00', 'cost_price' => '100.0000', 'tax_rate_id' => $exento->id],
        );

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$product->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.tax_source', 'variant')
                ->where('selected.variants.0.tax_is_exempt', true)
                ->where('selected.variants.0.net_price', '119.0000')
                ->where('selected.variants.0.margin', '19.00')
                ->where('selected.variants.0.margin_percent', '15.97')
                ->where('selected.variants.0.markup_percent', '19.00')
            );
    }

    public function test_costo_cero_y_precio_cero_no_producen_inf_ni_nan(): void
    {
        $this->setPricesIncludeTax(false);

        $zero = $this->createSimpleProduct([], ['price' => '0.00', 'cost_price' => '0.0000']);

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$zero->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.net_price', '0.0000')
                ->where('selected.variants.0.margin', '0.00')
                ->where('selected.variants.0.margin_percent', null)
                ->where('selected.variants.0.markup_percent', null)
            );

        $freeCost = $this->createSimpleProduct([], ['price' => '100.00', 'cost_price' => '0.0000']);

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$freeCost->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.net_price', '100.0000')
                ->where('selected.variants.0.margin', '100.00')
                ->where('selected.variants.0.margin_percent', '100.00')
                ->where('selected.variants.0.markup_percent', null)
            );
    }

    public function test_precio_bajo_y_redondeo_porcentual(): void
    {
        $this->setPricesIncludeTax(true);
        $iva = $this->makeTaxRate('19.00');

        $product = $this->createSimpleProduct(
            ['tax_rate_id' => $iva->id],
            ['price' => '10.00', 'cost_price' => '8.0000', 'tax_rate_id' => null],
        );

        $this->actingAs($this->admin)
            ->get("/catalog/products/{$product->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.variants.0.net_price', '8.4034')
                ->where('selected.variants.0.margin', '0.40')
                ->where('selected.variants.0.margin_percent', '4.80')
                ->where('selected.variants.0.markup_percent', '5.04')
            );
    }

    public function test_actor_sin_view_cost_no_recibe_precio_neto_ni_margenes(): void
    {
        $this->setPricesIncludeTax(true);
        $iva = $this->makeTaxRate('19.00');
        $product = $this->createSimpleProduct(
            ['tax_rate_id' => $iva->id],
            ['price' => '119.00', 'cost_price' => '100.0000', 'tax_rate_id' => null],
        );

        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');

        $this->actingAs($viewer)
            ->get("/catalog/products/{$product->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_view_cost', false)
                ->missing('selected.variants.0.cost_price')
                ->missing('selected.variants.0.last_cost')
                ->missing('selected.variants.0.net_price')
                ->missing('selected.variants.0.margin')
                ->missing('selected.variants.0.margin_percent')
                ->missing('selected.variants.0.markup_percent')
                ->missing('selected.variants.0.effective_tax_rate')
            );
    }

    public function test_calculadora_cat04_convierte_neto_a_precio_guardado(): void
    {
        $pricing = app(CatalogPricing::class);
        $tax = ['rate' => 19.0, 'is_exempt' => false];
        $exempt = ['rate' => 0.0, 'is_exempt' => true];

        $marginNet = 100 / (1 - 30 / 100);
        $markupNet = 100 * (1 + 30 / 100);

        $this->assertSame(170.0, $pricing->priceFromNet($marginNet, $tax, true));
        $this->assertSame(154.7, $pricing->priceFromNet($markupNet, $tax, true));
        $this->assertSame(142.86, $pricing->priceFromNet($marginNet, $tax, false));
        $this->assertSame(142.86, $pricing->priceFromNet($marginNet, $exempt, true));

        $limit = 100 / (1 - 99.99 / 100);
        $price = $pricing->priceFromNet($limit, $tax, true);

        $this->assertTrue(is_finite($price));
        $this->assertGreaterThan(0, $price);
    }

    private function setPricesIncludeTax(bool $include): void
    {
        DB::table('system_configs')->updateOrInsert(
            ['config_key' => 'prices_include_tax'],
            [
                'config_value' => $include ? '1' : '0',
                'value_type' => 'bool',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function makeTaxRate(string $rate, array $overrides = []): TaxRate
    {
        return TaxRate::create(array_merge([
            'code' => 'TASA-'.Str::upper(Str::random(6)),
            'name' => 'Tasa de prueba',
            'rate' => $rate,
            'is_exempt' => false,
            'is_default' => false,
            'fiscal_code' => null,
            'is_active' => true,
        ], $overrides));
    }
}
