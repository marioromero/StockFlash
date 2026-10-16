<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// DB-001 · Motivos estándar por ámbito (§4.12). Idempotente por (scope, code).

class ReasonCodesSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['override', 'oferta', 'Oferta puntual', 0], ['override', 'regalo', 'Regalo', 0],
            ['override', 'error_digitacion', 'Error de digitación', 0], ['override', 'empleado', 'Empleado', 0],
            ['stock_adjustment', 'dano', 'Daño', 0], ['stock_adjustment', 'vencido', 'Vencido', 0],
            ['stock_adjustment', 'perdida', 'Pérdida', 1], ['stock_adjustment', 'error_digitacion', 'Error de digitación', 0],
            ['waste', 'merma', 'Merma operativa', 0], ['waste', 'vencido', 'Vencido', 0],
            ['void', 'error_cobro', 'Error de cobro', 1], ['void', 'cliente_deshiste', 'Cliente desiste', 0],
            ['return', 'devolucion', 'Devolución', 0], ['return', 'falla_producto', 'Falla de producto', 1],
            ['cash_movement', 'retiro', 'Retiro de caja', 1], ['cash_movement', 'fondo', 'Fondo de caja', 0],
        ];
        $order = 0;
        foreach ($codes as [$scope, $code, $label, $note]) {
            DB::table('reason_codes')->updateOrInsert(
                ['scope' => $scope, 'code' => $code],
                ['label' => $label, 'requires_note' => $note, 'is_active' => 1,
                 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
