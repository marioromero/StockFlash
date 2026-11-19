<?php

namespace Tests\Feature\Inventory;

use App\Support\Inventory\StockWriteService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes manuales de existencias (INVWRITE-001 · INV-04).
 */
class StockAdjustmentWriteTest extends InventoryWriteTestCase
{
    public function test_ajuste_positivo_registra_movimiento_motivo_y_auditoria(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-001');
        $this->createStockRow($branch->id, $variantId, '5.000', '2.000');
        $reason = $this->reason('dano');
        $token = $this->token();

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.500',
            'reason_code_id' => $reason->id,
            'note' => 'Producto dañado en bodega',
            'request_token' => $token,
        ])->assertSessionHasNoErrors();

        $movement = DB::table('stock_movements')->where('branch_id', $branch->id)->first();

        $this->assertSame('adjustment', $movement->type);
        $this->assertQuantity('1.500', $movement->quantity);
        $this->assertQuantity('6.500', $movement->balance_after);
        $this->assertSame((int) $reason->id, (int) $movement->reason_code_id);
        $this->assertSame('Producto dañado en bodega', $movement->note);
        $this->assertSame('inventory_adjustment', $movement->source_type);
        $this->assertSame(StockWriteService::sourceIdFromToken($token), (int) $movement->source_id);
        $this->assertSame((int) $this->admin->id, (int) $movement->user_id);
        $this->assertNull($movement->unit_cost);

        $stock = DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertQuantity('6.500', $stock->on_hand);
        $this->assertQuantity('2.000', $stock->reserved, 'La reserva no debe cambiar.');
        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.movement_recorded')
            ->where('branch_id', $branch->id)
            ->count());
    }

    public function test_ajuste_negativo_es_permitido_sin_aplicar_regla_pos(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-002');
        $this->createStockRow($branch->id, $variantId, '3.000', '1.000');
        $reason = $this->reason('error_digitacion');
        $this->setAllowNegativeStock(false);

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-5.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasNoErrors();

        $movement = DB::table('stock_movements')->where('branch_id', $branch->id)->first();

        $this->assertQuantity('-5.000', $movement->quantity);
        $this->assertQuantity('-2.000', $movement->balance_after);

        $stock = DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertQuantity('-2.000', $stock->on_hand);
        $this->assertQuantity('1.000', $stock->reserved);
    }

    public function test_motivo_obligatorio_activo_y_del_ambito_correcto(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-003');
        $this->createStockRow($branch->id, $variantId, '1.000');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('reason_code_id');

        $overrideReason = DB::table('reason_codes')
            ->where('scope', 'override')
            ->first(['id']);

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $overrideReason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('reason_code_id');

        $inactive = $this->reason('vencido');
        DB::table('reason_codes')->where('id', $inactive->id)->update(['is_active' => 0]);

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $inactive->id,
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('reason_code_id');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_motivo_que_exige_nota_y_limite_de_nota(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-004');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('perdida');

        $this->assertSame(1, (int) $reason->requires_note);

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('note');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'note' => str_repeat('a', 256),
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('note');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'note' => 'Pérdida detectada en conteo',
            'request_token' => $this->token(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
    }

    public function test_cantidad_cero_escala_invalida_y_overflow_no_mutan(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-005');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');

        foreach (['0', '0.000', '1.2345'] as $quantity) {
            $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'reason_code_id' => $reason->id,
                'request_token' => $this->token(),
            ])->assertSessionHasErrors('quantity');
        }

        DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->update(['on_hand' => '999999999.999']);

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_mismo_token_no_duplica_y_token_nuevo_permite_otro_ajuste(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-006');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $token = $this->token();

        $payload = [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $token,
        ];

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)->assertSessionHasErrors('request_token');

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));

        $payload['request_token'] = $this->token();

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)->assertSessionHasNoErrors();

        $this->assertSame(2, $this->movementCount($branch->id, $variantId));
    }

    public function test_saldos_secuenciales_son_calculados_por_el_servidor(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-007');
        $reason = $this->reason('dano');

        $this->actingAs($this->admin)->post($this->initialUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '5.000',
        ])->assertSessionHasNoErrors();

        foreach ([['2.000', '7.000'], ['-3.000', '4.000']] as [$quantity, $expected]) {
            $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'reason_code_id' => $reason->id,
                'request_token' => $this->token(),
            ])->assertSessionHasNoErrors();

            $latest = DB::table('stock_movements')
                ->where('branch_id', $branch->id)
                ->where('variant_id', $variantId)
                ->orderByDesc('id')
                ->first();

            $this->assertQuantity($expected, $latest->balance_after);
        }

        $this->assertQuantity(
            '4.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
    }

    public function test_campos_de_contexto_del_cliente_no_alteran_el_ajuste(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-008');
        $this->createStockRow($branch->id, $variantId, '2.000');
        $reason = $this->reason('dano');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'type' => 'waste',
            'source_type' => 'sale_item',
            'source_id' => 404,
            'user_id' => 999,
            'branch_id' => $branch->id + 50,
            'on_hand' => '999.000',
            'cost' => '0.0001',
        ])->assertSessionHasNoErrors();

        $movement = DB::table('stock_movements')->where('branch_id', $branch->id)->first();

        $this->assertSame('adjustment', $movement->type);
        $this->assertSame('inventory_adjustment', $movement->source_type);
        $this->assertSame((int) $this->admin->id, (int) $movement->user_id);
        $this->assertQuantity(
            '3.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
    }

    public function test_modulo_deshabilitado_y_licencia_solo_lectura_no_mutan(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-009');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');

        $payload = [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ];

        $this->disableInventoryModule();
        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)->assertForbidden();
        $this->assertSame(0, $this->movementCount($branch->id, $variantId));

        $this->enableInventoryModule();
        $this->setLicense('suspended', null);

        $payload['request_token'] = $this->token();
        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)->assertSessionHasErrors('read_only');
        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_reconciliacion_queda_limpia_tras_carga_y_ajuste(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('ADJ-010');
        $reason = $this->reason('dano');

        $this->actingAs($this->admin)->post($this->initialUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '5.000',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasNoErrors();

        $exit = Artisan::call('inventory:reconcile', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit);
        $this->assertSame('CLEAN', $payload['result']);
        $this->assertSame('deferred', $payload['reserved_check']);
    }

    public function test_no_se_agrega_columna_de_autorizacion_al_kardex(): void
    {
        $this->assertFalse(Schema::hasColumn('stock_movements', 'authorization_override_id'));
        $this->assertFalse(Schema::hasColumn('stock_movements', 'authorization_override'));
    }
}
