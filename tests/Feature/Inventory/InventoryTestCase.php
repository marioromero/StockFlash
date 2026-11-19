<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Inventory\InventoryDecimal;
use App\Support\Inventory\StockLedger;
use Database\Seeders\PlanDefinitionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de pruebas INVCORE-001. Solo SQLite en memoria; nunca base remota.
 */
abstract class InventoryTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'INVCORE-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'INVCORE-001 exige SQLite :memory:.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanDefinitionsSeeder::class);
        $this->setLicense();
        $this->enableInventoryModule();

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Inventario',
            'email' => 'admin-inventario@prueba.cl',
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
            'name' => 'Usuario Inventario',
            'email' => "usuario-inventario-{$suffix}@prueba.cl",
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
            'code' => "INV-{$suffix}",
            'name' => 'Sucursal Inventario '.$suffix,
            'type' => 'store',
            'is_active' => true,
            'requires_cash_control' => true,
        ], $overrides));
    }

    protected function role(string $code): Role
    {
        return Role::query()->where('code', $code)->firstOrFail();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function makeRoleWithPermissions(array $permissions): Role
    {
        $role = Role::create([
            'code' => 'inventory_'.Str::lower(Str::random(8)),
            'name' => 'Rol Inventario '.Str::upper(Str::random(4)),
            'authority_level' => 30,
            'is_system' => 0,
        ]);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('code', $permission)->value('id');

            if ($permissionId !== null) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        return $role;
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

    protected function enableInventoryModule(): void
    {
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'inventory'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function disableInventoryModule(): void
    {
        foreach (['inventory', 'pos', 'bodega'] as $code) {
            DB::table('installation_modules')->updateOrInsert(
                ['module_code' => $code],
                ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function makeProductVariant(?string $sku = null): array
    {
        $sku ??= 'SKU-'.Str::upper(Str::random(10));
        $suffix = Str::lower(Str::random(6));

        $productId = (int) DB::table('products')->insertGetId([
            'type' => 'simple',
            'name' => 'Producto inventario '.$suffix,
            'tracks_stock' => 1,
            'is_sellable' => 1,
            'is_active' => 1,
            'origin' => 'pos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = (int) DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => $sku,
            'price' => '1000.00',
            'cost_price' => '500.0000',
            'unit' => 'unit',
            'sold_by_weight' => 0,
            'allow_price_override' => 1,
            'combo_deducts_components' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $variantId];
    }

    protected function createStockRow(
        int $branchId,
        int $variantId,
        string $onHand = '0.000',
        string $reserved = '0.000',
        ?string $lastMovementAt = null,
    ): void {
        DB::table('current_stock')->insertOrIgnore([
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'last_movement_at' => $lastMovementAt,
            'updated_at' => now(),
        ]);
    }

    protected function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function recordMovement(int $branchId, int $variantId, string $type, string $quantity, array $overrides = []): StockMovement
    {
        return $this->ledger()->recordMovement(array_merge([
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'type' => $type,
            'quantity' => $quantity,
            'user_id' => $this->admin->id,
        ], $overrides));
    }

    protected function assertQuantity(string $expected, mixed $actual): void
    {
        $this->assertSame(
            $expected,
            InventoryDecimal::formatQuantity(InventoryDecimal::quantityFromStorage($actual)),
        );
    }

    protected function assertCost(string $expected, mixed $actual): void
    {
        $this->assertSame(
            $expected,
            InventoryDecimal::formatCost((int) InventoryDecimal::costFromStorage($actual)),
        );
    }
}
