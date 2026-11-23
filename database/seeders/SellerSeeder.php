<?php

namespace Database\Seeders;

use App\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fila reservada `sellers.code='NONE'` ("Sin asignar") para agregados.
 *
 * Idempotente y sin sucursales: es un fallback histórico de solo lectura, no
 * un vendedor editable ni una credencial. No se crea desde peticiones GET.
 */
class SellerSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DB::table('sellers')
            ->where('code', Seller::RESERVED_CODE)
            ->first();

        $values = [
            'name' => Seller::RESERVED_NAME,
            'user_id' => null,
            'commission_percent' => 0,
            'is_active' => 1,
            'anonymized_at' => null,
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('sellers')->insert([
                'code' => Seller::RESERVED_CODE,
                ...$values,
                'created_at' => now(),
            ]);

            return;
        }

        DB::table('sellers')
            ->where('id', $existing->id)
            ->update($values);
    }
}
