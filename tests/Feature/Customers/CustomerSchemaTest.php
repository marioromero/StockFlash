<?php

namespace Tests\Feature\Customers;

use Illuminate\Support\Facades\Schema;

/**
 * Esquema aprobado de clientes (Parte 2 §0.1, §6.1 y §6.2).
 */
class CustomerSchemaTest extends CustomerTestCase
{
    public function test_existen_solo_las_dos_tablas_aprobadas_de_clientes(): void
    {
        $this->assertTrue(Schema::hasTable('customer_groups'));
        $this->assertTrue(Schema::hasTable('customers'));

        foreach ([
            'customer_addresses',
            'customer_consents',
            'sales',
            'sale_items',
            'stock_reservations',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La tabla {$table} no pertenece a esta ola.");
        }
    }

    public function test_customer_groups_tiene_columnas_tipos_y_defaults_exactos(): void
    {
        $expected = [
            'id',
            'code',
            'name',
            'default_discount_percent',
            'max_discount_percent',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $actual = array_column(Schema::getColumns('customer_groups'), 'name');
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);

        $columns = $this->columns('customer_groups');
        $this->assertFalse($columns['code']['nullable']);
        $this->assertFalse($columns['name']['nullable']);
        $this->assertFalse($columns['default_discount_percent']['nullable']);
        $this->assertTrue($columns['max_discount_percent']['nullable']);
        $this->assertFalse($columns['is_active']['nullable']);
        $this->assertTrue($columns['deleted_at']['nullable']);

        $source = file_get_contents(database_path('migrations/2026_11_20_000025_create_customer_core_tables.php'));
        $this->assertIsString($source);

        foreach ([
            "string('code', 30)->unique()",
            "string('name', 100)",
            "decimal('default_discount_percent', 5, 2)->default(0)",
            "decimal('max_discount_percent', 5, 2)->nullable()",
            "unsignedTinyInteger('is_active')->default(1)",
            'softDeletes()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertTrue($this->hasPrimaryIndex('customer_groups', ['id']));
        $this->assertTrue($this->hasUniqueIndex('customer_groups', ['code']));
    }

    public function test_customers_tiene_columnas_tipos_defaults_y_fk_exactos(): void
    {
        $expected = [
            'id',
            'type',
            'name',
            'trade_name',
            'fiscal_id_encrypted',
            'fiscal_id_hash',
            'email',
            'phone',
            'business_activity',
            'fiscal_address',
            'fiscal_commune',
            'fiscal_city',
            'customer_group_id',
            'default_discount_percent',
            'max_discount_percent',
            'marketing_opt_in',
            'origin',
            'last_activity_at',
            'is_active',
            'blocked_at',
            'anonymized_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $actual = array_column(Schema::getColumns('customers'), 'name');
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);

        $columns = $this->columns('customers');
        foreach ([
            'trade_name', 'fiscal_id_encrypted', 'fiscal_id_hash', 'email', 'phone',
            'business_activity', 'fiscal_address', 'fiscal_commune', 'fiscal_city',
            'customer_group_id', 'default_discount_percent', 'max_discount_percent',
            'last_activity_at', 'blocked_at', 'anonymized_at', 'deleted_at',
        ] as $nullable) {
            $this->assertTrue($columns[$nullable]['nullable'], "{$nullable} debe ser NULL.");
        }

        foreach ([
            'type', 'name', 'marketing_opt_in', 'origin', 'is_active',
        ] as $required) {
            $this->assertFalse($columns[$required]['nullable'], "{$required} debe ser NOT NULL.");
        }

        foreach (['created_at', 'updated_at'] as $timestamp) {
            $this->assertTrue($columns[$timestamp]['nullable'], "{$timestamp} sigue la convención DATETIME NULL.");
        }

        $source = file_get_contents(database_path('migrations/2026_11_20_000025_create_customer_core_tables.php'));
        $this->assertIsString($source);

        foreach ([
            "enum('type', ['person', 'company'])->default('person')",
            "string('name', 150)",
            "string('trade_name', 150)->nullable()",
            "string('fiscal_id_encrypted', 255)->nullable()",
            "char('fiscal_id_hash', 64)->nullable()->unique()",
            "string('email', 150)->nullable()->index()",
            "string('phone', 30)->nullable()",
            "string('business_activity', 150)->nullable()",
            "string('fiscal_address', 255)->nullable()",
            "string('fiscal_commune', 100)->nullable()",
            "string('fiscal_city', 100)->nullable()",
            "unsignedBigInteger('customer_group_id')->nullable()",
            "decimal('default_discount_percent', 5, 2)->nullable()",
            "decimal('max_discount_percent', 5, 2)->nullable()",
            "unsignedTinyInteger('marketing_opt_in')->default(0)",
            "enum('origin', ['pos', 'online', 'import'])->default('pos')",
            "dateTime('last_activity_at')->nullable()",
            "unsignedTinyInteger('is_active')->default(1)",
            "dateTime('blocked_at')->nullable()",
            "dateTime('anonymized_at')->nullable()",
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertStringContainsString(
            "foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete()",
            $source,
        );
    }

    public function test_indices_unicos_email_y_fk_set_null(): void
    {
        $this->assertTrue($this->hasUniqueIndex('customers', ['fiscal_id_hash']));
        $this->assertTrue($this->hasPlainIndex('customers', ['email']));
        $this->assertTrue($this->hasPrimaryIndex('customers', ['id']));

        $foreignKeys = [];

        foreach (Schema::getForeignKeys('customers') as $foreignKey) {
            $foreignKeys[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        $this->assertArrayHasKey('customer_group_id', $foreignKeys);
        $this->assertSame('customer_groups', $foreignKeys['customer_group_id']['foreign_table']);
        $this->assertSame('set null', strtolower((string) $foreignKeys['customer_group_id']['on_delete']));
    }

    public function test_soft_delete_y_timestamps_existen_sin_borrado_fisico_de_endpoints(): void
    {
        foreach (['customer_groups', 'customers'] as $table) {
            $columns = $this->columns($table);
            $this->assertArrayHasKey('deleted_at', $columns);
            $this->assertArrayHasKey('created_at', $columns);
            $this->assertArrayHasKey('updated_at', $columns);
        }

        $customer = $this->createCustomer();

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => 0]);
        $this->assertNull($customer->refresh()->deleted_at);
    }
}
