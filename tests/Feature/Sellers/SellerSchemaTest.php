<?php

namespace Tests\Feature\Sellers;

use Illuminate\Support\Facades\Schema;

/**
 * Esquema aprobado de vendedores (Parte 2 §0.1 y §6.5).
 */
class SellerSchemaTest extends SellerTestCase
{
    public function test_existen_solo_las_dos_tablas_aprobadas_de_vendedores(): void
    {
        $this->assertTrue(Schema::hasTable('sellers'));
        $this->assertTrue(Schema::hasTable('branch_seller'));

        foreach ([
            'sales',
            'sale_items',
            'sale_payments',
            'sale_returns',
            'stock_reservations',
            'seller_sales',
            'seller_commissions',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La tabla {$table} no pertenece a esta ola.");
        }
    }

    public function test_sellers_tiene_columnas_tipos_defaults_y_sd_exactos(): void
    {
        $expected = [
            'id',
            'code',
            'name',
            'user_id',
            'commission_percent',
            'is_active',
            'anonymized_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $actual = array_column(Schema::getColumns('sellers'), 'name');
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);

        $columns = $this->columns('sellers');
        $this->assertFalse($columns['code']['nullable']);
        $this->assertFalse($columns['name']['nullable']);
        $this->assertTrue($columns['user_id']['nullable']);
        $this->assertFalse($columns['commission_percent']['nullable']);
        $this->assertFalse($columns['is_active']['nullable']);
        $this->assertTrue($columns['anonymized_at']['nullable']);
        $this->assertTrue($columns['deleted_at']['nullable']);

        $source = file_get_contents(database_path('migrations/2026_11_23_000026_create_seller_core_tables.php'));
        $this->assertIsString($source);

        foreach ([
            "string('code', 20)->unique()",
            "string('name', 150)",
            "unsignedBigInteger('user_id')->nullable()",
            "decimal('commission_percent', 5, 2)->default(0)",
            "unsignedTinyInteger('is_active')->default(1)",
            "dateTime('anonymized_at')->nullable()",
            'softDeletes()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertStringContainsString(
            "foreign('user_id')->references('id')->on('users')->nullOnDelete()",
            $source,
        );

        $this->assertTrue($this->hasPrimaryIndex('sellers', ['id']));
        $this->assertTrue($this->hasUniqueIndex('sellers', ['code']));

        $plainIndexes = array_values(array_filter(
            Schema::getIndexes('sellers'),
            fn (array $index): bool => ! $index['primary'] && ! $index['unique'],
        ));

        $this->assertSame([], $plainIndexes, 'No se agregan índices no aprobados.');
    }

    public function test_branch_seller_pk_compuesta_sin_id_ni_timestamps_y_fks_cascade(): void
    {
        $expected = ['seller_id', 'branch_id'];

        $actual = array_column(Schema::getColumns('branch_seller'), 'name');
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);

        $columns = $this->columns('branch_seller');
        $this->assertArrayNotHasKey('id', $columns);
        $this->assertArrayNotHasKey('created_at', $columns);
        $this->assertArrayNotHasKey('updated_at', $columns);
        $this->assertFalse($columns['seller_id']['nullable']);
        $this->assertFalse($columns['branch_id']['nullable']);

        $source = file_get_contents(database_path('migrations/2026_11_23_000026_create_seller_core_tables.php'));
        $this->assertIsString($source);

        foreach ([
            "unsignedBigInteger('seller_id')",
            "unsignedBigInteger('branch_id')",
            "primary(['seller_id', 'branch_id'])",
            "foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete()",
            "foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete()",
        ] as $needle) {
            $this->assertStringContainsString($needle, $source, "Falta definición literal: {$needle}");
        }

        $this->assertTrue($this->hasPrimaryIndex('branch_seller', ['seller_id', 'branch_id']));

        $foreignKeys = [];

        foreach (Schema::getForeignKeys('branch_seller') as $foreignKey) {
            $foreignKeys[implode(',', $foreignKey['columns'])] = $foreignKey;
        }

        $this->assertArrayHasKey('seller_id', $foreignKeys);
        $this->assertArrayHasKey('branch_id', $foreignKeys);
        $this->assertSame('sellers', $foreignKeys['seller_id']['foreign_table']);
        $this->assertSame('branches', $foreignKeys['branch_id']['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $foreignKeys['seller_id']['on_delete']));
        $this->assertSame('cascade', strtolower((string) $foreignKeys['branch_id']['on_delete']));

        $plainIndexes = array_values(array_filter(
            Schema::getIndexes('branch_seller'),
            fn (array $index): bool => ! $index['primary'],
        ));

        $this->assertSame([], $plainIndexes, 'No se agregan índices no aprobados.');
    }

    public function test_fk_user_set_null_y_soft_delete_sin_borrado_fisico(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id]);

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/deactivate")
            ->assertRedirect();

        $seller->refresh();
        $this->assertFalse($seller->is_active);
        $this->assertNull($seller->deleted_at);
        $this->assertSame(1, $this->sellerCount());

        $this->assertTrue(Schema::hasTable('sellers'));
    }
}
