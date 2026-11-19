<?php

namespace Tests\Feature\Inventory;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación de inventario: solo lectura, determinista y acotada.
 */
class StockReconciliationTest extends InventoryTestCase
{
    public function test_informe_limpio_no_escribe_y_marca_reservas_diferidas(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-001');
        $this->recordMovement($branch->id, $variantId, 'initial', '5.000');

        $before = $this->snapshot();

        $exit = Artisan::call('inventory:reconcile');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('result=CLEAN', $output);
        $this->assertStringContainsString('reserved_check=deferred', $output);
        $this->assertStringContainsString('tabla stock_reservations ausente', $output);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_detecta_balance_distinto_sin_reescribir_y_es_repetible(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-002');
        $this->recordMovement($branch->id, $variantId, 'initial', '5.000');
        DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->update(['on_hand' => '4.000']);

        $before = $this->snapshot();

        $firstExit = Artisan::call('inventory:reconcile');
        $firstOutput = Artisan::output();
        $secondExit = Artisan::call('inventory:reconcile');
        $secondOutput = Artisan::output();

        $this->assertSame(1, $firstExit);
        $this->assertSame(1, $secondExit);
        $this->assertSame($firstOutput, $secondOutput, 'La salida debe ser determinista.');
        $this->assertStringContainsString('MISMATCH balance_mismatch', $firstOutput);
        $this->assertStringContainsString('on_hand=4.000', $firstOutput);
        $this->assertStringContainsString('latest_balance_after=5.000', $firstOutput);
        $this->assertSame($before, $this->snapshot(), 'La reconciliación no debe modificar datos.');
    }

    public function test_usa_orden_de_anexado_cuando_occurred_at_esta_retrodatado(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-BACKDATED');

        $first = $this->recordMovement($branch->id, $variantId, 'initial', '5.000', [
            'occurred_at' => '2026-01-02 10:00:00',
        ]);
        $second = $this->recordMovement($branch->id, $variantId, 'adjustment', '3.000', [
            'occurred_at' => '2026-01-01 09:00:00',
        ]);

        $this->assertGreaterThan($first->id, $second->id, 'El segundo movimiento debe anexarse después.');

        $stock = DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertQuantity('8.000', $stock->on_hand);
        $this->assertQuantity('8.000', $second->balance_after);

        $before = $this->snapshot();

        $exit = Artisan::call('inventory:reconcile', ['--json' => true]);
        $output = Artisan::output();
        $payload = json_decode($output, true);

        $this->assertSame(0, $exit, $output);
        $this->assertSame('CLEAN', $payload['result']);
        $this->assertCount(0, $payload['mismatches']);
        $this->assertStringNotContainsString('balance_mismatch', $output);
        $this->assertSame($before, $this->snapshot(), 'La reconciliación no debe modificar datos.');
    }

    public function test_detecta_existencia_sin_movimientos(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-003');
        $this->createStockRow($branch->id, $variantId, '2.000');

        $exit = Artisan::call('inventory:reconcile');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('MISMATCH balance_without_movements', $output);
    }

    public function test_detecta_reversas_duplicadas_sin_corregirlas(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-004');
        $original = $this->recordMovement($branch->id, $variantId, 'initial', '1.000');

        for ($i = 0; $i < 2; $i++) {
            DB::table('stock_movements')->insert([
                'branch_id' => $branch->id,
                'variant_id' => $variantId,
                'type' => 'reversal',
                'quantity' => '-1.000',
                'balance_after' => '0.000',
                'unit_cost' => null,
                'source_type' => null,
                'source_id' => null,
                'reservation_id' => null,
                'reverses_movement_id' => $original->id,
                'user_id' => null,
                'reason_code_id' => null,
                'note' => null,
                'occurred_at' => now(),
            ]);
        }

        $before = $this->snapshot();
        $exit = Artisan::call('inventory:reconcile');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('MISMATCH duplicate_reversal', $output);
        $this->assertStringContainsString('reverses_movement_id='.$original->id, $output);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_filtro_por_sucursal_y_codigo(): void
    {
        $branchA = $this->makeBranch(['code' => 'REC-A']);
        $branchB = $this->makeBranch(['code' => 'REC-B']);
        [, $variantA] = $this->makeProductVariant('REC-A-1');
        [, $variantB] = $this->makeProductVariant('REC-B-1');
        $this->createStockRow($branchA->id, $variantA, '1.000');
        $this->createStockRow($branchB->id, $variantB, '2.000');

        $exit = Artisan::call('inventory:reconcile', ['--branch' => 'REC-A', '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exit);
        $this->assertSame($branchA->id, $payload['branch']['id']);
        $this->assertCount(1, $payload['mismatches']);
        $this->assertSame($branchA->id, $payload['mismatches'][0]['branch_id']);
        $this->assertSame('REC-A', $payload['branch']['code']);
    }

    public function test_sucursal_inexistente_falla_sin_escribir(): void
    {
        $before = $this->snapshot();
        $exit = Artisan::call('inventory:reconcile', ['--branch' => 'NO-EXISTE']);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('no existe', $output);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_limite_y_chunk_acotan_la_salida(): void
    {
        $branch = $this->makeBranch();
        $variants = [];

        for ($i = 0; $i < 3; $i++) {
            [, $variants[]] = $this->makeProductVariant('REC-LIMIT-'.$i);
        }

        foreach ($variants as $variantId) {
            $this->createStockRow($branch->id, $variantId, '1.000');
        }

        $exit = Artisan::call('inventory:reconcile', ['--chunk' => 1, '--limit' => 1, '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exit);
        $this->assertSame(1, $payload['checked_stock_rows']);
        $this->assertCount(1, $payload['mismatches']);
    }

    public function test_salida_json_no_contiene_pii_ni_secretos(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-PII');
        $this->recordMovement($branch->id, $variantId, 'initial', '1.000');
        DB::table('current_stock')->where('branch_id', $branch->id)->update(['on_hand' => '0.000']);

        $exit = Artisan::call('inventory:reconcile', ['--json' => true]);
        $output = Artisan::output();
        $payload = json_decode($output, true);

        $this->assertSame(1, $exit);
        $this->assertIsArray($payload);
        $this->assertSame('MISMATCH', $payload['result']);
        $this->assertStringNotContainsString('@prueba.cl', $output);
        $this->assertStringNotContainsString('password', strtolower($output));
        $this->assertStringNotContainsString('ContrasenaSegura', $output);
    }

    public function test_verificacion_de_reservas_omite_pass_cuando_tabla_ausente(): void
    {
        $exit = Artisan::call('inventory:reconcile', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit);
        $this->assertSame('deferred', $payload['reserved_check']);
        $this->assertStringNotContainsString('reserved_check=ok', Artisan::output());
    }

    public function test_verificacion_de_reservas_cuando_la_tabla_existe_detecta_y_luego_limpia(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REC-RES');

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('variant_id');
            $table->decimal('quantity', 12, 3);
            $table->string('status', 20)->default('active');
        });

        $this->recordMovement($branch->id, $variantId, 'initial', '2.000');
        DB::table('stock_reservations')->insert([
            'branch_id' => $branch->id,
            'variant_id' => $variantId,
            'quantity' => '2.000',
            'status' => 'active',
        ]);

        $exit = Artisan::call('inventory:reconcile', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exit);
        $this->assertSame('mismatch', $payload['reserved_check']);
        $this->assertSame('reserved_mismatch', $payload['mismatches'][0]['check']);

        DB::table('current_stock')->where('branch_id', $branch->id)->update(['reserved' => '2.000']);

        $exit = Artisan::call('inventory:reconcile', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);
        $this->assertSame(0, $exit);
        $this->assertSame('ok', $payload['reserved_check']);

        Schema::dropIfExists('stock_reservations');
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function snapshot(): array
    {
        return [
            DB::table('current_stock')->orderBy('branch_id')->orderBy('variant_id')->get()
                ->map(fn (object $row): array => (array) $row)->all(),
            DB::table('stock_movements')->orderBy('id')->get()
                ->map(fn (object $row): array => (array) $row)->all(),
        ];
    }
}
