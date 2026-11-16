<?php

namespace Tests\Feature\Settings;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

class BusinessSettingsTest extends BusinessSettingsTestCase
{
    public function test_lectura_muestra_solo_las_nueve_claves_y_read_only(): void
    {
        $values = [
            'business_name' => 'Comercio Actual',
            'fiscal_id' => '11.111.111-1',
            'fiscal_name' => 'Razón Actual',
            'commercial_activity' => 'Giro actual',
            'fiscal_address' => 'Dirección actual 1',
            'fiscal_commune' => 'Comuna actual',
            'responsible_name' => 'Responsable Actual',
            'responsible_email' => 'actual@prueba.cl',
            'responsible_phone' => '+56 9 0000 0000',
        ];

        foreach ($values as $key => $value) {
            DB::table('system_configs')->where('config_key', $key)->update(['config_value' => $value]);
        }

        $this->actingAs($this->admin)
            ->get('/settings/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Business')
                ->where('business_name', 'Comercio Actual')
                ->where('fiscal_id', '11.111.111-1')
                ->where('fiscal_name', 'Razón Actual')
                ->where('commercial_activity', 'Giro actual')
                ->where('fiscal_address', 'Dirección actual 1')
                ->where('fiscal_commune', 'Comuna actual')
                ->where('responsible_name', 'Responsable Actual')
                ->where('responsible_email', 'actual@prueba.cl')
                ->where('responsible_phone', '+56 9 0000 0000')
                ->where('read_only', false)
                ->missing('country')
                ->missing('currency_code')
                ->missing('fiscal_provider_config')
                ->missing('is_setup_completed')
                ->missing('system_configs')
                ->missing('evil_key'));
    }

    public function test_lectura_registra_auditoria_de_pii_sin_copiar_valores(): void
    {
        $piiName = 'PII-FICTICIA-RESPONSABLE-ABC123';
        $piiEmail = 'pii-ficticia-abc123@example.test';
        $piiPhone = 'PII-FICTICIA-TELEFONO-987654';

        DB::table('system_configs')->where('config_key', 'responsible_name')->update(['config_value' => $piiName]);
        DB::table('system_configs')->where('config_key', 'responsible_email')->update(['config_value' => $piiEmail]);
        DB::table('system_configs')->where('config_key', 'responsible_phone')->update(['config_value' => $piiPhone]);

        $this->actingAs($this->admin)
            ->get('/settings/business')
            ->assertOk();

        $rows = DB::table('audit_logs')->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame('core.business_settings.pii_viewed', $row->event);
        $this->assertSame('system_configs', $row->auditable_type);
        $this->assertSame('core', $row->module_code);
        $this->assertSame(1, (int) $row->is_personal_data_access);
        $this->assertNull($row->old_values);
        $this->assertNull($row->new_values);

        $serialized = (string) $row->old_values.(string) $row->new_values;

        foreach ([$piiName, $piiEmail, $piiPhone] as $pii) {
            $this->assertStringNotContainsString($pii, $serialized);
        }
    }

    public function test_lectura_falla_cerrada_si_la_auditoria_de_pii_falla(): void
    {
        $piiName = 'PII-FICTICIA-FAIL-CLOSED-555111';
        $piiEmail = 'pii-fail-closed-555111@example.test';

        DB::table('system_configs')->where('config_key', 'responsible_name')->update(['config_value' => $piiName]);
        DB::table('system_configs')->where('config_key', 'responsible_email')->update(['config_value' => $piiEmail]);

        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function recordRead(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                string $moduleCode = 'users',
            ): void {
                throw new RuntimeException('Fallo de auditoría de lectura simulado');
            }
        });

        $response = $this->actingAs($this->admin)->get('/settings/business');

        // La excepción se traduce en una respuesta de error; lo importante es
        // que no se entregan los datos del responsable.
        $this->assertGreaterThanOrEqual(500, $response->getStatusCode());
        $response->assertDontSee($piiName);
        $response->assertDontSee($piiEmail);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guardado_persiste_las_nueve_claves_normaliza_vacio_y_crea_faltantes_sin_tocar_metadatos(): void
    {
        DB::table('system_configs')->where('config_key', 'fiscal_name')->update([
            'description' => 'Descripción heredada',
            'value_type' => 'text',
            'is_secret' => 1,
            'module_code' => 'legacy',
        ]);

        DB::table('system_configs')->where('config_key', 'responsible_phone')->delete();

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload([
                'fiscal_commune' => '   ',
                'responsible_phone' => '  +56 9 5555 0000  ',
            ]))
            ->assertRedirect(route('settings.business.edit'))
            ->assertSessionHas('success');

        $payload = $this->validPayload();

        foreach ($payload as $key => $value) {
            if ($key === 'fiscal_commune') {
                continue;
            }

            if ($key === 'responsible_phone') {
                continue;
            }

            $this->assertSame($value, $this->configValue($key));
        }

        $this->assertNull($this->configValue('fiscal_commune'));
        $this->assertSame('+56 9 5555 0000', $this->configValue('responsible_phone'));

        $existing = DB::table('system_configs')->where('config_key', 'fiscal_name')->firstOrFail();
        $this->assertSame('Descripción heredada', $existing->description);
        $this->assertSame('text', $existing->value_type);
        $this->assertSame(1, (int) $existing->is_secret);
        $this->assertSame('legacy', $existing->module_code);
        $this->assertSame($this->admin->id, (int) $existing->updated_by_user_id);

        $inserted = DB::table('system_configs')->where('config_key', 'responsible_phone')->firstOrFail();
        $this->assertSame('+56 9 5555 0000', $inserted->config_value);
        $this->assertSame('string', $inserted->value_type);
        $this->assertSame(0, (int) $inserted->is_secret);
        $this->assertNull($inserted->module_code);
        $this->assertSame($this->admin->id, (int) $inserted->updated_by_user_id);
    }

    public function test_guardado_ignora_claves_extra_y_metadatos_inyectados_sin_modificar_filas_ajenas(): void
    {
        $owned = [
            'business_name',
            'fiscal_id',
            'fiscal_name',
            'commercial_activity',
            'fiscal_address',
            'fiscal_commune',
            'responsible_name',
            'responsible_email',
            'responsible_phone',
        ];

        $countBefore = DB::table('system_configs')->count();

        $before = DB::table('system_configs')
            ->whereNotIn('config_key', $owned)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload([
                'country' => 'AR',
                'fiscal_provider_config' => '{"secreto":"no"}',
                'is_secret' => 1,
                'value_type' => 'json',
                'module_code' => 'hack',
                'evil_key' => 'no',
            ]))
            ->assertRedirect(route('settings.business.edit'));

        $after = DB::table('system_configs')
            ->whereNotIn('config_key', $owned)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertSame($countBefore, DB::table('system_configs')->count());
        $this->assertDatabaseMissing('system_configs', ['config_key' => 'evil_key']);
        $this->assertSame('CL', $this->configValue('country'));
        $this->assertNull($this->configValue('fiscal_provider_config'));

        $provider = DB::table('system_configs')->where('config_key', 'fiscal_provider_config')->firstOrFail();
        $this->assertSame(1, (int) $provider->is_secret);
        $this->assertSame('json', $provider->value_type);

        $business = DB::table('system_configs')->where('config_key', 'business_name')->firstOrFail();
        $this->assertSame('Comercio de Prueba SpA', $business->config_value);
        $this->assertSame('string', $business->value_type);
        $this->assertSame(0, (int) $business->is_secret);
        $this->assertNull($business->module_code);
    }

    public function test_validaciones_rechazan_correo_invalido_y_textos_sobre_el_limite_sin_persistir_ni_auditar(): void
    {
        $limits = [
            'business_name' => 150,
            'fiscal_id' => 50,
            'fiscal_name' => 150,
            'commercial_activity' => 255,
            'fiscal_address' => 255,
            'fiscal_commune' => 100,
            'responsible_name' => 150,
            'responsible_phone' => 50,
        ];

        foreach ($limits as $field => $max) {
            $this->actingAs($this->admin)
                ->put('/settings/business', $this->validPayload([
                    $field => str_repeat('x', $max + 1),
                ]))
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload([
                'responsible_email' => 'no-es-un-correo',
            ]))
            ->assertSessionHasErrors('responsible_email');

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload([
                'responsible_email' => str_repeat('x', 250).'@prueba.cl',
            ]))
            ->assertSessionHasErrors('responsible_email');

        $this->assertDatabaseCount('audit_logs', 0);

        foreach (array_keys($this->validPayload()) as $key) {
            $this->assertNull($this->configValue($key), "La clave {$key} no debía persistirse.");
        }
    }

    public function test_auditoria_de_cambios_registra_actor_fecha_y_solo_claves_modificadas_sin_pii(): void
    {
        $piiName = 'PII-FICTICIA-CAMBIO-NOMBRE-741852';
        $piiEmail = 'pii-ficticia-cambio-741852@example.test';
        $piiPhone = 'PII-FICTICIA-CAMBIO-FONO-963258';

        DB::table('system_configs')->where('config_key', 'business_name')->update(['config_value' => 'Comercio Original']);
        DB::table('system_configs')->where('config_key', 'fiscal_id')->update(['config_value' => '76.543.210-8']);
        DB::table('system_configs')->where('config_key', 'fiscal_name')->update(['config_value' => 'Fiscal Original']);
        DB::table('system_configs')->where('config_key', 'commercial_activity')->update(['config_value' => 'Venta minorista']);
        DB::table('system_configs')->where('config_key', 'fiscal_address')->update(['config_value' => 'Av. Prueba 123']);
        DB::table('system_configs')->where('config_key', 'fiscal_commune')->update(['config_value' => 'Santiago']);
        DB::table('system_configs')->where('config_key', 'responsible_name')->update(['config_value' => null]);

        $this->actingAs($this->admin)
            ->put('/settings/business', [
                'business_name' => 'Comercio Nuevo',
                'fiscal_id' => '76.543.210-8',
                'fiscal_name' => 'Fiscal Original',
                'commercial_activity' => 'Venta minorista',
                'fiscal_address' => 'Av. Prueba 123',
                'fiscal_commune' => 'Santiago',
                'responsible_name' => $piiName,
                'responsible_email' => $piiEmail,
                'responsible_phone' => $piiPhone,
            ])
            ->assertRedirect(route('settings.business.edit'));

        $rows = DB::table('audit_logs')->where('event', 'core.business_settings.updated')->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame($this->admin->id, (int) $row->user_id);
        $this->assertNotNull($row->created_at);
        $this->assertSame('system_configs', $row->auditable_type);
        $this->assertSame('core', $row->module_code);
        $this->assertNull($row->old_values);

        $newValues = json_decode((string) $row->new_values, true);

        $this->assertSame(
            ['changed_keys' => ['business_name', 'responsible_name', 'responsible_email', 'responsible_phone']],
            $newValues
        );

        $serialized = (string) $row->old_values.(string) $row->new_values;

        foreach ([$piiName, $piiEmail, $piiPhone] as $pii) {
            $this->assertStringNotContainsString($pii, $serialized);
        }
    }

    public function test_sin_cambios_no_escribe_ni_audita(): void
    {
        foreach ($this->validPayload() as $key => $value) {
            DB::table('system_configs')->where('config_key', $key)->update(['config_value' => $value]);
        }

        $before = DB::table('system_configs')
            ->whereIn('config_key', array_keys($this->validPayload()))
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload())
            ->assertRedirect(route('settings.business.edit'))
            ->assertSessionHasNoErrors();

        $after = DB::table('system_configs')
            ->whereIn('config_key', array_keys($this->validPayload()))
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_las_nueve_claves(): void
    {
        foreach ($this->validPayload() as $key => $value) {
            DB::table('system_configs')->where('config_key', $key)->update(['config_value' => 'Original '.$key]);
        }

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
                ->put('/settings/business', $this->validPayload());
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        foreach ($this->validPayload() as $key => $value) {
            $this->assertSame('Original '.$key, $this->configValue($key));
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_suspendida_sin_gracia_bloquea_escritura_y_marca_solo_lectura(): void
    {
        $this->suspendLicense(null);

        $this->actingAs($this->admin)
            ->get('/settings/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload())
            ->assertSessionHasErrors('read_only');

        $this->assertNull($this->configValue('business_name'));
        $this->assertSame(0, DB::table('audit_logs')->where('event', 'core.business_settings.updated')->count());
    }

    public function test_licencia_suspendida_con_gracia_futura_permite_escritura(): void
    {
        $this->suspendLicense(now()->addDays(3)->toDateString());

        $this->actingAs($this->admin)
            ->get('/settings/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', false));

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload(['business_name' => 'Permitido en gracia']))
            ->assertRedirect(route('settings.business.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Permitido en gracia', $this->configValue('business_name'));
    }

    public function test_licencia_suspendida_con_gracia_vencida_bloquea_escritura(): void
    {
        $this->suspendLicense(now()->subDays(2)->toDateString());

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload())
            ->assertSessionHasErrors('read_only');

        $this->assertNull($this->configValue('business_name'));
        $this->assertSame(0, DB::table('audit_logs')->where('event', 'core.business_settings.updated')->count());
    }

    public function test_sin_fila_de_licencia_permite_escritura(): void
    {
        $this->deleteLicense();

        $this->actingAs($this->admin)
            ->get('/settings/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', false));

        $this->actingAs($this->admin)
            ->put('/settings/business', $this->validPayload(['business_name' => 'Permitido sin licencia']))
            ->assertRedirect(route('settings.business.edit'));

        $this->assertSame('Permitido sin licencia', $this->configValue('business_name'));
    }
}
