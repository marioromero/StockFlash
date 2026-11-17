<?php

namespace Tests\Feature\Catalog;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de pruebas de CATMAST-001. Toda la suite usa SQLite :memory:.
 */
abstract class CatalogMastersTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'CATMAST-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'CATMAST-001 exige SQLite :memory:.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->setLicense();

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Catálogo',
            'email' => 'admin-catalogo@prueba.cl',
            'global_role_id' => $this->adminRole->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeUser(array $overrides = []): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create(array_merge([
            'name' => 'Usuario Catálogo',
            'email' => "usuario-catalogo-{$suffix}@prueba.cl",
            'password' => 'ContrasenaSegura123!',
            'is_active' => true,
            'must_change_password' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeBranch(array $overrides = []): Branch
    {
        $suffix = Str::upper(Str::random(5));

        return Branch::create(array_merge([
            'code' => "CAT-{$suffix}",
            'name' => 'Sucursal '.$suffix,
            'type' => 'store',
            'is_active' => true,
            'requires_cash_control' => true,
        ], $overrides));
    }

    protected function role(string $code): Role
    {
        return Role::query()->where('code', $code)->firstOrFail();
    }

    protected function makeNoPermissionRole(): Role
    {
        return Role::create([
            'code' => 'catalog_none_'.Str::lower(Str::random(6)),
            'name' => 'Sin catálogo',
            'authority_level' => 10,
            'is_system' => 0,
        ]);
    }

    protected function makeBranchUser(Branch $branch, string|Role $role, array $overrides = []): User
    {
        $roleId = $role instanceof Role ? $role->id : $this->role($role)->id;

        $user = $this->makeUser(array_merge([
            'default_branch_id' => $branch->id,
            'global_role_id' => null,
        ], $overrides));

        BranchUser::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $roleId,
        ]);

        return $user;
    }

    protected function setLicense(string $status = 'active', ?string $graceUntil = null): void
    {
        DB::table('license_states')->updateOrInsert(
            ['installation_key' => 'test-installation'],
            [
                'status' => $status,
                'plan_code' => 'demo',
                'max_users' => 0,
                'max_branches' => 0,
                'grace_until' => $graceUntil,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    protected function deleteLicense(): void
    {
        DB::table('license_states')->delete();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function taxRatePayload(array $overrides = []): array
    {
        $suffix = Str::upper(Str::random(5));

        return array_merge([
            'code' => "TASA-{$suffix}",
            'name' => 'Tasa '.$suffix,
            'rate' => '19.00',
            'is_exempt' => false,
            'is_default' => false,
            'fiscal_code' => null,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function categoryPayload(array $overrides = []): array
    {
        $suffix = Str::lower(Str::random(6));

        return array_merge([
            'parent_id' => null,
            'name' => 'Categoría '.$suffix,
            'slug' => null,
            'max_discount_percent' => null,
            'default_tax_rate_id' => null,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function brandPayload(array $overrides = []): array
    {
        $suffix = Str::lower(Str::random(6));

        return array_merge([
            'name' => 'Marca '.$suffix,
            'slug' => null,
            'description' => null,
            'logo_url' => null,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function tagPayload(array $overrides = []): array
    {
        $suffix = Str::lower(Str::random(6));

        return array_merge([
            'name' => 'Etiqueta '.$suffix,
            'slug' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function attributePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Atributo '.Str::lower(Str::random(6)),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function attributeValuePayload(int $attributeId, array $overrides = []): array
    {
        return array_merge([
            'attribute_id' => $attributeId,
            'value' => 'Valor '.Str::lower(Str::random(6)),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function insertProduct(array $overrides = []): int
    {
        return (int) DB::table('products')->insertGetId(array_merge([
            'category_id' => null,
            'brand_id' => null,
            'type' => 'simple',
            'name' => 'Producto '.Str::lower(Str::random(6)),
            'tracks_stock' => 1,
            'is_sellable' => 1,
            'is_active' => 1,
            'origin' => 'pos',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function insertVariant(int $productId): int
    {
        return (int) DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'price' => 1000,
            'cost_price' => 500,
            'unit' => 'unit',
            'sold_by_weight' => 0,
            'allow_price_override' => 1,
            'combo_deducts_components' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
