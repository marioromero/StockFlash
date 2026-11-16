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

abstract class GeneralSettingsTestCase extends TestCase
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
            'email' => 'admin-general@prueba.cl',
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
     * @return list<string>
     */
    protected function keys(): array
    {
        return [
            'country',
            'currency_code',
            'currency_decimals',
            'timezone',
            'locale',
            'prices_include_tax',
            'cash_rounding_step',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function ownedValues(): array
    {
        $values = [];

        foreach ($this->keys() as $key) {
            $values[$key] = $this->configValue($key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'country' => 'CL',
            'currency_code' => 'CLP',
            'currency_decimals' => 0,
            'timezone' => 'America/Santiago',
            'locale' => 'es-CL',
            'prices_include_tax' => true,
            'cash_rounding_step' => 1,
        ], $overrides);
    }
}
