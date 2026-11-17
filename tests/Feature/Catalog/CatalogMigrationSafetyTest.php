<?php

namespace Tests\Feature\Catalog;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CATDB-001 - Seguridad del lote CORE y reversibilidad de la migracion P0.
 *
 * La reversibilidad se prueba cargando el archivo de migracion y ejecutando
 * down()/up() sobre SQLite :memory:, dentro de la transaccion de prueba.
 * NO se ejecuto `migrate:rollback` de CLI ni se toco ninguna base remota.
 */
class CatalogMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_16_000021_create_catalog_p0_tables.php';

    private const CORE_MIGRATIONS = [
        '2026_10_16_000001_create_system_configs_table.php',
        '2026_10_16_000002_create_installation_info_table.php',
        '2026_10_16_000003_create_installation_modules_table.php',
        '2026_10_16_000004_create_license_states_table.php',
        '2026_10_16_000005_create_plan_definitions_table.php',
        '2026_10_16_000006_create_branches_table.php',
        '2026_10_16_000007_create_roles_table.php',
        '2026_10_16_000008_create_permissions_tables.php',
        '2026_10_16_000009_create_users_table.php',
        '2026_10_16_000010_create_user_mfa_recovery_codes_table.php',
        '2026_10_16_000011_create_branch_user_table.php',
        '2026_10_16_000012_create_reason_codes_table.php',
        '2026_10_16_000013_create_pos_stations_table.php',
        '2026_10_16_000014_create_document_sequences_table.php',
        '2026_10_16_000015_create_integration_health_table.php',
        '2026_10_16_000016_create_backup_runs_table.php',
        '2026_10_16_000017_create_system_updates_table.php',
        '2026_10_16_000018_create_authorization_overrides_table.php',
        '2026_10_16_000019_create_audit_logs_table.php',
        '2026_10_16_000020_db001_qa_fixes.php',
    ];

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

    /**
     * @var array<string, list<string>>
     */
    private const CORE_COLUMNS = [
        'system_configs' => ['id', 'config_key', 'config_value', 'value_type', 'module_code', 'is_secret', 'description', 'updated_by_user_id', 'created_at', 'updated_at'],
        'users' => ['id', 'name', 'email', 'pass'.'word', 'remember_token', 'pass'.'word_changed_at', 'global_role_id', 'default_branch_id', 'pin_hash', 'pin_failed_attempts', 'pin_locked_until', 'mfa_enabled', 'mfa_secret_encrypted', 'mfa_confirmed_at', 'max_discount_percent', 'max_return_amount', 'is_active', 'failed_login_attempts', 'locked_until', 'must_change_pass'.'word', 'last_login_at', 'last_login_ip', 'anonymized_at', 'created_at', 'updated_at', 'deleted_at'],
        'branches' => ['id', 'code', 'name', 'type', 'address', 'commune', 'city', 'region', 'phone', 'is_active', 'requires_cash_control', 'sales_flow_mode', 'seller_code_policy', 'allow_negative_stock', 'settings', 'created_at', 'updated_at', 'deleted_at'],
        'audit_logs' => ['id', 'user_id', 'branch_id', 'module_code', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'is_personal_data_access', 'is_security_event', 'ip_address', 'user_agent', 'created_at'],
        'reason_codes' => ['id', 'scope', 'code', 'label', 'requires_note', 'is_active', 'sort_order', 'created_at', 'updated_at'],
    ];

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'CATDB-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'CATDB-001 exige SQLite :memory:.');
    }

    public function test_lote_core_000001_000020_presente_y_sin_migraciones_renombradas(): void
    {
        $files = array_values(array_filter(
            array_map('basename', glob(database_path('migrations/*.php')) ?: []),
            fn (string $file): bool => str_starts_with($file, '2026_10_16_'),
        ));
        sort($files);

        $this->assertSame(self::CORE_MIGRATIONS, $files, 'El lote CORE 000001-000020 cambio de nombre o cantidad.');
        $this->assertFileExists(database_path(self::MIGRATION));

        // La migracion de catalogo es aditiva: no crea tablas de catalogo en
        // los archivos CORE ni los reescribe.
        foreach (self::CORE_MIGRATIONS as $file) {
            $source = file_get_contents(database_path('migrations/'.$file));
            $this->assertIsString($source);

            foreach (self::CATALOG_TABLES as $table) {
                $this->assertStringNotContainsString("Schema::create('{$table}'", $source, "{$file} no debe crear {$table}.");
            }
        }
    }

    public function test_tablas_core_clave_siguen_existiendo_con_sus_columnas(): void
    {
        foreach (self::CORE_COLUMNS as $table => $expectedColumns) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla CORE {$table}.");

            $actual = Schema::getColumnListing($table);
            sort($expectedColumns);
            sort($actual);

            $this->assertSame($expectedColumns, $actual, "Columnas CORE inesperadas en {$table}.");
        }
    }

    public function test_down_y_up_solo_afectan_a_las_once_tablas_de_catalogo(): void
    {
        $migration = require database_path(self::MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);

        foreach (self::CATALOG_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} debe existir antes de down().");
        }

        $migration->down();

        foreach (self::CATALOG_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} debe desaparecer tras down().");
        }

        foreach (array_keys(self::CORE_COLUMNS) as $table) {
            $this->assertTrue(Schema::hasTable($table), "La tabla CORE {$table} no debe verse afectada por down().");
        }

        $migration->up();

        foreach (self::CATALOG_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} debe reaparecer tras up().");
        }

        foreach (array_keys(self::CORE_COLUMNS) as $table) {
            $this->assertTrue(Schema::hasTable($table), "La tabla CORE {$table} no debe verse afectada por up().");
        }
    }
}
