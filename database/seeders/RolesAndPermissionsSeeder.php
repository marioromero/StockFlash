<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// DB-001 · Roles de sistema + matriz acumulativa (Parte 1 §6.2-6.3, Parte 2 §13.1).
// Idempotente: updateOrCreate por code +SyncWithoutDetaching en pivote.

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = config('modules', []);

        foreach ($modules as $moduleCode => $codes) {
            foreach ($codes as $code) {
                DB::table('permissions')->updateOrInsert(
                    ['code' => $code],
                    ['module_code' => $moduleCode, 'description' => null,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
        $permId = fn (string $code) => DB::table('permissions')->where('code', $code)->value('id');

        $roles = [
            ['code' => 'seller', 'name' => 'Vendedor', 'authority_level' => 10, 'max_discount_percent' => 5, 'max_return_amount' => null],
            ['code' => 'cashier', 'name' => 'Cajero', 'authority_level' => 20, 'max_discount_percent' => 10, 'max_return_amount' => null],
            ['code' => 'warehouse_keeper', 'name' => 'Bodeguero', 'authority_level' => 20, 'max_discount_percent' => null, 'max_return_amount' => null],
            ['code' => 'supervisor', 'name' => 'Supervisor', 'authority_level' => 50, 'max_discount_percent' => 30, 'max_return_amount' => null],
            ['code' => 'central_admin', 'name' => 'Admin Central', 'authority_level' => 100, 'max_discount_percent' => 100, 'max_return_amount' => null],
        ];
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                [...$role, 'is_system' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $seller = ['catalog.view', 'inventory.view', 'sales.create_ticket', 'sales.hold',
            'sales.discount_manual', 'sales.view_own', 'customers.view', 'customers.manage', 'sellers.view_reports'];
        $cashierExtra = ['sales.direct_sale', 'sales.collect', 'sales.price_override', 'sales.edit_ticket_at_cash',
            'sales.void', 'sales.return', 'sales.reprint', 'cash.open_close', 'cash.movements'];
        $keeper = ['catalog.view', 'catalog.view_cost', 'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'inventory.receive', 'inventory.waste', 'inventory.count', 'purchasing.manage', 'sales.dispatch'];
        $supervisorExtra = ['catalog.manage', 'catalog.import', 'inventory.apply_count', 'sales.change_seller',
            'sales.review_orders', 'sales.view_branch', 'cash.approve_difference', 'cash.view_all',
            'customers.view_sensitive', 'sellers.manage', 'pricing.manage', 'fiscal.view', 'fiscal.request_credit_note',
            'integrations.view', 'reports.view_branch', 'reports.export', 'override.authorize',
            'audit.view', 'audit.view_security', 'license.view'];

        $matrix = [
            'seller' => $seller,
            'cashier' => array_unique([...$seller, ...$cashierExtra]),
            'warehouse_keeper' => $keeper,
            'supervisor' => array_unique([...$seller, ...$cashierExtra, ...$keeper, ...$supervisorExtra]),
        ];

        $allCodes = [];
        foreach ($modules as $codes) {
            $allCodes = [...$allCodes, ...$codes];
        }
        $matrix['central_admin'] = array_unique($allCodes);

        foreach ($matrix as $roleCode => $codes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            foreach (array_unique($codes) as $code) {
                if ($id = $permId($code)) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id]);
                }
            }
        }
    }
}
