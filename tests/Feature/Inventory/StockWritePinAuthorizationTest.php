<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Umbral de valor y autorización PIN para ajustes (INVWRITE-001 · INV-04).
 */
class StockWritePinAuthorizationTest extends InventoryWriteTestCase
{
    public function test_bajo_o_igual_al_umbral_no_exige_pin(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-001');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');

        foreach (['500.001', '500'] as $threshold) {
            $this->setThreshold($threshold);

            $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
                'variant_id' => $variantId,
                'quantity' => '1.000',
                'reason_code_id' => $reason->id,
                'request_token' => $this->token(),
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_sobre_el_umbral_exige_autorizador_distinto_y_pin(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-002');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $this->setThreshold('499.999');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('authorizer_id');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'authorizer_id' => $this->admin->id,
            'pin' => '1234',
        ])->assertSessionHasErrors('authorizer_id');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_pin_incorrecto_persiste_intento_sin_mover_stock(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-003');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '1234');
        $this->setThreshold('499.999');

        $response = $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'authorizer_id' => $authorizer->id,
            'pin' => '9999',
        ]);

        $response->assertSessionHasErrors('pin');

        $error = session('errors')->get('pin')[0];
        $this->assertStringNotContainsString('9999', $error);
        $this->assertStringNotContainsString('1234', $error);

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(1, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertQuantity(
            '1.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
    }

    public function test_autorizacion_valida_crea_un_ao_y_un_movimiento(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-004');
        $this->createStockRow($branch->id, $variantId, '2.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '4321');
        $this->setThreshold('499.999');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'note' => 'Corrección autorizada',
            'request_token' => $this->token(),
            'authorizer_id' => $authorizer->id,
            'pin' => '4321',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertSame(1, $this->movementCount($branch->id, $variantId));

        $override = DB::table('authorization_overrides')->first();
        $this->assertSame('stock_adjustment', $override->type);
        $this->assertSame((int) $this->admin->id, (int) $override->requested_by_user_id);
        $this->assertSame((int) $authorizer->id, (int) $override->authorized_by_user_id);
        $this->assertSame((int) $branch->id, (int) $override->branch_id);
        $this->assertSame('inventory_adjustment', $override->reference_type);
        $this->assertSame($variantId, (int) $override->reference_id);
        $this->assertQuantity('2.000', $override->old_value);
        $this->assertQuantity('1.000', $override->new_value);
        $this->assertNull($override->reason_code_id);
        $this->assertSame('Corrección autorizada', $override->reason);

        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'authorization_override.approved')
            ->where('branch_id', $branch->id)
            ->count());
        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'inventory.movement_recorded')
            ->where('branch_id', $branch->id)
            ->count());
    }

    public function test_reintento_con_mismo_token_no_crea_ao_ni_duplica_movimiento(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-004B');
        $this->createStockRow($branch->id, $variantId, '2.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '4321');
        $this->setThreshold('499.999');

        $token = $this->token();
        $payload = [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'note' => 'Corrección autorizada',
            'request_token' => $token,
            'authorizer_id' => $authorizer->id,
            'pin' => '4321',
        ];

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
        $this->assertQuantity('1.000', DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->value('on_hand'));

        // Reintento exacto: se rechaza antes de PIN y no deja otra AO ni movimiento.
        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), $payload)
            ->assertSessionHasErrors('request_token');

        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
        $this->assertQuantity('1.000', DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->value('on_hand'));

        // Un token nuevo sigue permitiendo un ajuste legítimo adicional.
        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-0.001',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertSame(2, $this->movementCount($branch->id, $variantId));
        $this->assertQuantity('0.999', DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->value('on_hand'));
    }

    public function test_autorizador_sin_permiso_override_es_rechazado(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-005');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('cashier', '1234');
        $this->setThreshold('499.999');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'authorizer_id' => $authorizer->id,
            'pin' => '1234',
        ])->assertSessionHasErrors('pin');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_umbral_desactivado_no_pide_pin_aunque_el_valor_sea_alto(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-006');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $this->setThreshold('0');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '999.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_costo_vigente_usa_last_cost_solo_con_metodo_last(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-007');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $this->updateVariantCosts($variantId, '500.0000', '5000.0000');
        $reason = $this->reason('dano');
        $this->setThreshold('600');

        // Con costo promedio (cost_price 500) el ajuste queda bajo el umbral.
        $this->setCostMethod('average');
        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasNoErrors();

        // Con método `last`, last_cost 5000 exige PIN.
        $this->setCostMethod('last');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
        ])->assertSessionHasErrors('authorizer_id');

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
    }

    public function test_licencia_solo_lectura_no_crea_ao_huérfana(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-008');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '1234');
        $this->setThreshold('499.999');
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'authorizer_id' => $authorizer->id,
            'pin' => '1234',
        ])->assertSessionHasErrors('read_only');

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
    }

    public function test_props_de_escritura_se_exponen_solo_con_permiso_de_ajuste(): void
    {
        $branch = $this->makeBranch();
        $this->createStockRow($branch->id, $this->makeProductVariant('PIN-009')[1], '1.000');
        $this->makeAuthorizer('supervisor');
        $this->makeAuthorizer('cashier');
        $this->makeAuthorizer('supervisor', overrides: ['is_active' => false]);
        $this->makeBranchAuthorizer($branch, 'supervisor');

        $adminResponse = $this->actingAs($this->admin)->get("/inventory/stock/{$branch->id}");
        $adminResponse->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('can_adjust', true)
            ->has('reasons')
            ->has('authorizers')
            ->has('adjustment_token'));

        $props = $adminResponse->inertiaProps();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16,64}$/', $props['adjustment_token']);
        $this->assertCount(2, $props['authorizers']);

        foreach ($props['authorizers'] as $authorizer) {
            $this->assertSame(['id', 'name'], array_keys($authorizer));
        }

        $this->assertSame(
            ['dano', 'vencido', 'perdida', 'error_digitacion'],
            array_map(
                fn (array $reason): string => (string) DB::table('reason_codes')->where('id', $reason['id'])->value('code'),
                $props['reasons'],
            ),
        );

        $viewer = $this->makeBranchUser($branch, 'seller');
        $viewerResponse = $this->actingAs($viewer)->get("/inventory/stock/{$branch->id}");
        $viewerResponse->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_adjust', false));

        $viewerProps = $viewerResponse->inertiaProps();
        $this->assertArrayNotHasKey('reasons', $viewerProps);
        $this->assertArrayNotHasKey('authorizers', $viewerProps);
        $this->assertArrayNotHasKey('adjustment_token', $viewerProps);
    }

    public function test_errores_y_auditoria_no_filtran_pin_costo_ni_umbral(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-010');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $this->updateVariantCosts($variantId, '123456.7890', null);
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '7777');
        $this->setThreshold('1');

        $response = $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'authorizer_id' => $authorizer->id,
            'pin' => '0000',
        ]);

        $response->assertSessionHasErrors('pin');

        $serializedErrors = json_encode(session('errors')->getBag('default')->all());
        $serializedAudit = json_encode(DB::table('audit_logs')->pluck('new_values')->all());

        $this->assertStringNotContainsString('0000', (string) $serializedErrors);
        $this->assertStringNotContainsString('7777', (string) $serializedErrors);
        $this->assertStringNotContainsString('123456.7890', (string) $serializedErrors);
        $this->assertStringNotContainsString('123456.7890', (string) $serializedAudit);
        $this->assertStringNotContainsString('7777', (string) $serializedAudit);
        $this->assertStringNotContainsString('0000', (string) $serializedAudit);
    }

    public function test_autorizador_de_sucursal_sin_rol_override_no_aparece_en_la_lista(): void
    {
        $branch = $this->makeBranch();
        $this->makeBranchAuthorizer($branch, 'cashier');

        $props = $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}")
            ->inertiaProps();

        $this->assertSame([], $props['authorizers']);
    }

    public function test_otra_sucursal_no_expone_autorizadores_de_esa_sucursal(): void
    {
        $own = $this->makeBranch();
        $other = $this->makeBranch();
        $this->makeBranchAuthorizer($other, 'supervisor');

        $props = $this->actingAs($this->admin)
            ->get("/inventory/stock/{$own->id}")
            ->inertiaProps();

        $this->assertSame([], $props['authorizers']);
    }

    public function test_sucursal_inactiva_no_permite_ajustar(): void
    {
        $branch = $this->makeBranch(['is_active' => false]);
        [, $variantId] = $this->makeProductVariant('PIN-011');

        $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '1.000',
            'reason_code_id' => $this->reason('dano')->id,
            'request_token' => $this->token(),
        ])->assertForbidden();
    }
}
