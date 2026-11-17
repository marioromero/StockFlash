<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

class CatalogMastersCrudTest extends CatalogMastersTestCase
{
    public function test_categoria_se_crea_con_slug_estable_y_padre_opcional(): void
    {
        $this->actingAs($this->admin)
            ->post('/catalog/masters/categories', $this->categoryPayload([
                'name' => 'Bebidas',
                'slug' => null,
                'sort_order' => 3,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $category = DB::table('categories')->where('name', 'Bebidas')->first();

        $this->assertNotNull($category);
        $this->assertSame('bebidas', $category->slug);
        $this->assertNull($category->parent_id);
        $this->assertSame(3, (int) $category->sort_order);

        $parent = Category::create(['name' => 'Padre', 'slug' => 'padre']);

        $this->actingAs($this->admin)
            ->post('/catalog/masters/categories', $this->categoryPayload([
                'name' => 'Hija',
                'slug' => 'hija',
                'parent_id' => $parent->id,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Hija', 'parent_id' => $parent->id]);
    }

    public function test_categoria_impide_padre_propio_ciclos_y_fk_invalida(): void
    {
        $a = Category::create(['name' => 'A', 'slug' => 'a']);
        $b = Category::create(['name' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/categories/{$a->id}", $this->categoryPayload([
                'name' => 'A',
                'parent_id' => $a->id,
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->admin)
            ->put("/catalog/masters/categories/{$a->id}", $this->categoryPayload([
                'name' => 'A',
                'parent_id' => $b->id,
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->admin)
            ->post('/catalog/masters/categories', $this->categoryPayload([
                'name' => 'Huérfana',
                'parent_id' => 999999,
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($a->fresh()->parent_id);
        $this->assertDatabaseMissing('categories', ['name' => 'Huérfana']);
    }

    public function test_slug_de_categoria_es_unico_incluido_soft_deleted_y_no_se_renombra_al_editar(): void
    {
        $deleted = Category::create(['name' => 'Eliminada', 'slug' => 'duplicado']);
        $deleted->delete();

        $this->actingAs($this->admin)
            ->post('/catalog/masters/categories', $this->categoryPayload([
                'name' => 'Nueva',
                'slug' => 'duplicado',
            ]))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, DB::table('categories')->count());

        $existing = Category::create(['name' => 'Original', 'slug' => 'original']);
        $payload = $this->categoryPayload(['name' => 'Nombre Nuevo']);
        unset($payload['slug']);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/categories/{$existing->id}", $payload)
            ->assertRedirect(route('catalog.masters.index'));

        $existing->refresh();

        $this->assertSame('Nombre Nuevo', $existing->name);
        $this->assertSame('original', $existing->slug);
    }

    public function test_categoria_usa_soft_delete_y_bloquea_bajas_con_hijas_o_productos(): void
    {
        $free = Category::create(['name' => 'Libre', 'slug' => 'libre']);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/categories/{$free->id}")
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertSoftDeleted('categories', ['id' => $free->id]);

        $parent = Category::create(['name' => 'Padre', 'slug' => 'padre']);
        $child = Category::create(['name' => 'Hija', 'slug' => 'hija', 'parent_id' => $parent->id]);
        $child->delete();

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/categories/{$parent->id}")
            ->assertSessionHasErrors('category');

        $this->assertNull($parent->fresh()->deleted_at);

        $withProduct = Category::create(['name' => 'Con producto', 'slug' => 'con-producto']);
        $this->insertProduct(['category_id' => $withProduct->id]);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/categories/{$withProduct->id}")
            ->assertSessionHasErrors('category');

        $this->assertNull($withProduct->fresh()->deleted_at);
    }

    public function test_marcas_slug_unico_soft_delete_y_bloqueo_por_productos(): void
    {
        $this->actingAs($this->admin)
            ->post('/catalog/masters/brands', $this->brandPayload([
                'name' => 'Marca Generada',
                'slug' => null,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $brand = DB::table('brands')->where('name', 'Marca Generada')->first();
        $this->assertNotNull($brand);
        $this->assertSame('marca-generada', $brand->slug);

        $softDeleted = Brand::create(['name' => 'Borrada', 'slug' => 'marca-duplicada']);
        $softDeleted->delete();

        $this->actingAs($this->admin)
            ->post('/catalog/masters/brands', $this->brandPayload([
                'name' => 'Otra',
                'slug' => 'marca-duplicada',
            ]))
            ->assertSessionHasErrors('slug');

        $free = Brand::create(['name' => 'Libre', 'slug' => 'libre']);
        $this->actingAs($this->admin)
            ->delete("/catalog/masters/brands/{$free->id}")
            ->assertRedirect(route('catalog.masters.index'));
        $this->assertSoftDeleted('brands', ['id' => $free->id]);

        $referenced = Brand::create(['name' => 'Referenciada', 'slug' => 'referenciada']);
        $this->insertProduct(['brand_id' => $referenced->id]);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/brands/{$referenced->id}")
            ->assertSessionHasErrors('brand');

        $this->assertNull($referenced->fresh()->deleted_at);
    }

    public function test_etiquetas_crud_borrado_fisico_y_bloqueo_por_productos(): void
    {
        $this->actingAs($this->admin)
            ->post('/catalog/masters/tags', $this->tagPayload([
                'name' => 'Promoción',
                'slug' => null,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $tag = Tag::query()->where('name', 'Promoción')->firstOrFail();
        $this->assertSame('promocion', $tag->slug);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/tags/{$tag->id}", $this->tagPayload([
                'name' => 'Promociones',
                'slug' => 'promociones',
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $tag->refresh();
        $this->assertSame('Promociones', $tag->name);
        $this->assertSame('promociones', $tag->slug);

        $productId = $this->insertProduct();
        DB::table('product_tag')->insert(['product_id' => $productId, 'tag_id' => $tag->id]);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/tags/{$tag->id}")
            ->assertSessionHasErrors('tag');

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);

        DB::table('product_tag')->where('tag_id', $tag->id)->delete();

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/tags/{$tag->id}")
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_atributos_y_valores_respetan_unicidad_compuesta_y_borrado_seguro(): void
    {
        $this->actingAs($this->admin)
            ->post('/catalog/masters/attributes', $this->attributePayload(['name' => 'Color']))
            ->assertRedirect(route('catalog.masters.index'));

        $attribute = Attribute::query()->where('name', 'Color')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/catalog/masters/attributes', $this->attributePayload(['name' => 'Color']))
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post('/catalog/masters/attribute-values', $this->attributeValuePayload($attribute->id, ['value' => 'Rojo']))
            ->assertRedirect(route('catalog.masters.index'));

        $this->actingAs($this->admin)
            ->post('/catalog/masters/attribute-values', $this->attributeValuePayload($attribute->id, ['value' => 'Rojo']))
            ->assertSessionHasErrors('value');

        $other = Attribute::create(['name' => 'Talla']);
        $this->actingAs($this->admin)
            ->post('/catalog/masters/attribute-values', $this->attributeValuePayload($other->id, ['value' => 'Rojo']))
            ->assertRedirect(route('catalog.masters.index'));

        $value = AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->where('value', 'Rojo')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/catalog/masters/attribute-values/{$value->id}", $this->attributeValuePayload($attribute->id, ['value' => 'Rojo']))
            ->assertRedirect(route('catalog.masters.index'));

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/attributes/{$attribute->id}")
            ->assertSessionHasErrors('attribute');

        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);

        $productId = $this->insertProduct();
        $variantId = $this->insertVariant($productId);
        DB::table('product_variant_attribute_values')->insert([
            'variant_id' => $variantId,
            'attribute_value_id' => $value->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/attribute-values/{$value->id}")
            ->assertSessionHasErrors('attribute_value');

        $this->assertDatabaseHas('attribute_values', ['id' => $value->id]);

        DB::table('product_variant_attribute_values')->where('attribute_value_id', $value->id)->delete();

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/attribute-values/{$value->id}")
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseMissing('attribute_values', ['id' => $value->id]);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/attributes/{$attribute->id}")
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
    }

    public function test_tasas_code_unico_rango_exencion_y_default_atomico(): void
    {
        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                'code' => 'IVA19',
                'name' => 'IVA 19',
                'rate' => '19.00',
                'is_exempt' => false,
                'is_default' => true,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                'code' => 'IVA19',
                'name' => 'Duplicada',
            ]))
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload(['rate' => '100.01']))
            ->assertSessionHasErrors('rate');

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload(['rate' => '-0.01']))
            ->assertSessionHasErrors('rate');

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                'code' => 'EXENTO',
                'name' => 'Exento',
                'rate' => '0.00',
                'is_exempt' => true,
                'is_default' => true,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $iva = TaxRate::query()->where('code', 'IVA19')->firstOrFail();
        $exempt = TaxRate::query()->where('code', 'EXENTO')->firstOrFail();

        $this->assertFalse((bool) $iva->fresh()->is_default);
        $this->assertTrue((bool) $exempt->fresh()->is_default);
        $this->assertTrue((bool) $exempt->fresh()->is_exempt);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/tax-rates/{$exempt->id}", $this->taxRatePayload([
                'code' => 'EXENTO',
                'name' => 'Exento',
                'rate' => '0.00',
                'is_exempt' => true,
                'is_default' => false,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertFalse((bool) $exempt->fresh()->is_default);
        $this->assertFalse((bool) $iva->fresh()->is_default);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/tax-rates/{$iva->id}", $this->taxRatePayload([
                'code' => 'IVA19',
                'name' => 'IVA 19',
                'rate' => '19.00',
                'is_default' => true,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertTrue((bool) $iva->fresh()->is_default);
    }

    public function test_tasa_referenciada_por_categoria_no_se_elimina(): void
    {
        $taxRate = TaxRate::create([
            'code' => 'IVA-REF',
            'name' => 'IVA referenciada',
            'rate' => '19.00',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Con impuesto',
            'slug' => 'con-impuesto',
            'default_tax_rate_id' => $taxRate->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/tax-rates/{$taxRate->id}")
            ->assertSessionHasErrors('tax_rate');

        $this->assertDatabaseHas('tax_rates', ['id' => $taxRate->id]);
    }

    public function test_payload_extra_no_se_persiste_y_allowlist_rechaza_columnas_sensibles(): void
    {
        $this->actingAs($this->admin)
            ->post('/catalog/masters/categories', $this->categoryPayload([
                'name' => 'Con extras',
                'slug' => 'con-extras',
                'cost_price' => 999,
                'description' => 'No es parte del contrato',
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $row = (array) DB::table('categories')->where('slug', 'con-extras')->first();

        $this->assertArrayNotHasKey('cost_price', $row);
        $this->assertArrayNotHasKey('description', $row);

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                'code' => 'PORC-EXTRA',
                'percentage' => '19',
            ]))
            ->assertSessionHasErrors('percentage');

        $this->assertDatabaseMissing('tax_rates', ['code' => 'PORC-EXTRA']);
    }

    public function test_auditoria_registra_actor_recurso_id_y_revierte_si_falla(): void
    {
        $payload = $this->tagPayload(['name' => 'Auditada', 'slug' => 'auditada']);

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tags', $payload)
            ->assertRedirect(route('catalog.masters.index'));

        $tag = Tag::query()->where('slug', 'auditada')->firstOrFail();
        $created = DB::table('audit_logs')->where('event', 'catalog.tags.created')->first();

        $this->assertNotNull($created);
        $this->assertSame('catalog', $created->module_code);
        $this->assertSame('tags', $created->auditable_type);
        $this->assertSame($tag->id, (int) $created->auditable_id);
        $this->assertSame($this->admin->id, (int) $created->user_id);
        $this->assertNull($created->branch_id);

        $newValues = json_decode((string) $created->new_values, true);
        $this->assertSame('Auditada', $newValues['name']);
        $this->assertSame('auditada', $newValues['slug']);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/tags/{$tag->id}", $this->tagPayload([
                'name' => 'Auditada editada',
                'slug' => 'auditada-editada',
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $updated = DB::table('audit_logs')->where('event', 'catalog.tags.updated')->first();
        $this->assertNotNull($updated);
        $this->assertSame('Auditada', json_decode((string) $updated->old_values, true)['name']);
        $this->assertSame('Auditada editada', json_decode((string) $updated->new_values, true)['name']);

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/tags/{$tag->id}")
            ->assertRedirect(route('catalog.masters.index'));

        $deleted = DB::table('audit_logs')->where('event', 'catalog.tags.deleted')->first();
        $this->assertNotNull($deleted);
        $this->assertSame($tag->id, (int) $deleted->auditable_id);
    }

    public function test_auditoria_fallida_revierte_la_transaccion_completa(): void
    {
        // Se registra ANTES de la primera petición: el controlador resuelto
        // por la ruta conserva la instancia de AuditLogger durante la prueba.
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function record(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                ?int $branchId = null,
                ?array $old = null,
                ?array $new = null,
                string $moduleCode = 'core',
                bool $isSecurityEvent = false,
                bool $isPersonalDataAccess = false,
            ): void {
                throw new RuntimeException('audit failure');
            }
        });

        $this->withoutExceptionHandling();

        $caught = null;

        try {
            $this->actingAs($this->admin)
                ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'Sin auditoría']));
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'La excepción de auditoría debía propagarse.');
        $this->assertSame('audit failure', $caught->getMessage());
        $this->assertDatabaseMissing('tags', ['name' => 'Sin auditoría']);
    }

    public function test_licencia_suspendida_fuera_de_gracia_bloquea_escrituras_y_con_gracia_permite(): void
    {
        $tag = Tag::create(['name' => 'Existente', 'slug' => 'existente']);
        $this->setLicense(status: 'suspended', graceUntil: null);

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'Bloqueada']))
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->put("/catalog/masters/tags/{$tag->id}", $this->tagPayload([
                'name' => 'No permitida',
                'slug' => 'no-permitida',
            ]))
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->delete("/catalog/masters/tags/{$tag->id}")
            ->assertSessionHasErrors('read_only');

        $this->assertDatabaseMissing('tags', ['name' => 'Bloqueada']);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Existente']);

        $this->setLicense(status: 'suspended', graceUntil: now()->addDay()->toDateString());

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'En gracia']))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseHas('tags', ['name' => 'En gracia']);

        $this->deleteLicense();

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'Sin licencia']))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseHas('tags', ['name' => 'Sin licencia']);
    }

    public function test_props_de_pagina_no_exponen_costos_ni_secretos(): void
    {
        TaxRate::create([
            'code' => 'IVA-DEMO',
            'name' => 'IVA demo',
            'rate' => '19.00',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Demo', 'slug' => 'demo']);
        Brand::create(['name' => 'Demo', 'slug' => 'demo']);
        Tag::create(['name' => 'Demo', 'slug' => 'demo']);
        $attribute = Attribute::create(['name' => 'Color']);
        AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Rojo']);

        $this->actingAs($this->admin)
            ->get('/catalog/masters')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Masters')
                ->where('can_manage', true)
                ->where('read_only', false)
                ->has('tax_rates', 1)
                ->has('categories', 1)
                ->has('brands', 1)
                ->has('tags', 1)
                ->has('attributes', 1)
                ->where('tax_rates.0.rate', '19.00')
                ->where('categories.0.slug', 'demo')
                ->where('attributes.0.values.0.value', 'Rojo')
                ->where('tax_rates', fn ($rows): bool => collect($rows)->every(
                    fn ($row): bool => ! array_key_exists('cost_price', $row)
                        && ! array_key_exists('percentage', $row)
                        && ! array_key_exists('installation_key', $row)
                ))
                ->where('categories', fn ($rows): bool => collect($rows)->every(
                    fn ($row): bool => ! array_key_exists('cost_price', $row)
                        && ! array_key_exists('installation_key', $row)
                ))
            );

        $this->assertSame('demo', $category->slug);
    }
}
