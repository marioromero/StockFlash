<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Autorización, alcance de sucursal, módulo y licencia de traspasos
 * (INVTRANSFER-001 · INV-06).
 */
class StockTransferAccessTest extends StockTransferTestCase
{
    public function test_lector_puede_listar_y_ver_pero_no_crear_ni_transicionar(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RBAC']);
        $destination = $this->makeBranch(['code' => 'DES-RBAC']);
        [, $variantId] = $this->makeProductVariant('TRF-RBAC');
        $this->createStockRow($origin->id, $variantId, '3.000');
        $transferId = $this->draft($origin, $destination, [['sku' => 'TRF-RBAC', 'quantity' => '1.000']]);

        $role = $this->makeRoleWithPermissions(['inventory.view']);
        $user = $this->makeBranchUser($origin, $role);

        $this->actingAs($user)
            ->get($this->transferUrl($origin, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_adjust', false)
                ->where('transfer.actions.request', false)
                ->where('transfer.actions.cancel', false));

        $this->actingAs($user)
            ->post($this->transferUrl($origin, $transferId, '/request'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $destination->id,
                'lines' => [['sku' => 'TRF-RBAC', 'quantity' => '1.000']],
            ])
            ->assertForbidden();

        $this->assertSame('draft', $this->transferRow($transferId)->status);
    }

    public function test_sucursal_destino_puede_recibir_pero_no_enviar_y_origen_no_puede_recibir(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-CROSS']);
        $destination = $this->makeBranch(['code' => 'DES-CROSS']);
        [, $variantId] = $this->makeProductVariant('TRF-CROSS');
        $this->createStockRow($origin->id, $variantId, '5.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-CROSS', 'quantity' => '2.000'],
        ]);
        $itemId = $this->itemIdsByVariant($transferId)[$variantId];

        $destinationUser = $this->makeBranchUser($destination, 'warehouse_keeper');

        $this->actingAs($destinationUser)
            ->get($this->transferUrl($destination, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_adjust', true)
                ->where('transfer.actions.receive', true)
                ->where('transfer.actions.dispatch', false));

        $this->actingAs($destinationUser)
            ->post($this->transferUrl($destination, $transferId, '/dispatch'))
            ->assertSessionHasErrors('transfer');

        $this->actingAs($destinationUser)
            ->post($this->transferUrl($destination, $transferId, '/receive'), [
                'lines' => [['item_id' => $itemId, 'received_qty' => '2.000']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->transferUrl($destination, $transferId));

        $this->assertSame('received', $this->transferRow($transferId)->status);

        $secondTransfer = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-CROSS', 'quantity' => '1.000'],
        ]);
        $secondItem = $this->itemIdsByVariant($secondTransfer)[$variantId];
        $originUser = $this->makeBranchUser($origin, 'warehouse_keeper');

        $this->actingAs($originUser)
            ->post($this->transferUrl($origin, $secondTransfer, '/receive'), [
                'lines' => [['item_id' => $secondItem, 'received_qty' => '1.000']],
            ])
            ->assertSessionHasErrors('transfer');

        $this->assertSame('in_transit', $this->transferRow($secondTransfer)->status);
    }

    public function test_sucursal_ajena_no_puede_listar_ni_ver_el_traspaso(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-SCOPE']);
        $destination = $this->makeBranch(['code' => 'DES-SCOPE']);
        $unrelated = $this->makeBranch(['code' => 'UNR-SCOPE']);
        $this->makeProductVariant('TRF-SCOPE');
        $transferId = $this->draft($origin, $destination, [['sku' => 'TRF-SCOPE', 'quantity' => '1.000']]);

        $this->actingAs($this->admin)
            ->get($this->transferUrl($unrelated, $transferId))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post($this->transferUrl($unrelated, $transferId, '/request'))
            ->assertSessionHasErrors('transfer');

        $this->actingAs($this->admin)
            ->get($this->transferUrl($unrelated))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('transfers.data', 0));

        $this->assertSame('draft', $this->transferRow($transferId)->status);
    }

    public function test_identificador_de_traspaso_no_positivo_o_no_numerico_es_403(): void
    {
        $branch = $this->makeBranch(['code' => 'ORI-BADID']);

        $this->actingAs($this->admin)
            ->get($this->transferUrl($branch, null, '/abc'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get($this->transferUrl($branch, 0))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get($this->transferUrl($branch, 999999))
            ->assertForbidden();
    }

    public function test_codigo_1e0_no_amplia_el_alcance_autorizado(): void
    {
        $byId = $this->makeBranch(['code' => 'SUC-UNO-TRF']);
        $coded = $this->makeBranch(['code' => '1E0']);

        $this->assertSame((int) $byId->id, (int) DB::table('branches')->min('id'));

        $this->actingAs($this->admin)
            ->get('/inventory/stock/1E0/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branch.id', $byId->id));

        $codedUser = $this->makeBranchUser($coded, 'warehouse_keeper');

        $this->actingAs($codedUser)
            ->get('/inventory/stock/1E0/transfers')
            ->assertForbidden();
    }

    public function test_modulo_deshabilitado_bloquea_lista_detalle_y_escrituras(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-MOD']);
        $destination = $this->makeBranch(['code' => 'DES-MOD']);
        $this->makeProductVariant('TRF-MOD');
        $transferId = $this->draft($origin, $destination, [['sku' => 'TRF-MOD', 'quantity' => '1.000']]);
        $this->disableInventoryModule();

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, $transferId))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $destination->id,
                'lines' => [['sku' => 'TRF-MOD', 'quantity' => '1.000']],
            ])
            ->assertForbidden();

        $this->assertSame('draft', $this->transferRow($transferId)->status);
    }

    public function test_licencia_solo_lectura_permite_ver_pero_bloquea_escrituras_con_422(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RO']);
        $destination = $this->makeBranch(['code' => 'DES-RO']);
        $this->makeProductVariant('TRF-RO');
        $transferId = $this->draft($origin, $destination, [['sku' => 'TRF-RO', 'quantity' => '1.000']]);
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('read_only', true)
                ->where('can_adjust', true));

        $this->actingAs($this->admin)
            ->get($this->transferUrl($origin, $transferId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('read_only', true)
                ->where('transfer.actions.request', false)
                ->where('transfer.actions.cancel', false));

        $this->actingAs($this->admin)
            ->post($this->transferUrl($origin, $transferId, '/request'))
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $destination->id,
                'lines' => [['sku' => 'TRF-RO', 'quantity' => '1.000']],
            ])
            ->assertSessionHasErrors('read_only');

        $this->assertSame('draft', $this->transferRow($transferId)->status);
        $this->assertSame(1, DB::table('stock_transfers')->count());
    }

    public function test_store_ignora_campos_forjados_y_usa_origen_actor_y_estado_del_servidor(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-FORGE']);
        $destination = $this->makeBranch(['code' => 'DES-FORGE']);
        $this->makeProductVariant('TRF-FORGE');

        $response = $this->actingAs($this->admin)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $destination->id,
                'from_branch_id' => $destination->id,
                'status' => 'received',
                'number' => 'FORGED-0001',
                'user_id' => 999999,
                'requested_at' => '2000-01-01 00:00:00',
                'unit_cost' => '1.0000',
                'lines' => [[
                    'sku' => 'TRF-FORGE',
                    'quantity' => '1.500',
                ]],
                'notes' => 'Nota de prueba',
            ]);

        $transferId = (int) DB::table('stock_transfers')->max('id');
        $response->assertRedirect($this->transferUrl($origin, $transferId));

        $transfer = $this->transferRow($transferId);
        $this->assertSame((int) $origin->id, (int) $transfer->from_branch_id);
        $this->assertSame((int) $destination->id, (int) $transfer->to_branch_id);
        $this->assertSame('draft', $transfer->status);
        $this->assertStringStartsWith('DRAFT-', (string) $transfer->number);
        $this->assertNull($transfer->requested_by_user_id);
        $this->assertNull($transfer->requested_at);

        $item = $this->itemRows($transferId)->first();
        $this->assertQuantity('1.500', $item->requested_qty);
        $this->assertNull($item->unit_cost_snapshot);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_store_devuelve_errores_es_cl_y_no_crea_borrador_invalido(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-ERR']);
        $destination = $this->makeBranch(['code' => 'DES-ERR']);
        $this->makeProductVariant('TRF-ERR');

        $this->actingAs($this->admin)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $origin->id,
                'lines' => [['sku' => 'TRF-ERR', 'quantity' => '1.000']],
            ])
            ->assertSessionHasErrors('to_branch_id');

        $this->actingAs($this->admin)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $destination->id,
                'lines' => [['sku' => 'TRF-ERR', 'quantity' => '0.000']],
            ])
            ->assertSessionHasErrors('lines.0.quantity');

        $this->actingAs($this->admin)
            ->post($this->transferUrl($origin), [
                'to_branch_id' => $destination->id,
                'lines' => [
                    ['sku' => 'TRF-ERR', 'quantity' => '1.000'],
                    ['sku' => 'trf-err', 'quantity' => '1.000'],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, DB::table('stock_transfers')->count());
    }

    public function test_receive_ignora_campos_forjados_y_exige_todas_las_lineas(): void
    {
        $origin = $this->makeBranch(['code' => 'ORI-RFORGE']);
        $destination = $this->makeBranch(['code' => 'DES-RFORGE']);
        [, $firstVariant] = $this->makeProductVariant('TRF-RFORGE-1');
        [, $secondVariant] = $this->makeProductVariant('TRF-RFORGE-2');
        $this->createStockRow($origin->id, $firstVariant, '5.000');
        $this->createStockRow($origin->id, $secondVariant, '5.000');

        $transferId = $this->dispatched($origin, $destination, [
            ['sku' => 'TRF-RFORGE-1', 'quantity' => '2.000'],
            ['sku' => 'TRF-RFORGE-2', 'quantity' => '2.000'],
        ]);
        $itemIds = $this->itemIdsByVariant($transferId);

        $this->actingAs($this->admin)
            ->post($this->transferUrl($destination, $transferId, '/receive'), [
                'status' => 'received',
                'received_by_user_id' => 999999,
                'branch_id' => $origin->id,
                'lines' => [
                    ['item_id' => $itemIds[$firstVariant], 'received_qty' => '1.000'],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame('in_transit', $this->transferRow($transferId)->status);

        $this->actingAs($this->admin)
            ->post($this->transferUrl($destination, $transferId, '/receive'), [
                'status' => 'received',
                'received_by_user_id' => 999999,
                'branch_id' => $origin->id,
                'lines' => [
                    ['item_id' => $itemIds[$firstVariant], 'received_qty' => '2.000'],
                    ['item_id' => $itemIds[$secondVariant], 'received_qty' => '1.000'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $transfer = $this->transferRow($transferId);
        $this->assertSame('partially_received', $transfer->status);
        $this->assertSame((int) $this->admin->id, (int) $transfer->received_by_user_id);
        $this->assertSame('2.000', $this->onHand((int) $destination->id, $firstVariant));
        $this->assertSame('1.000', $this->onHand((int) $destination->id, $secondVariant));
    }

    public function test_sucursal_inactiva_en_la_ruta_es_403(): void
    {
        $branch = $this->makeBranch(['code' => 'ORI-INACT', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get($this->transferUrl($branch))
            ->assertForbidden();
    }
}
