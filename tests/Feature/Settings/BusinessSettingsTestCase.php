<?php

namespace Tests\Feature\Settings;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemConfigsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class BusinessSettingsTestCase extends TestCase
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

        $this->setLicense();

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser([
            'name' => 'Admin Central',
            'email' => 'admin-comercio@prueba.cl',
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

    protected function setLicense(
        int $maxUsers = 0,
        int $maxBranches = 0,
        string $status = 'active',
        ?string $graceUntil = null,
    ): void {
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

    protected function suspendLicense(?string $graceUntil = null): void
    {
        $this->setLicense(status: 'suspended', graceUntil: $graceUntil);
    }

    protected function configValue(string $key): ?string
    {
        return DB::table('system_configs')
            ->where('config_key', $key)
            ->value('config_value');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Comercio de Prueba SpA',
            'fiscal_id' => '76.543.210-8',
            'fiscal_name' => 'Comercio de Prueba SpA',
            'commercial_activity' => 'Venta minorista',
            'fiscal_address' => 'Av. Prueba 123',
            'fiscal_commune' => 'Santiago',
            'responsible_name' => 'Responsable Prueba',
            'responsible_email' => 'responsable@prueba.cl',
            'responsible_phone' => '+56 9 1234 5678',
        ], $overrides);
    }
}
