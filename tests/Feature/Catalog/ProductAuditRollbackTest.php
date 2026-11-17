<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;

class ProductAuditRollbackTest extends ProductTestCase
{
    public function test_fallo_de_auditoria_en_variante_revierte_producto_tags_y_pivotes(): void
    {
        [$attribute, $value] = $this->makeAttributeValue('Color', 'Negro');
        $tag = $this->makeTag('Rollback');

        $calls = 0;
        $this->mock(AuditLogger::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('record')->andReturnUsing(function () use (&$calls): void {
                $calls++;

                if ($calls === 2) {
                    throw new RuntimeException('Fallo de auditoría simulado.');
                }
            });
        });

        $payload = $this->productPayload([
            'name' => 'Producto rollback',
            'internal_code' => 'ROLLBACK-001',
            'tag_ids' => [$tag->id],
        ]);
        $payload['variants'][0]['attribute_value_ids'] = [$value->id];

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post('/catalog/products', $payload);
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('products', ['internal_code' => 'ROLLBACK-001']);
        $this->assertSame(0, ProductVariant::query()->count());
        $this->assertSame(0, DB::table('product_tag')->count());
        $this->assertSame(0, DB::table('product_variant_attribute_values')->count());
    }

    public function test_fallo_de_auditoria_al_actualizar_revierte_campos_y_tags(): void
    {
        $product = $this->createSimpleProduct(['name' => 'Nombre original']);
        $tag = $this->makeTag('No debe quedar');

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->put("/catalog/products/{$product->id}", $this->productPayload([
                    'name' => 'Nombre cambiado',
                    'internal_code' => $product->internal_code,
                    'tag_ids' => [$tag->id],
                ]));
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría caída.', $exception->getMessage());
        }

        $this->assertSame('Nombre original', $product->refresh()->name);
        $this->assertDatabaseMissing('product_tag', ['product_id' => $product->id, 'tag_id' => $tag->id]);
    }

    public function test_licencia_suspendida_fuera_de_gracia_bloquea_escrituras(): void
    {
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['name' => 'No debe crearse']))
            ->assertSessionHasErrors('read_only');

        $this->assertDatabaseMissing('products', ['name' => 'No debe crearse']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'catalog.products.created']);

        $this->actingAs($this->admin)
            ->get('/catalog/products')
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));
    }

    public function test_licencia_suspendida_dentro_de_gracia_permite_escrituras(): void
    {
        $this->setLicense('suspended', now()->addDay()->toDateString());

        $this->actingAs($this->admin)
            ->post('/catalog/products', $this->productPayload(['name' => 'Permitido en gracia']))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Permitido en gracia']);
    }

    public function test_eliminacion_de_producto_se_audita_en_la_misma_transaccion(): void
    {
        $product = $this->createSimpleProduct();

        $this->actingAs($this->admin)
            ->delete("/catalog/products/{$product->id}")
            ->assertRedirect();

        $audit = DB::table('audit_logs')
            ->where('event', 'catalog.products.deleted')
            ->where('auditable_id', $product->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('catalog', $audit->module_code);
        $this->assertNull($audit->branch_id);
        $this->assertSame(0, Product::query()->count());
    }
}
