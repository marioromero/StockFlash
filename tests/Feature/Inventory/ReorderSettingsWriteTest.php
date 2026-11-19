<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Support\AuditLogger;
use App\Support\Inventory\ReorderSettingsService;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Escritura de configuración de reposición por sucursal y variante
 * (INVREORDER-001 · INV-07). SQLite :memory: exclusivamente.
 */
class ReorderSettingsWriteTest extends InventoryTestCase
{
    private function reorderUrl(Branch $branch, int $variantId): string
    {
        return "/inventory/stock/{$branch->id}/reorder/{$variantId}";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'reorder_point' => '5.000',
            'reorder_qty' => '10.000',
            'max_stock' => '100.000',
        ], $overrides);
    }

    private function settingsRow(int $branchId, int $variantId): ?object
    {
        return DB::table('branch_variant_settings')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->first();
    }

    public function test_crea_y_actualiza_sin_tocar_stock_ni_movimientos(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-CREATE');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), $this->payload())
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertNotNull($row);
        $this->assertQuantity('5.000', $row->reorder_point);
        $this->assertQuantity('10.000', $row->reorder_qty);
        $this->assertQuantity('100.000', $row->max_stock);
        $this->assertQuantity('0.000', $row->safety_stock);
        $this->assertNull($row->location_label);

        $this->assertSame(0, DB::table('current_stock')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '6.500',
                'reorder_qty' => '',
                'max_stock' => null,
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertQuantity('6.500', $row->reorder_point);
        $this->assertNull($row->reorder_qty);
        $this->assertNull($row->max_stock);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_dos_sucursales_comparten_variante_de_forma_independiente(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-BRANCHES');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branchA, $variantId), $this->payload([
                'reorder_point' => '3.000',
                'max_stock' => '30.000',
            ]))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branchB, $variantId), $this->payload([
                'reorder_point' => '7.000',
                'max_stock' => '70.000',
            ]))
            ->assertRedirect();

        $rowA = $this->settingsRow($branchA->id, $variantId);
        $rowB = $this->settingsRow($branchB->id, $variantId);
        $this->assertQuantity('3.000', $rowA->reorder_point);
        $this->assertQuantity('30.000', $rowA->max_stock);
        $this->assertQuantity('7.000', $rowB->reorder_point);
        $this->assertQuantity('70.000', $rowB->max_stock);
        $this->assertCount(2, DB::table('branch_variant_settings')->get());
    }

    public function test_fila_ausente_de_current_stock_es_valida_y_nullable_ida_y_vuelta(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-NULLABLE');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '2.000',
                'reorder_qty' => null,
                'max_stock' => null,
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertQuantity('2.000', $row->reorder_point);
        $this->assertNull($row->reorder_qty);
        $this->assertNull($row->max_stock);
        $this->assertSame(0, DB::table('current_stock')->count());

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '2.000',
                'reorder_qty' => '2.500',
                'max_stock' => '9.500',
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertQuantity('2.500', $row->reorder_qty);
        $this->assertQuantity('9.500', $row->max_stock);

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '2.000',
                'reorder_qty' => '',
                'max_stock' => '',
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertNull($row->reorder_qty);
        $this->assertNull($row->max_stock);
    }

    public function test_preserva_safety_stock_y_location_label_existentes(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-PRESERVE');

        $this->createReorderSettings(
            $branch->id,
            $variantId,
            '1.000',
            null,
            null,
            '3.500',
            'A-01',
        );

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '8.000',
                'reorder_qty' => '4.000',
                'max_stock' => '20.000',
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertQuantity('8.000', $row->reorder_point);
        $this->assertQuantity('3.500', $row->safety_stock);
        $this->assertSame('A-01', $row->location_label);

        [, $otherVariant] = $this->makeProductVariant('REORDER-PRESERVE-NEW');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $otherVariant), [
                'reorder_point' => '1.000',
                'reorder_qty' => null,
                'max_stock' => null,
            ])
            ->assertRedirect();

        $created = $this->settingsRow($branch->id, $otherVariant);
        $this->assertQuantity('0.000', $created->safety_stock);
        $this->assertNull($created->location_label);
    }

    public function test_precision_y_rango_estricto(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-PRECISION');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '999999999.999',
                'reorder_qty' => null,
                'max_stock' => null,
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertQuantity('999999999.999', $row->reorder_point);

        $invalid = ['-1', '1.2345', '1,5', '1E3', 'abc', '1000000000', ''];

        foreach ($invalid as $value) {
            $this->actingAs($this->admin)
                ->putJson($this->reorderUrl($branch, $variantId), [
                    'reorder_point' => $value,
                    'reorder_qty' => null,
                    'max_stock' => null,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['reorder_point']);
        }

        $this->actingAs($this->admin)
            ->putJson($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '1.000',
                'reorder_qty' => '1.2345',
                'max_stock' => '5.000',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reorder_qty']);

        $this->actingAs($this->admin)
            ->putJson($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '1.000',
                'reorder_qty' => '1.000',
                'max_stock' => '-5',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_stock']);
    }

    public function test_maximo_menor_que_punto_es_permitido_sin_regla_inventada(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-MAX-BELOW');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '10.000',
                'reorder_qty' => '2.000',
                'max_stock' => '5.000',
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertQuantity('10.000', $row->reorder_point);
        $this->assertQuantity('5.000', $row->max_stock);
    }

    public function test_rechaza_variante_o_producto_inactivo_sin_control_o_eliminado(): void
    {
        $branch = $this->makeBranch();
        $cases = [];

        [$productId, $variantId] = $this->makeProductVariant('REORDER-INACTIVE-V');
        DB::table('product_variants')->where('id', $variantId)->update(['is_active' => 0]);
        $cases[] = $variantId;

        [$productId, $variantId] = $this->makeProductVariant('REORDER-INACTIVE-P');
        DB::table('products')->where('id', $productId)->update(['is_active' => 0]);
        $cases[] = $variantId;

        [$productId, $variantId] = $this->makeProductVariant('REORDER-NOTRACK');
        DB::table('products')->where('id', $productId)->update(['tracks_stock' => 0]);
        $cases[] = $variantId;

        [$productId, $variantId] = $this->makeProductVariant('REORDER-COMBO');
        DB::table('products')->where('id', $productId)->update(['type' => 'combo']);
        $cases[] = $variantId;

        [$productId, $variantId] = $this->makeProductVariant('REORDER-DELETED-V');
        DB::table('product_variants')->where('id', $variantId)->update(['deleted_at' => now()]);
        $cases[] = $variantId;

        [$productId, $variantId] = $this->makeProductVariant('REORDER-DELETED-P');
        DB::table('products')->where('id', $productId)->update(['deleted_at' => now()]);
        $cases[] = $variantId;

        foreach ($cases as $variantId) {
            $this->actingAs($this->admin)
                ->putJson($this->reorderUrl($branch, (int) $variantId), $this->payload())
                ->assertStatus(422)
                ->assertJsonValidationErrors(['variant_id']);
        }

        $this->assertSame(0, DB::table('branch_variant_settings')->count());
    }

    public function test_actualizacion_no_altera_current_stock_ni_movimientos(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-NO-SIDE-EFFECTS');

        $this->recordMovement($branch->id, $variantId, 'initial', '10.000');
        DB::table('current_stock')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->update(['reserved' => '2.500', 'updated_at' => now()]);

        $stockBefore = DB::table('current_stock')->where('branch_id', $branch->id)->first();
        $movementsBefore = DB::table('stock_movements')->count();

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), $this->payload())
            ->assertRedirect();

        $stockAfter = DB::table('current_stock')->where('branch_id', $branch->id)->first();

        $this->assertQuantity('10.000', $stockAfter->on_hand);
        $this->assertQuantity('2.500', $stockAfter->reserved);
        $this->assertQuantity('7.500', $stockAfter->available);
        $this->assertSame((string) $stockBefore->updated_at, (string) $stockAfter->updated_at);
        $this->assertSame($movementsBefore, DB::table('stock_movements')->count());
    }

    public function test_auditoria_exacta_sin_claves_sensibles(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-AUDIT');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '5.000',
                'reorder_qty' => null,
                'max_stock' => '50.000',
            ])
            ->assertRedirect();

        $create = DB::table('audit_logs')
            ->where('event', 'inventory.reorder_settings_updated')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($create);
        $this->assertSame('branch_variant_settings', $create->auditable_type);
        $this->assertSame($variantId, (int) $create->auditable_id);
        $this->assertSame($branch->id, (int) $create->branch_id);
        $this->assertSame('inventory', $create->module_code);
        $this->assertSame($this->admin->id, (int) $create->user_id);
        $this->assertNull($create->old_values);

        $createdNew = $this->decodeAudit($create->new_values);
        $this->assertSame(['max_stock', 'reorder_point', 'reorder_qty'], $this->sortedKeys($createdNew));
        $this->assertSame('5.000', $createdNew['reorder_point']);
        $this->assertNull($createdNew['reorder_qty']);
        $this->assertSame('50.000', $createdNew['max_stock']);

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '6.000',
                'reorder_qty' => '12.000',
                'max_stock' => null,
            ])
            ->assertRedirect();

        $update = DB::table('audit_logs')
            ->where('event', 'inventory.reorder_settings_updated')
            ->orderByDesc('id')
            ->first();

        $old = $this->decodeAudit($update->old_values);
        $new = $this->decodeAudit($update->new_values);

        $this->assertSame(['max_stock', 'reorder_point', 'reorder_qty'], $this->sortedKeys($old));
        $this->assertSame(['max_stock', 'reorder_point', 'reorder_qty'], $this->sortedKeys($new));
        $this->assertSame('5.000', $old['reorder_point']);
        $this->assertNull($old['reorder_qty']);
        $this->assertSame('50.000', $old['max_stock']);
        $this->assertSame('6.000', $new['reorder_point']);
        $this->assertSame('12.000', $new['reorder_qty']);
        $this->assertNull($new['max_stock']);

        foreach (['unit_cost', 'cost', 'price', 'margin', 'safety_stock', 'location_label', 'user_id', 'on_hand'] as $key) {
            $this->assertArrayNotHasKey($key, $new);
            $this->assertArrayNotHasKey($key, $old);
        }
    }

    public function test_rollback_si_falla_la_auditoria_al_crear(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-ROLLBACK-CREATE');

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit failure'));
        });

        $this->actingAs($this->admin);
        $this->expectException(RuntimeException::class);

        try {
            app(ReorderSettingsService::class)->update(
                $branch->id,
                $variantId,
                '5.000',
                null,
                null,
                $this->admin->id,
            );
        } finally {
            $this->assertSame(0, DB::table('branch_variant_settings')->count());
        }
    }

    public function test_rollback_si_falla_la_auditoria_al_actualizar(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-ROLLBACK-UPDATE');

        $this->createReorderSettings($branch->id, $variantId, '2.000');

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit failure'));
        });

        $this->actingAs($this->admin);
        $this->expectException(RuntimeException::class);

        try {
            app(ReorderSettingsService::class)->update(
                $branch->id,
                $variantId,
                '9.000',
                '4.000',
                '20.000',
                $this->admin->id,
            );
        } finally {
            $row = $this->settingsRow($branch->id, $variantId);
            $this->assertQuantity('2.000', $row->reorder_point);
            $this->assertNull($row->reorder_qty);
            $this->assertNull($row->max_stock);
        }
    }

    public function test_licencia_solo_lectura_bloquea_escritura_sin_fila_parcial(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-READONLY');
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->putJson($this->reorderUrl($branch, $variantId), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['read_only']);

        $this->assertSame(0, DB::table('branch_variant_settings')->count());
    }

    public function test_modulo_deshabilitado_bloquea_lectura_y_escritura(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-MODULE-OFF');
        $this->disableInventoryModule();

        $this->actingAs($this->admin)
            ->get("/inventory/stock/{$branch->id}/reorder")
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->putJson($this->reorderUrl($branch, $variantId), $this->payload())
            ->assertForbidden();
    }

    public function test_permisos_de_escritura_y_alcance_por_sucursal(): void
    {
        $own = $this->makeBranch();
        $other = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-PERMS');

        $viewRole = $this->makeRoleWithPermissions(['inventory.view']);
        $viewUser = $this->makeBranchUser($own, $viewRole);

        $this->actingAs($viewUser)
            ->putJson($this->reorderUrl($own, $variantId), $this->payload())
            ->assertForbidden();

        $adjustRole = $this->makeRoleWithPermissions(['inventory.view', 'inventory.adjust']);
        $adjustUser = $this->makeBranchUser($own, $adjustRole);

        $this->actingAs($adjustUser)
            ->put($this->reorderUrl($own, $variantId), $this->payload())
            ->assertRedirect();

        $this->assertNotNull($this->settingsRow($own->id, $variantId));

        $this->actingAs($adjustUser)
            ->putJson($this->reorderUrl($other, $variantId), $this->payload())
            ->assertForbidden();

        $this->assertNull($this->settingsRow($other->id, $variantId));
    }

    public function test_codigo_1e0_resuelve_el_mismo_alcance_y_no_escribe_otra_sucursal(): void
    {
        $own = $this->makeBranch();
        $this->assertSame(1, (int) $own->id, 'La primera sucursal del test debe tener ID 1.');

        $other = $this->makeBranch(['code' => '1E0']);
        [, $variantId] = $this->makeProductVariant('REORDER-1E0');

        $role = $this->makeRoleWithPermissions(['inventory.view', 'inventory.adjust']);
        $user = $this->makeBranchUser($own, $role);

        $this->actingAs($user)
            ->put('/inventory/stock/1E0/reorder/'.$variantId, $this->payload())
            ->assertRedirect();

        $this->assertNotNull($this->settingsRow($own->id, $variantId));
        $this->assertNull($this->settingsRow($other->id, $variantId));

        $response = $this->actingAs($user)->get('/inventory/stock/1E0/reorder');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('branch.id', 1)
            ->where('branch.code', $own->code)
        );

        $this->assertStringNotContainsString($other->name, (string) $response->getContent());
    }

    public function test_campos_extra_del_cliente_se_ignoran(): void
    {
        $branch = $this->makeBranch();
        $other = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('REORDER-EXTRA');
        [, $otherVariant] = $this->makeProductVariant('REORDER-EXTRA-OTHER');

        $this->actingAs($this->admin)
            ->put($this->reorderUrl($branch, $variantId), [
                'reorder_point' => '3.000',
                'reorder_qty' => '4.000',
                'max_stock' => '5.000',
                'branch_id' => $other->id,
                'variant_id' => $otherVariant,
                'user_id' => $this->admin->id + 999,
                'safety_stock' => '99.000',
                'location_label' => 'Z-99',
                'on_hand' => '999.000',
                'unit_cost' => '1.0000',
                'type' => 'initial',
            ])
            ->assertRedirect();

        $row = $this->settingsRow($branch->id, $variantId);
        $this->assertNotNull($row);
        $this->assertQuantity('3.000', $row->reorder_point);
        $this->assertQuantity('0.000', $row->safety_stock);
        $this->assertNull($row->location_label);
        $this->assertNull($this->settingsRow($other->id, $variantId));
        $this->assertNull($this->settingsRow($branch->id, $otherVariant));

        $audit = DB::table('audit_logs')
            ->where('event', 'inventory.reorder_settings_updated')
            ->orderByDesc('id')
            ->first();

        $new = $this->decodeAudit($audit->new_values);
        $this->assertSame(['max_stock', 'reorder_point', 'reorder_qty'], $this->sortedKeys($new));
        $this->assertArrayNotHasKey('safety_stock', $new);
        $this->assertArrayNotHasKey('location_label', $new);
        $this->assertArrayNotHasKey('user_id', $new);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAudit(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
