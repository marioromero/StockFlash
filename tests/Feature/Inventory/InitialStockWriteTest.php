<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Carga inicial manual de existencias (INVWRITE-001 · INV-05 manual).
 */
class InitialStockWriteTest extends InventoryWriteTestCase
{
    public function test_carga_inicial_registra_un_movimiento_balance_y_auditoria(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-001');

        $response = $this->actingAs($this->admin)->post($this->initialUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '5.250',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));

        $movement = DB::table('stock_movements')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertSame('initial', $movement->type);
        $this->assertQuantity('5.250', $movement->quantity);
        $this->assertQuantity('5.250', $movement->balance_after);
        $this->assertSame('inventory_initial', $movement->source_type);
        $this->assertSame($variantId, (int) $movement->source_id);
        $this->assertSame((int) $this->admin->id, (int) $movement->user_id);
        $this->assertNull($movement->unit_cost);

        $stock = DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertQuantity('5.250', $stock->on_hand);
        $this->assertQuantity('0.000', $stock->reserved);

        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.movement_recorded')
            ->where('branch_id', $branch->id)
            ->count());

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stock.data.0.initialized', true)
                ->where('can_adjust', true));
    }

    public function test_reintento_de_carga_inicial_no_duplica_existencias(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-002');

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '3.000'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '3.000'])
            ->assertSessionHasErrors('variant_id');

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
        $this->assertQuantity(
            '3.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
    }

    public function test_cantidad_cero_negativa_o_formato_invalido_no_registran(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-003');

        foreach (['0', '0.000', '-1.000'] as $quantity) {
            $this->actingAs($this->admin)
                ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => $quantity])
                ->assertSessionHasErrors('quantity');
        }

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '1.2345'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_rechaza_reinicializar_una_variante_con_historial(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-004');
        $this->recordMovement($branch->id, $variantId, 'adjustment', '2.000');

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '9.000'])
            ->assertSessionHasErrors('variant_id');

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
    }

    public function test_variante_sin_control_de_stock_o_eliminada_es_rechazada(): void
    {
        $branch = $this->makeBranch();
        [$productId, $variantId] = $this->makeProductVariant('INIT-005');

        DB::table('products')->where('id', $productId)->update(['tracks_stock' => 0]);

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '1.000'])
            ->assertSessionHasErrors('variant_id');

        DB::table('products')->where('id', $productId)->update(['tracks_stock' => 1, 'deleted_at' => now()]);

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '1.000'])
            ->assertSessionHasErrors('variant_id');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_campos_de_contexto_enviados_por_el_cliente_son_ignorados(): void
    {
        $branch = $this->makeBranch();
        [$productId, $variantId] = $this->makeProductVariant('INIT-006');

        $this->actingAs($this->admin)->post($this->initialUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '2.000',
            'type' => 'waste',
            'source_type' => 'sale_item',
            'source_id' => 999,
            'user_id' => 999,
            'branch_id' => $branch->id + 100,
            'on_hand' => '999.000',
            'cost' => '1.0000',
        ])->assertSessionHasNoErrors();

        $movement = DB::table('stock_movements')->where('branch_id', $branch->id)->first();

        $this->assertSame('initial', $movement->type);
        $this->assertSame('inventory_initial', $movement->source_type);
        $this->assertSame($variantId, (int) $movement->source_id);
        $this->assertSame((int) $this->admin->id, (int) $movement->user_id);
        $this->assertQuantity(
            '2.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
        $this->assertSame($productId, (int) DB::table('product_variants')->where('id', $variantId)->value('product_id'));
    }

    public function test_guardar_en_catalogo_no_crea_stock_ni_movimientos(): void
    {
        $beforeStock = DB::table('current_stock')->count();
        $beforeMovements = DB::table('stock_movements')->count();

        $this->actingAs($this->admin)->post('/catalog/products', [
            'type' => 'simple',
            'name' => 'Producto sin stock inicial',
            'tracks_stock' => true,
            'variants' => [[
                'sku' => 'CAT-NO-STOCK-1',
                'price' => '1000.00',
                'unit' => 'unit',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', ['sku' => 'CAT-NO-STOCK-1']);
        $this->assertSame($beforeStock, DB::table('current_stock')->count());
        $this->assertSame($beforeMovements, DB::table('stock_movements')->count());
    }

    public function test_modulo_deshabilitado_responde_403_sin_mutar(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-007');
        $this->disableInventoryModule();

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '1.000'])
            ->assertForbidden();

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_licencia_solo_lectura_rechaza_con_422_y_sin_filas(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-008');
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '1.000'])
            ->assertSessionHasErrors('read_only');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('current_stock')->count());
    }

    public function test_rbac_y_sucursal_son_autorizados_en_servidor(): void
    {
        $branch = $this->makeBranch();
        $other = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('INIT-009');

        $viewOnly = $this->makeBranchUser($branch, 'seller');
        $this->actingAs($viewOnly)
            ->post($this->initialUrl($branch), ['variant_id' => $variantId, 'quantity' => '1.000'])
            ->assertForbidden();

        $writer = $this->makeWriteUser($branch);
        $this->actingAs($writer)
            ->post($this->initialUrl($other), ['variant_id' => $variantId, 'quantity' => '1.000'])
            ->assertForbidden();

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $branch = $this->makeBranch();

        $this->post($this->initialUrl($branch), ['variant_id' => 1, 'quantity' => '1.000'])
            ->assertRedirect(route('login'));
    }
}
