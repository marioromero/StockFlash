<?php

namespace Tests\Feature\Settings;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

class ReceiptSettingsTest extends ReceiptSettingsTestCase
{
    public function test_lectura_muestra_los_tres_valores_actuales_sin_exponer_otras_claves(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => 'Encabezado actual']);
        DB::table('system_configs')->where('config_key', 'receipt_footer')->update(['config_value' => 'Pie actual']);
        DB::table('system_configs')->where('config_key', 'receipt_paper')->update(['config_value' => 'A4']);

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Receipt')
                ->where('receipt_header', 'Encabezado actual')
                ->where('receipt_footer', 'Pie actual')
                ->where('receipt_paper', 'A4')
                ->where('read_only', false)
                ->missing('business_name')
                ->missing('fiscal_provider_config')
                ->missing('system_configs'));
    }

    public function test_lectura_aplica_defectos_si_falta_papel_o_hay_valores_nulos(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_paper')->delete();
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => null]);
        DB::table('system_configs')->where('config_key', 'receipt_footer')->update(['config_value' => null]);

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Receipt')
                ->where('receipt_header', '')
                ->where('receipt_footer', '')
                ->where('receipt_paper', '80mm')
                ->where('read_only', false));
    }

    public function test_lectura_aplica_papel_por_defecto_si_el_valor_guardado_es_invalido(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_paper')->update(['config_value' => 'carta']);

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Receipt')
                ->where('receipt_paper', '80mm'));
    }

    public function test_guardado_normaliza_textos_y_persiste_los_tres_valores(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/receipt', [
                'receipt_header' => '  Encabezado limpio  ',
                'receipt_footer' => '   ',
                'receipt_paper' => '58mm',
            ])
            ->assertRedirect(route('settings.receipt.edit'))
            ->assertSessionHas('success');

        $this->assertSame('Encabezado limpio', $this->configValue('receipt_header'));
        $this->assertNull($this->configValue('receipt_footer'));
        $this->assertSame('58mm', $this->configValue('receipt_paper'));

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('receipt_header', 'Encabezado limpio')
                ->where('receipt_footer', '')
                ->where('receipt_paper', '58mm'));
    }

    public function test_guardado_crea_solo_la_fila_faltante_con_metadatos_por_defecto(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_footer')->delete();

        $this->actingAs($this->admin)
            ->put('/settings/receipt', $this->validPayload([
                'receipt_header' => 'Solo encabezado',
                'receipt_footer' => 'Pie restaurado',
                'receipt_paper' => 'A4',
            ]))
            ->assertRedirect(route('settings.receipt.edit'));

        $row = DB::table('system_configs')->where('config_key', 'receipt_footer')->first();

        $this->assertNotNull($row);
        $this->assertSame('Pie restaurado', $row->config_value);
        $this->assertSame('string', $row->value_type);
        $this->assertSame(0, (int) $row->is_secret);
        $this->assertNull($row->module_code);
        $this->assertSame($this->admin->id, (int) $row->updated_by_user_id);
    }

    public function test_guardado_ignora_claves_extra_y_no_modifica_filas_ajenas(): void
    {
        $ignoredKeys = ['receipt_header', 'receipt_footer', 'receipt_paper'];
        $before = DB::table('system_configs')
            ->whereNotIn('config_key', $ignoredKeys)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->put('/settings/receipt', [
                'receipt_header' => 'Encabezado permitido',
                'receipt_footer' => 'Pie permitido',
                'receipt_paper' => '80mm',
                'business_name' => 'No debe persistirse',
                'fiscal_provider_config' => '{"secreto":"no"}',
                'is_secret' => 1,
                'value_type' => 'json',
                'module_code' => 'hack',
                'evil_key' => 'no',
            ])
            ->assertRedirect(route('settings.receipt.edit'));

        $after = DB::table('system_configs')
            ->whereNotIn('config_key', $ignoredKeys)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertDatabaseMissing('system_configs', ['config_key' => 'evil_key']);
        $this->assertNotSame('No debe persistirse', $this->configValue('business_name'));
        $this->assertSame(0, (int) DB::table('system_configs')->where('config_key', 'receipt_header')->value('is_secret'));
        $this->assertSame('string', DB::table('system_configs')->where('config_key', 'receipt_header')->value('value_type'));
    }

    public function test_papel_invalido_no_persiste_y_devuelve_error(): void
    {
        $before = [
            'receipt_header' => $this->configValue('receipt_header'),
            'receipt_footer' => $this->configValue('receipt_footer'),
            'receipt_paper' => $this->configValue('receipt_paper'),
        ];

        foreach (['58', 'carta', 'A3', '80MM'] as $invalid) {
            $this->actingAs($this->admin)
                ->put('/settings/receipt', $this->validPayload(['receipt_paper' => $invalid]))
                ->assertSessionHasErrors('receipt_paper');
        }

        $this->assertSame($before, [
            'receipt_header' => $this->configValue('receipt_header'),
            'receipt_footer' => $this->configValue('receipt_footer'),
            'receipt_paper' => $this->configValue('receipt_paper'),
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_encabezado_sobre_el_limite_no_persiste_y_devuelve_error(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/receipt', $this->validPayload([
                'receipt_header' => str_repeat('x', 1001),
            ]))
            ->assertSessionHasErrors('receipt_header');

        $this->assertNotSame(str_repeat('x', 1001), $this->configValue('receipt_header'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_auditoria_registra_actor_fecha_y_solo_claves_cambiadas_sin_pii(): void
    {
        $headerPii = 'PII-FICTICIA-ENCABEZADO-987654';
        $footerPii = 'PII-FICTICIA-PIE-123987';

        $this->actingAs($this->admin)
            ->put('/settings/receipt', [
                'receipt_header' => $headerPii,
                'receipt_footer' => $footerPii,
                'receipt_paper' => 'A4',
            ])
            ->assertRedirect(route('settings.receipt.edit'));

        $rows = DB::table('audit_logs')->where('event', 'core.receipt_settings.updated')->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame($this->admin->id, (int) $row->user_id);
        $this->assertNotNull($row->created_at);
        $this->assertSame('system_configs', $row->auditable_type);

        $newValues = json_decode((string) $row->new_values, true);
        $this->assertSame(
            ['receipt_header', 'receipt_footer', 'receipt_paper'],
            $newValues['changed_keys']
        );

        $serializedOld = (string) $row->old_values;
        $serializedNew = (string) $row->new_values;

        $this->assertStringNotContainsString($headerPii, $serializedOld);
        $this->assertStringNotContainsString($headerPii, $serializedNew);
        $this->assertStringNotContainsString($footerPii, $serializedOld);
        $this->assertStringNotContainsString($footerPii, $serializedNew);
    }

    public function test_auditoria_lista_solo_claves_realmente_modificadas(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => 'Sin cambios']);
        DB::table('system_configs')->where('config_key', 'receipt_footer')->update(['config_value' => 'Pie anterior']);
        DB::table('system_configs')->where('config_key', 'receipt_paper')->update(['config_value' => '80mm']);

        $this->actingAs($this->admin)
            ->put('/settings/receipt', [
                'receipt_header' => 'Sin cambios',
                'receipt_footer' => 'Pie nuevo',
                'receipt_paper' => 'A4',
            ])
            ->assertRedirect(route('settings.receipt.edit'));

        $row = DB::table('audit_logs')->where('event', 'core.receipt_settings.updated')->firstOrFail();
        $newValues = json_decode((string) $row->new_values, true);

        $this->assertSame(['receipt_footer', 'receipt_paper'], $newValues['changed_keys']);
    }

    public function test_sin_cambios_no_registra_auditoria_ni_actualiza_filas(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => 'Igual']);
        DB::table('system_configs')->where('config_key', 'receipt_footer')->update(['config_value' => null]);
        DB::table('system_configs')->where('config_key', 'receipt_paper')->update(['config_value' => '80mm']);

        $before = DB::table('system_configs')
            ->whereIn('config_key', ['receipt_header', 'receipt_footer', 'receipt_paper'])
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->put('/settings/receipt', [
                'receipt_header' => 'Igual',
                'receipt_footer' => '',
                'receipt_paper' => '80mm',
            ])
            ->assertRedirect(route('settings.receipt.edit'));

        $after = DB::table('system_configs')
            ->whereIn('config_key', ['receipt_header', 'receipt_footer', 'receipt_paper'])
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_los_tres_valores(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => 'Encabezado original']);
        DB::table('system_configs')->where('config_key', 'receipt_footer')->update(['config_value' => 'Pie original']);
        DB::table('system_configs')->where('config_key', 'receipt_paper')->update(['config_value' => '80mm']);

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
                throw new RuntimeException('Fallo de auditoría simulado');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->put('/settings/receipt', [
                    'receipt_header' => 'No debe quedar',
                    'receipt_footer' => 'Tampoco',
                    'receipt_paper' => 'A4',
                ]);
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertSame('Encabezado original', $this->configValue('receipt_header'));
        $this->assertSame('Pie original', $this->configValue('receipt_footer'));
        $this->assertSame('80mm', $this->configValue('receipt_paper'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_suspendida_sin_gracia_bloquea_escritura_y_marca_solo_lectura(): void
    {
        $this->suspendLicense(null);

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->put('/settings/receipt', $this->validPayload(['receipt_header' => 'No debe quedar']))
            ->assertSessionHasErrors('read_only');

        $this->assertNull($this->configValue('receipt_header'));
        $this->assertSame('80mm', $this->configValue('receipt_paper'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_suspendida_con_gracia_futura_permite_escritura(): void
    {
        $this->suspendLicense(now()->addDays(3)->toDateString());

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', false));

        $this->actingAs($this->admin)
            ->put('/settings/receipt', $this->validPayload(['receipt_header' => 'Permitido en gracia']))
            ->assertRedirect(route('settings.receipt.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Permitido en gracia', $this->configValue('receipt_header'));
    }

    public function test_licencia_suspendida_con_gracia_vencida_bloquea_escritura(): void
    {
        $this->suspendLicense(now()->subDays(2)->toDateString());

        $this->actingAs($this->admin)
            ->put('/settings/receipt', $this->validPayload(['receipt_header' => 'No debe quedar']))
            ->assertSessionHasErrors('read_only');

        $this->assertNull($this->configValue('receipt_header'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_sin_fila_de_licencia_permite_escritura(): void
    {
        $this->deleteLicense();

        $this->actingAs($this->admin)
            ->get('/settings/receipt')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', false));

        $this->actingAs($this->admin)
            ->put('/settings/receipt', $this->validPayload(['receipt_header' => 'Permitido sin licencia']))
            ->assertRedirect(route('settings.receipt.edit'));

        $this->assertSame('Permitido sin licencia', $this->configValue('receipt_header'));
    }

    public function test_patch_no_esta_soportado_y_no_modifica_los_tres_valores(): void
    {
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => 'Encabezado intacto']);
        DB::table('system_configs')->where('config_key', 'receipt_footer')->update(['config_value' => 'Pie intacto']);
        DB::table('system_configs')->where('config_key', 'receipt_paper')->update(['config_value' => 'A4']);

        $before = [
            'receipt_header' => $this->configValue('receipt_header'),
            'receipt_footer' => $this->configValue('receipt_footer'),
            'receipt_paper' => $this->configValue('receipt_paper'),
        ];

        $this->actingAs($this->admin)
            ->patch('/settings/receipt', [
                'receipt_paper' => '58mm',
            ])
            ->assertStatus(405);

        $this->assertSame($before, [
            'receipt_header' => $this->configValue('receipt_header'),
            'receipt_footer' => $this->configValue('receipt_footer'),
            'receipt_paper' => $this->configValue('receipt_paper'),
        ]);
        $this->assertSame('Encabezado intacto', $this->configValue('receipt_header'));
        $this->assertSame('Pie intacto', $this->configValue('receipt_footer'));
        $this->assertSame('A4', $this->configValue('receipt_paper'));
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
