<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * BOOT-001: seeders de ejemplo excluidos del dominio StockFlash.
     * No ejecutar en este hito; el modelo User de ejemplo no es esquema aprobado.
     */
    public function run(): void
    {
        // BOOT-001: sin seeds de dominio. Ejemplo de scaffold deshabilitado.
        // User::factory()->create([...]);
    }
}
