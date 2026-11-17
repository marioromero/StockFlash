<?php

namespace Tests\Feature\Catalog;

use App\Http\Controllers\CatalogMastersController;
use App\Http\Requests\Catalog\AttributeRequest;
use App\Http\Requests\Catalog\AttributeValueRequest;
use App\Http\Requests\Catalog\BrandRequest;
use App\Http\Requests\Catalog\CategoryRequest;
use App\Http\Requests\Catalog\TagRequest;
use App\Http\Requests\Catalog\TaxRateRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\TaxRate;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Correcciones QA CATMAST-001 (H-01..H-06) sobre SQLite :memory:.
 *
 * Las pruebas son deterministas: simulan cambios entre la validación del Form
 * Request y el guard transaccional, pero no afirman concurrencia real MariaDB.
 */
class CatalogMastersIntegrityTest extends CatalogMastersTestCase
{
    public function test_guard_transaccional_rechaza_ciclo_creado_entre_validacion_y_guardado(): void
    {
        $a = Category::create(['name' => 'A', 'slug' => 'a']);
        $b = Category::create(['name' => 'B', 'slug' => 'b']);

        $payload = $this->categoryPayload(['name' => 'A', 'parent_id' => $b->id]);
        $request = $this->categoryRequestFor($a, $payload);

        // La validación de UX (Form Request) pasa: en ese momento A y B son raíces.
        $request->validateResolved();

        // Cambio de jerarquía posterior a la validación (equivale a otra
        // transacción concurrente). El guard transaccional debe verlo.
        DB::table('categories')->where('id', $b->id)->update(['parent_id' => $a->id]);

        $caught = null;

        try {
            app(CatalogMastersController::class)->updateCategory($request, $a);
        } catch (ValidationException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'El guard transaccional debía rechazar el ciclo.');
        $this->assertArrayHasKey('parent_id', $caught->errors());

        // La mutación rechazada no se persistió: A sigue raíz.
        $this->assertNull($a->fresh()->parent_id);
        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_ciclo_normal_sigue_rechazandose_sin_escritura(): void
    {
        $a = Category::create(['name' => 'A', 'slug' => 'a']);
        $b = Category::create(['name' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/categories/{$a->id}", $this->categoryPayload([
                'name' => 'A',
                'parent_id' => $b->id,
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($a->fresh()->parent_id);
        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_dos_defaults_sin_fila_de_licencia_dejan_solo_una(): void
    {
        $this->deleteLicense();

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                'code' => 'DEFAULT-1',
                'name' => 'Default 1',
                'is_default' => true,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $this->actingAs($this->admin)
            ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                'code' => 'DEFAULT-2',
                'name' => 'Default 2',
                'is_default' => true,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $defaults = TaxRate::query()->where('is_default', true)->get();

        $this->assertCount(1, $defaults);
        $this->assertSame('DEFAULT-2', $defaults->first()->code);
        $this->assertFalse((bool) TaxRate::query()->where('code', 'DEFAULT-1')->firstOrFail()->is_default);
    }

    public function test_padre_soft_deleted_se_rechaza_al_crear_y_al_editar(): void
    {
        $deletedParent = Category::create(['name' => 'Borrada', 'slug' => 'borrada']);
        $deletedParent->delete();

        $this->actingAs($this->admin)
            ->post('/catalog/masters/categories', $this->categoryPayload([
                'name' => 'Hija nueva',
                'parent_id' => $deletedParent->id,
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Hija nueva']);

        $child = Category::create(['name' => 'Hija existente', 'slug' => 'hija-existente']);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/categories/{$child->id}", $this->categoryPayload([
                'name' => 'Hija existente',
                'parent_id' => $deletedParent->id,
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_default_desplazado_genera_evento_de_auditoria(): void
    {
        $displaced = TaxRate::create([
            'code' => 'VIEJA',
            'name' => 'Tasa vieja',
            'rate' => '19.00',
            'is_default' => true,
            'is_active' => true,
        ]);

        $new = TaxRate::create([
            'code' => 'NUEVA',
            'name' => 'Tasa nueva',
            'rate' => '19.00',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put("/catalog/masters/tax-rates/{$new->id}", $this->taxRatePayload([
                'code' => 'NUEVA',
                'name' => 'Tasa nueva',
                'rate' => '19.00',
                'is_default' => true,
            ]))
            ->assertRedirect(route('catalog.masters.index'));

        $log = DB::table('audit_logs')
            ->where('event', 'catalog.tax_rates.default_displaced')
            ->where('auditable_type', 'tax_rates')
            ->where('auditable_id', $displaced->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('catalog', $log->module_code);
        $this->assertNull($log->branch_id);

        $old = json_decode((string) $log->old_values, true);
        $newValues = json_decode((string) $log->new_values, true);

        $this->assertSame('VIEJA', $old['code']);
        $this->assertTrue($old['is_default']);
        $this->assertSame('VIEJA', $newValues['code']);
        $this->assertFalse($newValues['is_default']);

        $this->assertFalse((bool) $displaced->fresh()->is_default);
        $this->assertTrue((bool) $new->fresh()->is_default);
    }

    public function test_fallo_auditando_default_desplazado_revierte_todo(): void
    {
        $displaced = TaxRate::create([
            'code' => 'VIEJA',
            'name' => 'Tasa vieja',
            'rate' => '19.00',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Se registra ANTES de la petición para que el controlador resuelto
        // por la ruta reciba esta instancia.
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
                if ($event === 'catalog.tax_rates.default_displaced') {
                    throw new RuntimeException('audit displaced failure');
                }

                parent::record(
                    $event,
                    $auditableType,
                    $auditableId,
                    $branchId,
                    $old,
                    $new,
                    $moduleCode,
                    $isSecurityEvent,
                    $isPersonalDataAccess,
                );
            }
        });

        $this->withoutExceptionHandling();

        $caught = null;

        try {
            $this->actingAs($this->admin)
                ->post('/catalog/masters/tax-rates', $this->taxRatePayload([
                    'code' => 'NUEVA',
                    'name' => 'Tasa nueva',
                    'is_default' => true,
                ]));
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'La excepción de auditoría debía propagarse.');
        $this->assertSame('audit displaced failure', $caught->getMessage());

        $this->assertTrue((bool) $displaced->fresh()->is_default);
        $this->assertDatabaseMissing('tax_rates', ['code' => 'NUEVA']);
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_violacion_unique_de_base_se_mapea_a_422_en_cada_recurso(): void
    {
        $controller = app(CatalogMastersController::class);

        // Tax rates → code
        DB::table('tax_rates')->insert([
            'code' => 'DUP-TAX',
            'name' => 'Duplicada',
            'rate' => 19.00,
        ]);

        $this->assertMapsToValidation(
            fn () => $controller->storeTaxRate(new StubTaxRateRequest([
                'code' => 'DUP-TAX',
                'name' => 'Otra',
                'rate' => '19.00',
                'is_default' => false,
            ])),
            'code',
        );
        $this->assertSame(1, DB::table('tax_rates')->count());

        // Categories → slug
        DB::table('categories')->insert(['name' => 'Dup', 'slug' => 'dup-categoria']);

        $this->assertMapsToValidation(
            fn () => $controller->storeCategory(new StubCategoryRequest([
                'name' => 'Otra',
                'slug' => 'dup-categoria',
                'parent_id' => null,
            ])),
            'slug',
        );
        $this->assertSame(1, DB::table('categories')->count());

        // Brands → slug
        DB::table('brands')->insert(['name' => 'Dup', 'slug' => 'dup-marca']);

        $this->assertMapsToValidation(
            fn () => $controller->storeBrand(new StubBrandRequest([
                'name' => 'Otra',
                'slug' => 'dup-marca',
            ])),
            'slug',
        );
        $this->assertSame(1, DB::table('brands')->count());

        // Tags → slug
        DB::table('tags')->insert(['name' => 'Dup', 'slug' => 'dup-etiqueta']);

        $this->assertMapsToValidation(
            fn () => $controller->storeTag(new StubTagRequest([
                'name' => 'Otra',
                'slug' => 'dup-etiqueta',
            ])),
            'slug',
        );
        $this->assertSame(1, DB::table('tags')->count());

        // Attributes → name
        $attribute = Attribute::create(['name' => 'Dup Atributo']);

        $this->assertMapsToValidation(
            fn () => $controller->storeAttribute(new StubAttributeRequest([
                'name' => 'Dup Atributo',
            ])),
            'name',
        );
        $this->assertSame(1, DB::table('attributes')->count());

        // Attribute values → value (compuesto attribute_id + value)
        DB::table('attribute_values')->insert([
            'attribute_id' => $attribute->id,
            'value' => 'Dup Valor',
        ]);

        $this->assertMapsToValidation(
            fn () => $controller->storeAttributeValue(new StubAttributeValueRequest([
                'attribute_id' => $attribute->id,
                'value' => 'Dup Valor',
            ])),
            'value',
        );
        $this->assertSame(1, DB::table('attribute_values')->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function categoryRequestFor(Category $category, array $payload): CategoryRequest
    {
        $route = new class($category)
        {
            public function __construct(private readonly Category $category) {}

            public function parameter($param, $default = null): mixed
            {
                return $param === 'category' ? $this->category : $default;
            }
        };

        $request = CategoryRequest::create(
            "/catalog/masters/categories/{$category->id}",
            'PUT',
            $payload,
        );

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setRouteResolver(fn (): object => $route);

        return $request;
    }

    private function assertMapsToValidation(callable $call, string $field): void
    {
        $caught = null;

        try {
            $call();
        } catch (ValidationException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'La violación UNIQUE debía mapearse a ValidationException (422).');
        $this->assertArrayHasKey($field, $caught->errors());
    }
}

/**
 * Dobles de prueba: entregan el payload ya validado para forzar la colisión
 * directa de base (bypass deliberado del Form Request).
 */
class StubTaxRateRequest extends TaxRateRequest
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct();
    }

    public function validated($key = null, $default = null): mixed
    {
        return $key === null ? $this->payload : ($this->payload[$key] ?? $default);
    }
}

class StubCategoryRequest extends CategoryRequest
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct();
    }

    public function validated($key = null, $default = null): mixed
    {
        return $key === null ? $this->payload : ($this->payload[$key] ?? $default);
    }
}

class StubBrandRequest extends BrandRequest
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct();
    }

    public function validated($key = null, $default = null): mixed
    {
        return $key === null ? $this->payload : ($this->payload[$key] ?? $default);
    }
}

class StubTagRequest extends TagRequest
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct();
    }

    public function validated($key = null, $default = null): mixed
    {
        return $key === null ? $this->payload : ($this->payload[$key] ?? $default);
    }
}

class StubAttributeRequest extends AttributeRequest
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct();
    }

    public function validated($key = null, $default = null): mixed
    {
        return $key === null ? $this->payload : ($this->payload[$key] ?? $default);
    }
}

class StubAttributeValueRequest extends AttributeValueRequest
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct();
    }

    public function validated($key = null, $default = null): mixed
    {
        return $key === null ? $this->payload : ($this->payload[$key] ?? $default);
    }
}
