<?php

namespace Tests\Feature\Users;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemConfigsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class UserTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SystemConfigsSeeder::class);

        // Por defecto la instalación tiene licencia y sin topes, para que los
        // casos de negocio no choquen con el guard de licencia.
        $this->setLicense(maxUsers: 0, maxBranches: 0);

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Central',
            'email' => 'admin@prueba.cl',
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
            'name' => 'Usuario Prueba',
            'email' => "usuario-{$suffix}@prueba.cl",
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
        $suffix = Str::upper(Str::random(4));

        return Branch::create(array_merge([
            'code' => "SUC-{$suffix}",
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validUserPayload(array $overrides = []): array
    {
        $suffix = Str::lower(Str::random(8));

        return array_merge([
            'name' => 'Usuario Nuevo',
            'email' => "nuevo-{$suffix}@prueba.cl",
            'password' => 'ContrasenaTemporal123!',
            'global_role_id' => $this->role('cashier')->id,
            'default_branch_id' => null,
            'max_discount_percent' => null,
            'max_return_amount' => null,
            'assignments' => [],
        ], $overrides);
    }

    protected function setLicense(int $maxUsers = 0, int $maxBranches = 0, string $status = 'active', ?string $graceUntil = null): void
    {
        DB::table('license_states')->updateOrInsert(
            ['installation_key' => 'test-installation'],
            [
                'status' => $status,
                'plan_code' => 'demo',
                'max_users' => $maxUsers,
                'max_branches' => $maxBranches,
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

    protected function setMaxUsers(int $maxUsers): void
    {
        $this->setLicense(maxUsers: $maxUsers, maxBranches: 0);
    }

    protected function suspendLicense(?string $graceUntil = null): void
    {
        $this->setLicense(maxUsers: 0, maxBranches: 0, status: 'suspended', graceUntil: $graceUntil);
    }
}
