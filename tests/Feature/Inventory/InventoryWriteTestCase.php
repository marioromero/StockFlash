<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ReasonCodesSeeder;
use Database\Seeders\SystemConfigsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Base de pruebas INVWRITE-001. Solo SQLite en memoria; nunca base remota.
 */
abstract class InventoryWriteTestCase extends InventoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemConfigsSeeder::class);
        $this->seed(ReasonCodesSeeder::class);
        $this->setThreshold('0');
    }

    protected function setThreshold(string $value): void
    {
        DB::table('system_configs')->updateOrInsert(
            ['config_key' => 'stock_adjust_pin_value_threshold'],
            [
                'config_value' => $value,
                'value_type' => 'decimal',
                'module_code' => null,
                'is_secret' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    protected function setCostMethod(string $value): void
    {
        DB::table('system_configs')->updateOrInsert(
            ['config_key' => 'cost_method'],
            [
                'config_value' => $value,
                'value_type' => 'string',
                'module_code' => null,
                'is_secret' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    protected function setAllowNegativeStock(bool $allow): void
    {
        DB::table('system_configs')
            ->where('config_key', 'allow_negative_stock')
            ->update(['config_value' => $allow ? '1' : '0', 'updated_at' => now()]);
    }

    /**
     * @return object{id: int, code: string, requires_note: int}
     */
    protected function reason(string $code = 'dano'): object
    {
        $reason = DB::table('reason_codes')
            ->where('scope', 'stock_adjustment')
            ->where('code', $code)
            ->first(['id', 'code', 'requires_note']);

        $this->assertNotNull($reason, "No existe el motivo stock_adjustment/{$code}.");

        return $reason;
    }

    protected function updateVariantCosts(int $variantId, string $costPrice, ?string $lastCost = null): void
    {
        DB::table('product_variants')->where('id', $variantId)->update([
            'cost_price' => $costPrice,
            'last_cost' => $lastCost,
            'updated_at' => now(),
        ]);
    }

    protected function makeAuthorizer(
        string $roleCode = 'supervisor',
        string $pin = '1234',
        array $overrides = [],
    ): User {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return $this->makeUser(array_merge([
            'name' => 'Autorizador '.Str::upper(Str::random(4)),
            'global_role_id' => $role->id,
            'pin_hash' => Hash::make($pin),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeBranchAuthorizer(Branch $branch, string $roleCode = 'supervisor', string $pin = '1234'): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        $user = $this->makeUser([
            'name' => 'Autorizador sucursal '.Str::upper(Str::random(4)),
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
            'pin_hash' => Hash::make($pin),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ]);

        return $user;
    }

    protected function makeWriteUser(Branch $branch): User
    {
        return $this->makeBranchUser($branch, 'warehouse_keeper');
    }

    protected function token(): string
    {
        return Str::random(48);
    }

    protected function initialUrl(Branch $branch): string
    {
        return "/inventory/stock/{$branch->id}/initial";
    }

    protected function adjustmentUrl(Branch $branch): string
    {
        return "/inventory/stock/{$branch->id}/adjustment";
    }

    protected function movementCount(int $branchId, int $variantId): int
    {
        return DB::table('stock_movements')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->count();
    }
}
