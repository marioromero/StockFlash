<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// DB-001 · Planes (demo/free + comerciales §4.5) y módulos conocidos.
// free: 50 variantes / 500 ventas mes. core siempre activo.

class PlanDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'demo', 'name' => 'Demo Vitrina', 'max_products' => 0, 'max_monthly_sales' => 0, 'products_enabled' => ['*'], 'modules_enabled' => ['*'], 'sort_order' => 0],
            ['code' => 'free', 'name' => 'Gratis Persistente', 'max_products' => 50, 'max_monthly_sales' => 500, 'products_enabled' => ['pos', 'bodega'], 'modules_enabled' => ['core', 'pos', 'bodega'], 'sort_order' => 1],
            ['code' => 'bodega', 'name' => 'Bodega', 'max_products' => 0, 'max_monthly_sales' => 0, 'products_enabled' => ['bodega'], 'modules_enabled' => ['core', 'bodega'], 'sort_order' => 2],
            ['code' => 'starter', 'name' => 'Starter', 'max_products' => 0, 'max_monthly_sales' => 0, 'products_enabled' => ['pos'], 'modules_enabled' => ['core', 'pos'], 'sort_order' => 3],
            ['code' => 'pro', 'name' => 'Pro', 'max_products' => 0, 'max_monthly_sales' => 0, 'products_enabled' => ['pos', 'bodega', 'fiscal'], 'modules_enabled' => ['core', 'pos', 'bodega', 'fiscal'], 'sort_order' => 4],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'max_products' => 0, 'max_monthly_sales' => 0, 'products_enabled' => ['*'], 'modules_enabled' => ['*'], 'sort_order' => 5],
        ];
        foreach ($plans as $plan) {
            DB::table('plan_definitions')->updateOrInsert(
                ['code' => $plan['code']],
                [...$plan, 'description' => null, 'max_branches' => 0, 'max_pos_stations' => 0,
                 'max_channels' => 0, 'max_users' => 0,
                 'products_enabled' => json_encode($plan['products_enabled']),
                 'modules_enabled' => json_encode($plan['modules_enabled']),
                 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        foreach (['core' => 1, 'pos' => 0, 'bodega' => 0, 'syncwp' => 0, 'fiscal' => 0, 'compras' => 0, 'analitica' => 0, 'control' => 0] as $code => $enabled) {
            DB::table('installation_modules')->updateOrInsert(
                ['module_code' => $code],
                ['is_enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
