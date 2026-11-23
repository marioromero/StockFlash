<?php

namespace Tests\Feature\Sellers;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\PlanDefinitionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SellerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de pruebas SELLERS-001. Solo SQLite en memoria; nunca base remota.
 */
abstract class SellerTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected Seller $sentinel;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'SELLERS-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'SELLERS-001 exige SQLite :memory:.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanDefinitionsSeeder::class);
        $this->seed(SellerSeeder::class);
        $this->setLicense();
        $this->enableSellersModule();

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Vendedores',
            'email' => 'admin-vendedores@prueba.cl',
            'global_role_id' => $this->adminRole->id,
        ]);
        $this->sentinel = Seller::query()->where('code', Seller::RESERVED_CODE)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeUser(array $overrides = []): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create(array_merge([
            'name' => 'Usuario Vendedores',
            'email' => "usuario-vendedores-{$suffix}@prueba.cl",
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
            'code' => "VEN-{$suffix}",
            'name' => 'Sucursal Vendedores '.$suffix,
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
            'code' => 'sellers_'.Str::lower(Str::random(8)),
            'name' => 'Rol Vendedores '.Str::upper(Str::random(4)),
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

    protected function assignBranch(User $user, Branch $branch, string|Role $role): void
    {
        BranchUser::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $role instanceof Role ? $role->id : $this->role($role)->id,
        ]);
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

    protected function enableSellersModule(): void
    {
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'sellers'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function disableSellersModule(): void
    {
        foreach (['sellers', 'pos'] as $code) {
            DB::table('installation_modules')->updateOrInsert(
                ['module_code' => $code],
                ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    protected function usePosFallbackWithoutSellersRow(): void
    {
        DB::table('installation_modules')->where('module_code', 'sellers')->delete();
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function sellerPayload(array $overrides = []): array
    {
        $suffix = Str::upper(Str::random(6));

        return array_merge([
            'code' => 'VEN-'.$suffix,
            'name' => 'Vendedor '.$suffix,
            'user_id' => null,
            'commission_percent' => '0',
            'branches' => [],
        ], $overrides);
    }

    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $overrides
     */
    protected function createSeller(User $actor, array $branchIds, array $overrides = []): Seller
    {
        $payload = array_merge($this->sellerPayload($overrides), ['branches' => $branchIds]);

        $this->actingAs($actor)
            ->post('/sellers', $payload)
            ->assertRedirect();

        return Seller::query()->latest('id')->firstOrFail();
    }

    /**
     * Inserta una fila sin pasar por el servicio, para fixtures de alcance.
     *
     * @param  array<string, mixed>  $values
     * @param  list<int>  $branchIds
     */
    protected function insertSeller(array $values, array $branchIds): Seller
    {
        $seller = Seller::create(array_merge([
            'code' => 'VEN-'.Str::upper(Str::random(8)),
            'name' => 'Vendedor fixture',
            'user_id' => null,
            'commission_percent' => '0.00',
            'is_active' => true,
            'anonymized_at' => null,
        ], $values));

        foreach ($branchIds as $branchId) {
            DB::table('branch_seller')->insert([
                'seller_id' => $seller->id,
                'branch_id' => $branchId,
            ]);
        }

        return $seller;
    }

    protected function sellerCount(): int
    {
        return Seller::query()->where('code', '!=', Seller::RESERVED_CODE)->count();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function columns(string $table): array
    {
        $result = [];

        foreach (Schema::getColumns($table) as $column) {
            $result[$column['name']] = $column;
        }

        return $result;
    }

    /**
     * @param  list<string>  $columns
     */
    protected function hasPlainIndex(string $table, array $columns): bool
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
    protected function hasUniqueIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && ! $index['primary'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    protected function hasPrimaryIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] && $this->sameColumns($index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function sameColumns(array $columns, array $expected): bool
    {
        return array_map('strtolower', $columns) === array_map('strtolower', $expected);
    }
}
