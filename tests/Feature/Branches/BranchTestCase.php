<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class BranchTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->admin = $this->makeUser(['global_role_id' => $this->adminRole->id]);
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
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeBranch(array $overrides = []): Branch
    {
        return Branch::create(array_merge([
            'code' => 'SUC-A',
            'name' => 'Sucursal A',
            'type' => 'store',
            'address' => 'Calle Ficticia 123',
            'commune' => 'Santiago',
            'city' => 'Santiago',
            'region' => 'Metropolitana',
            'phone' => '+56 2 1234 5678',
            'is_active' => true,
            'requires_cash_control' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SUC-N',
            'name' => 'Sucursal Nueva',
            'type' => 'store',
            'address' => 'Avenida Ficticia 456',
            'commune' => 'Providencia',
            'city' => 'Santiago',
            'region' => 'Metropolitana',
            'phone' => '+56 2 8765 4321',
            'is_active' => true,
            'requires_cash_control' => true,
            'sales_flow_mode' => null,
            'seller_code_policy' => null,
            'allow_negative_stock' => null,
            'settings' => null,
        ], $overrides);
    }

    protected function setMaxBranches(int $maxBranches): void
    {
        DB::table('license_states')->updateOrInsert(
            ['installation_key' => 'test-installation'],
            [
                'status' => 'active',
                'plan_code' => 'demo',
                'max_branches' => $maxBranches,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
