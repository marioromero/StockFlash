<?php

namespace Tests\Feature\PosStations;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\PosStation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanDefinitionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Base de pruebas POSSTATION-001. Solo SQLite en memoria; nunca base remota.
 */
abstract class PosStationTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'POSSTATION-001 solo se prueba en SQLite.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'POSSTATION-001 exige SQLite :memory:.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanDefinitionsSeeder::class);
        $this->setLicense();
        $this->enablePosModule();

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Estaciones',
            'email' => 'admin-estaciones@prueba.cl',
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
            'name' => 'Usuario Estaciones',
            'email' => "usuario-estaciones-{$suffix}@prueba.cl",
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
            'code' => "POS-{$suffix}",
            'name' => 'Sucursal Estaciones '.$suffix,
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
            'code' => 'posstations_'.Str::lower(Str::random(8)),
            'name' => 'Rol Estaciones '.Str::upper(Str::random(4)),
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

    protected function enablePosModule(): void
    {
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function disablePosModule(): void
    {
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    protected function removePosModuleRow(): void
    {
        DB::table('installation_modules')->where('module_code', 'pos')->delete();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function stationPayload(array $overrides = []): array
    {
        $suffix = Str::upper(Str::random(6));

        return array_merge([
            'code' => 'POS-'.$suffix,
            'name' => 'Estación '.$suffix,
            'printer_profile' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function insertStation(int $branchId, array $overrides = [], ?string $deviceTokenHash = null): PosStation
    {
        $suffix = Str::upper(Str::random(8));

        $station = PosStation::create(array_merge([
            'branch_id' => $branchId,
            'code' => 'POS-'.$suffix,
            'name' => 'Estación fixture '.$suffix,
            'printer_profile' => null,
            'is_active' => true,
            'last_seen_at' => null,
        ], $overrides));

        if ($deviceTokenHash !== null) {
            DB::table('pos_stations')
                ->where('id', $station->id)
                ->update(['device_token_hash' => $deviceTokenHash]);
        }

        return $station->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createStation(User $actor, array $overrides = []): PosStation
    {
        if (! array_key_exists('branch_id', $overrides)) {
            $overrides['branch_id'] = $this->makeBranch()->id;
        }

        $this->actingAs($actor)
            ->post('/settings/pos-stations', $this->stationPayload($overrides))
            ->assertRedirect();

        return PosStation::query()->latest('id')->firstOrFail();
    }

    protected function cookieFrom(TestResponse $response, string $name): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $response->getCookie($name);
            }
        }

        return null;
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
