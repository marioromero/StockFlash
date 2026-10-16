<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * DB-001: semillas mínimas contractuales (Parte 2 §13.1). Idempotentes.
     * Sin cuenta demo (§13.2 jamás en free/DEV) ni datos reales.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemConfigsSeeder::class,
            PlanDefinitionsSeeder::class,
            ReasonCodesSeeder::class,
        ]);
    }
}
