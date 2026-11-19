<?php

namespace Tests\Feature\Inventory;

use App\Support\AuditLogger;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

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

    public function test_sobre_el_umbral_exige_autorizador_y_pin(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-002');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '1234');
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
            'authorizer_id' => $authorizer->id,
        ])->assertSessionHasErrors('pin');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_autoautorizacion_permitida_con_permiso_autoridad_y_pin(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-002B');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $this->setThreshold('499.999');

        // El propio solicitante cumple la política: central_admin (autoridad
        // 100 + override.authorize) con PIN configurado.
        $selfAuthorizer = $this->makeUser([
            'name' => 'Admin con PIN',
            'global_role_id' => $this->adminRole->id,
            'pin_hash' => Hash::make('1234'),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
            'is_active' => true,
        ]);

        $props = $this->actingAs($selfAuthorizer)
            ->get("/inventory/stock/{$branch->id}")
            ->inertiaProps();

        $authorizerIds = array_map(fn (array $row): int => (int) $row['id'], $props['authorizers']);
        $this->assertContains((int) $selfAuthorizer->id, $authorizerIds);

        $this->actingAs($selfAuthorizer)->post($this->adjustmentUrl($branch), [
            'variant_id' => $variantId,
            'quantity' => '-1.000',
            'reason_code_id' => $reason->id,
            'request_token' => $this->token(),
            'authorizer_id' => $selfAuthorizer->id,
            'pin' => '1234',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertSame(1, $this->movementCount($branch->id, $variantId));

        $override = DB::table('authorization_overrides')->first();
        $this->assertSame((int) $selfAuthorizer->id, (int) $override->requested_by_user_id);
        $this->assertSame((int) $selfAuthorizer->id, (int) $override->authorized_by_user_id);
        $this->assertQuantity('0.000', $override->new_value);
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
        $this->assertSame(1, DB::table('audit_logs')
            ->where('event', 'authorization_override.pin_failed')
            ->where('branch_id', $branch->id)
            ->count());
        $this->assertSame(0, DB::table('audit_logs')
            ->where('event', 'inventory.movement_recorded')
            ->count());
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

    public function test_fallo_posterior_a_la_aprobacion_no_deja_ao_ni_movimiento(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-004C');
        $this->createStockRow($branch->id, $variantId, '2.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '4321');
        $this->setThreshold('499.999');

        // Falla SOLO la auditoría de aprobación, que se escribe después de
        // insertar el AO. La auditoría del movimiento sí se registra, de modo
        // que el fallo ocurre realmente después de la aprobación/escritura.
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function record(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                ?int $branchId = null,
                ?array $old = null,
                ?array $new = null,
                string $moduleCode = 'core',
                bool $isSecurityEvent = false,
                bool $isPersonalDataAccess = false,
            ): void {
                if ($event === 'authorization_override.approved') {
                    throw new RuntimeException('Auditoría de aprobación no disponible.');
                }

                parent::record(
                    $event,
                    $auditableType,
                    $auditableId,
                    $branchId,
                    $old,
                    $new,
                    $moduleCode,
                    $isSecurityEvent,
                    $isPersonalDataAccess,
                );
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
                'variant_id' => $variantId,
                'quantity' => '-1.000',
                'reason_code_id' => $reason->id,
                'request_token' => $this->token(),
                'authorizer_id' => $authorizer->id,
                'pin' => '4321',
            ]);

            $this->fail('Se esperaba el fallo de auditoría de aprobación.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría de aprobación no disponible.', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('audit_logs')
            ->where('event', 'inventory.movement_recorded')
            ->count());
        $this->assertSame(0, DB::table('audit_logs')
            ->where('event', 'authorization_override.approved')
            ->count());
        $this->assertQuantity(
            '2.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
    }

    public function test_umbral_reevaluado_bajo_la_transaccion_falla_sin_ao_ni_movimiento(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('PIN-004D');
        $this->createStockRow($branch->id, $variantId, '1.000');
        $reason = $this->reason('dano');
        $authorizer = $this->makeAuthorizer('supervisor', '4321');
        $this->setThreshold('499.999');

        // Interleave de concurrencia: cuando la autorización ya superó la
        // prevalidación y abre su transacción, otro actor sube el umbral. La
        // revalidación bajo lock debe fallar cerrado.
        $changed = false;

        Event::listen(TransactionBeginning::class, function () use (&$changed): void {
            if ($changed) {
                return;
            }

            $changed = true;
            $this->setThreshold('1000');
        });

        try {
            $this->actingAs($this->admin)->post($this->adjustmentUrl($branch), [
                'variant_id' => $variantId,
                'quantity' => '1.000',
                'reason_code_id' => $reason->id,
                'request_token' => $this->token(),
                'authorizer_id' => $authorizer->id,
                'pin' => '4321',
            ])->assertSessionHasErrors('adjustment');
        } finally {
            Event::forget(TransactionBeginning::class);
        }

        $this->assertTrue($changed);
        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertQuantity(
            '1.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
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
        // Incluye al admin actual (global, con override.authorize y autoridad).
        $this->assertCount(3, $props['authorizers']);

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
        $cashier = $this->makeBranchAuthorizer($branch, 'cashier');

        $props = $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}")
            ->inertiaProps();

        $authorizerIds = array_map(fn (array $row): int => (int) $row['id'], $props['authorizers']);

        // Solo el admin actual cumple la política; el cajero no tiene override.
        $this->assertSame([(int) $this->admin->id], $authorizerIds);
        $this->assertNotContains((int) $cashier->id, $authorizerIds);
    }

    public function test_otra_sucursal_no_expone_autorizadores_de_esa_sucursal(): void
    {
        $own = $this->makeBranch();
        $other = $this->makeBranch();
        $otherAuthorizer = $this->makeBranchAuthorizer($other, 'supervisor');

        $props = $this->actingAs($this->admin)
            ->get("/inventory/stock/{$own->id}")
            ->inertiaProps();

        $authorizerIds = array_map(fn (array $row): int => (int) $row['id'], $props['authorizers']);

        $this->assertSame([(int) $this->admin->id], $authorizerIds);
        $this->assertNotContains((int) $otherAuthorizer->id, $authorizerIds);
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
