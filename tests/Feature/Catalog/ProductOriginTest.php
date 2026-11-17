<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductOriginTest extends ProductTestCase
{
    public function test_alta_manual_ignora_origen_del_payload_y_persiste_pos(): void
    {
        foreach (['channel', 'import', 'pos'] as $index => $origin) {
            $payload = $this->productPayload([
                'name' => 'Producto origen '.$origin,
                'internal_code' => 'ORI-'.$index,
                'origin' => $origin,
            ]);

            $this->actingAs($this->admin)
                ->post('/catalog/products', $payload)
                ->assertRedirect();

            $product = Product::query()->where('internal_code', 'ORI-'.$index)->firstOrFail();

            $this->assertSame('pos', $product->origin);
        }
    }

    public function test_edicion_no_cambia_el_origen_existente(): void
    {
        $product = $this->createSimpleProduct(['origin' => 'channel']);
        DB::table('products')->where('id', $product->id)->update(['origin' => 'channel']);

        $update = $this->productPayload([
            'name' => 'Producto editado sin cambiar origen',
            'origin' => 'import',
        ]);
        $update['variants'] = [];

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}", $update)
            ->assertRedirect();

        $product->refresh();

        $this->assertSame('channel', $product->origin);
        $this->assertSame('Producto editado sin cambiar origen', $product->name);
    }

    public function test_edicion_de_producto_pos_no_permite_atribuir_canal(): void
    {
        $product = $this->createSimpleProduct();

        $update = $this->productPayload([
            'name' => 'Producto POS editado',
            'origin' => 'channel',
        ]);
        $update['variants'] = [];

        $this->actingAs($this->admin)
            ->put("/catalog/products/{$product->id}", $update)
            ->assertRedirect();

        $this->assertSame('pos', $product->refresh()->origin);
    }
}
