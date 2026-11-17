<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CATDB-001 - Esquema base de Catalogo P0 (Parte 2 5.1-5.6).
 *
 * El guard de driver/base de datos se ejecuta en `beforeRefreshingDatabase()`,
 * es decir, antes de que RefreshDatabase dispare cualquier DDL. La suite solo
 * puede correr sobre SQLite en memoria; nunca contra una base remota.
 */
class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOG_TABLES = [
        'tax_rates',
        'categories',
        'brands',
        'tags',
        'attributes',
        'attribute_values',
        'products',
        'product_variants',
        'product_barcodes',
        'product_tag',
        'product_variant_attribute_values',
    ];

    private const CORE_TABLES = [
        'system_configs',
        'installation_info',
        'installation_modules',
        'license_states',
        'plan_definitions',
        'branches',
        'roles',
        'permissions',
        'role_permissions',
        'users',
        'user_mfa_recovery_codes',
        'branch_user',
        'reason_codes',
        'pos_stations',
        'document_sequences',
        'integration_health',
        'backup_runs',
        'system_updates',
        'authorization_overrides',
        'audit_logs',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const EXPECTED_COLUMNS = [
        'tax_rates' => ['id', 'code', 'name', 'rate', 'is_exempt', 'is_default', 'fiscal_code', 'is_active', 'created_at', 'updated_at'],
        'categories' => ['id', 'parent_id', 'name', 'slug', 'max_discount_percent', 'default_tax_rate_id', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
        'brands' => ['id', 'name', 'slug', 'description', 'logo_url', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
        'tags' => ['id', 'name', 'slug', 'created_at', 'updated_at'],
        'attributes' => ['id', 'name', 'created_at', 'updated_at'],
        'attribute_values' => ['id', 'attribute_id', 'value', 'created_at', 'updated_at'],
        'products' => ['id', 'category_id', 'brand_id', 'type', 'name', 'description', 'internal_code', 'image_url', 'tax_rate_id', 'tracks_stock', 'is_sellable', 'is_active', 'origin', 'created_at', 'updated_at', 'deleted_at'],
        'product_variants' => ['id', 'product_id', 'sku', 'price', 'cost_price', 'last_cost', 'unit', 'sold_by_weight', 'max_discount_percent', 'min_price', 'allow_price_override', 'tax_rate_id', 'combo_deducts_components', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
        'product_barcodes' => ['id', 'variant_id', 'barcode', 'type', 'is_primary', 'created_at', 'updated_at'],
        'product_tag' => ['product_id', 'tag_id'],
        'product_variant_attribute_values' => ['variant_id', 'attribute_value_id'],
    ];

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'CATDB-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'CATDB-001 exige SQLite :memory:.');
    }

    public function test_solo_corren_sobre_sqlite_en_memoria(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_existen_exactamente_las_once_tablas_de_catalogo(): void
    {
        $tables = array_column(Schema::getTables(), 'name');

        foreach (self::CATALOG_TABLES as $table) {
            $this->assertTrue(in_array($table, $tables, true), "Falta la tabla {$table}.");
        }

        $expected = array_merge(self::CATALOG_TABLES, self::CORE_TABLES, ['migrations']);
        sort($expected);
        sort($tables);

        $this->assertSame($expected, $tables, 'El conjunto de tablas no es exactamente CORE + las 11 de catalogo.');
    }

    public function test_columnas_exactas_por_tabla_sin_columnas_inventadas(): void
    {
        foreach (self::EXPECTED_COLUMNS as $table => $expected) {
            $actual = array_column(Schema::getColumns($table), 'name');
            sort($expected);
            sort($actual);

            $this->assertSame($expected, $actual, "Columnas inesperadas en {$table}.");
        }
    }

    public function test_ids_autoincrementales_y_timestamps_segun_convencion(): void
    {
        foreach (self::CATALOG_TABLES as $table) {
            $columns = $this->columns($table);

            if (! in_array($table, ['product_tag', 'product_variant_attribute_values'], true)) {
                $this->assertTrue($columns['id']['auto_increment'] ?? false, "{$table}.id debe ser autoincremental.");
                $this->assertTrue($columns['created_at']['nullable'], "{$table}.created_at debe ser nullable.");
                $this->assertTrue($columns['updated_at']['nullable'], "{$table}.updated_at debe ser nullable.");
            }
        }

        foreach (['product_tag', 'product_variant_attribute_values'] as $pivot) {
            $columns = $this->columns($pivot);
            $this->assertArrayNotHasKey('id', $columns, "{$pivot} no debe tener id.");
            $this->assertArrayNotHasKey('created_at', $columns, "{$pivot} no debe tener timestamps.");
            $this->assertArrayNotHasKey('updated_at', $columns, "{$pivot} no debe tener timestamps.");
        }

        foreach (['categories', 'brands', 'products', 'product_variants'] as $table) {
            $this->assertArrayHasKey('deleted_at', $this->columns($table), "{$table} debe tener soft delete.");
            $this->assertTrue($this->columns($table)['deleted_at']['nullable']);
        }

        foreach (['tax_rates', 'tags', 'attributes', 'attribute_values', 'product_barcodes'] as $table) {
            $this->assertArrayNotHasKey('deleted_at', $this->columns($table), "{$table} no debe tener soft delete.");
        }
    }

    public function test_defaults_aprobados(): void
    {
        $expected = [
            'tax_rates' => ['is_exempt' => '0', 'is_default' => '0', 'is_active' => '1'],
            'categories' => ['sort_order' => '0', 'is_active' => '1'],
            'brands' => ['is_active' => '1'],
            'products' => ['type' => 'simple', 'tracks_stock' => '1', 'is_sellable' => '1', 'is_active' => '1', 'origin' => 'pos'],
            'product_variants' => ['price' => '0', 'cost_price' => '0', 'unit' => 'unit', 'sold_by_weight' => '0', 'allow_price_override' => '1', 'combo_deducts_components' => '1', 'is_active' => '1'],
            'product_barcodes' => ['type' => 'ean13', 'is_primary' => '0'],
        ];

        foreach ($expected as $table => $defaults) {
            $columns = $this->columns($table);

            foreach ($defaults as $column => $value) {
                $actual = $this->normalizeDefault($columns[$column]['default']);
                $this->assertContains($actual, [$value, $value.'.0'], "Default inesperado en {$table}.{$column}.");
            }
        }
    }

    public function test_indices_unicos_declarados(): void
    {
        $this->assertTrue($this->hasUniqueIndex('tax_rates', ['code']));
        $this->assertTrue($this->hasUniqueIndex('categories', ['slug']));
        $this->assertTrue($this->hasUniqueIndex('brands', ['slug']));
        $this->assertTrue($this->hasUniqueIndex('tags', ['slug']));
        $this->assertTrue($this->hasUniqueIndex('attributes', ['name']));
        $this->assertTrue($this->hasUniqueIndex('attribute_values', ['attribute_id', 'value']));
        $this->assertTrue($this->hasUniqueIndex('products', ['internal_code']));
        $this->assertTrue($this->hasUniqueIndex('product_variants', ['sku']));
        $this->assertTrue($this->hasUniqueIndex('product_barcodes', ['barcode']));
    }

    public function test_indices_no_unicos_declarados(): void
    {
        foreach ([
            ['products', 'category_id'],
            ['products', 'brand_id'],
            ['products', 'type'],
            ['products', 'is_active'],
            ['product_variants', 'is_active'],
        ] as [$table, $column]) {
            $this->assertTrue($this->hasPlainIndex($table, [$column]), "Falta indice {$table}.{$column}.");
        }
    }

    public function test_pk_compuestas_de_pivotes(): void
    {
        $this->assertTrue($this->hasPrimaryIndex('product_tag', ['product_id', 'tag_id']));
        $this->assertTrue($this->hasPrimaryIndex('product_variant_attribute_values', ['variant_id', 'attribute_value_id']));
    }

    public function test_fulltext_no_existe_en_sqlite_y_solo_se_declara_para_mysql_y_mariadb(): void
    {
        $indexes = Schema::getIndexes('products');

        $fullTextNamed = array_filter($indexes, fn (array $index): bool => str_contains(strtolower($index['name']), 'fulltext'));
        $this->assertSame([], array_values($fullTextNamed), 'SQLite no debe tener indice FULLTEXT.');

        $nameIndexed = array_filter($indexes, fn (array $index): bool => $this->sameColumns($index['columns'], ['name']));
        $this->assertSame([], array_values($nameIndexed), 'SQLite no debe indexar products.name como FULLTEXT.');

        $source = $this->migrationSource();
        $this->assertStringContainsString('getDriverName', $source);
        $this->assertStringContainsString("in_array(\$driver, ['mysql', 'mariadb'], true)", $source);
        $this->assertStringContainsString("fullText('name')", $source);
        $this->assertDoesNotMatchRegularExpression('/\$driver\s*===\s*\'mysql\'/', $source, 'El FULLTEXT no debe limitarse al driver mysql.');
    }

    /**
     * Precisiones, longitudes, enum members y booleanos literales de 5.1-5.6.
     */
    public function test_definiciones_estaticas_coinciden_con_el_contrato(): void
    {
        $source = $this->migrationSource();

        foreach ([
            "string('code', 20)",
            "string('name', 80)",
            "decimal('rate', 5, 2)",
            "string('fiscal_code', 10)",
            "string('name', 150)",
            "string('slug', 170)",
            "string('description', 255)",
            "string('logo_url', 500)",
            "string('name', 100)",
            "string('slug', 120)",
            "string('value', 100)",
            "string('name', 255)",
            "string('internal_code', 60)",
            "string('image_url', 500)",
            "string('sku', 100)",
            "decimal('price', 14, 2)",
            "decimal('cost_price', 14, 4)",
            "decimal('last_cost', 14, 4)",
            "decimal('min_price', 14, 2)",
            "string('barcode', 64)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definicion literal: {$needle}");
        }

        $this->assertGreaterThanOrEqual(2, substr_count($source, "decimal('max_discount_percent', 5, 2)"));
        $this->assertStringContainsString("unsignedSmallInteger('sort_order')", $source);

        $this->assertStringContainsString("enum('type', ['simple', 'variable', 'combo'])", $source);
        $this->assertStringContainsString("enum('origin', ['pos', 'channel', 'import'])", $source);
        $this->assertStringContainsString("enum('unit', ['unit', 'kg', 'g', 'lt', 'ml', 'm', 'box', 'other'])", $source);
        $this->assertStringContainsString("enum('type', ['ean13', 'ean8', 'upc', 'code128', 'internal', 'other'])", $source);

        foreach ([
            'is_exempt', 'is_default', 'is_active', 'tracks_stock', 'is_sellable',
            'sold_by_weight', 'allow_price_override', 'combo_deducts_components', 'is_primary',
        ] as $column) {
            $this->assertStringContainsString("unsignedTinyInteger('{$column}')", $source, "Booleano no literal: {$column}");
        }

        $this->assertStringContainsString("->restrictOnDelete()", $source);
        $this->assertStringContainsString("->cascadeOnDelete()", $source);
        $this->assertStringContainsString("->nullOnDelete()", $source);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function columns(string $table): array
    {
        $result = [];

        foreach (Schema::getColumns($table) as $column) {
            $result[$column['name']] = $column;
        }

        return $result;
    }

    private function normalizeDefault(mixed $default): string
    {
        return trim(trim((string) $default), "'\"");
    }

    /**
     * @param  list<string>  $columns
     */
    private function sameColumns(array $columns, array $expected): bool
    {
        $actual = array_map('strtolower', $columns);
        $expected = array_map('strtolower', $expected);

        return $actual === $expected;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasUniqueIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasPlainIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (! $index['unique'] && ! $index['primary'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasPrimaryIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    private function migrationSource(): string
    {
        $path = database_path('migrations/2026_11_16_000021_create_catalog_p0_tables.php');
        $source = file_get_contents($path);

        $this->assertIsString($source);

        return $source;
    }
}
