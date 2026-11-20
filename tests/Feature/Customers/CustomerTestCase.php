<?php

namespace Tests\Feature\Customers;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanDefinitionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de pruebas CUSCORE-001. Solo SQLite en memoria; nunca base remota.
 *
 * La clave de índice ciego es determinista y exclusiva de pruebas. No se lee
 * ni se imprime ningún secreto real.
 */
abstract class CustomerTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'CUSCORE-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'CUSCORE-001 exige SQLite :memory:.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Claves de prueba deterministas. APP_KEY permite ejercer Laravel Crypt
        // sin leer .env; BLIND_INDEX_KEY es la clave HMAC de pruebas.
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.cipher' => 'AES-256-CBC',
            'privacy.blind_index_key' => str_repeat('b', 64),
        ]);

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanDefinitionsSeeder::class);
        $this->setLicense();
        $this->enableCustomersModule();

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Clientes',
            'email' => 'admin-clientes@prueba.cl',
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
            'name' => 'Usuario Clientes',
            'email' => "usuario-clientes-{$suffix}@prueba.cl",
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
            'code' => "CUS-{$suffix}",
            'name' => 'Sucursal Clientes '.$suffix,
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
            'code' => 'customers_'.Str::lower(Str::random(8)),
            'name' => 'Rol Clientes '.Str::upper(Str::random(4)),
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

    protected function enableCustomersModule(): void
    {
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'customers'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function disableCustomersModule(): void
    {
        foreach (['customers', 'pos'] as $code) {
            DB::table('installation_modules')->updateOrInsert(
                ['module_code' => $code],
                ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    protected function usePosFallbackWithoutCustomersRow(): void
    {
        DB::table('installation_modules')->where('module_code', 'customers')->delete();
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeGroup(array $overrides = []): CustomerGroup
    {
        $suffix = Str::upper(Str::random(5));

        return CustomerGroup::create(array_merge([
            'code' => 'GRP-'.$suffix,
            'name' => 'Grupo '.$suffix,
            'default_discount_percent' => '0.00',
            'max_discount_percent' => null,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function customerPayload(array $overrides = []): array
    {
        $suffix = Str::upper(Str::random(6));

        return array_merge([
            'type' => 'person',
            'name' => 'Cliente '.$suffix,
            'trade_name' => null,
            'rut' => null,
            'email' => null,
            'phone' => null,
            'business_activity' => null,
            'fiscal_address' => null,
            'fiscal_commune' => null,
            'fiscal_city' => null,
            'customer_group_id' => null,
            'default_discount_percent' => null,
            'max_discount_percent' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCustomer(array $overrides = []): Customer
    {
        $this->actingAs($this->admin)
            ->post('/customers', $this->customerPayload($overrides))
            ->assertRedirect();

        return Customer::query()->latest('id')->firstOrFail();
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
