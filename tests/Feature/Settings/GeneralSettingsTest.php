<?php

namespace Tests\Feature\Settings;

use App\Support\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

class GeneralSettingsTest extends GeneralSettingsTestCase
{
    public function test_lectura_aplica_defectos_contractuales_si_faltan_las_filas_sin_escribir(): void
    {
        DB::table('system_configs')->whereIn('config_key', $this->keys())->delete();

        $beforeCount = DB::table('system_configs')->count();

        $this->actingAs($this->admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/General')
                ->where('country', 'CL')
                ->where('currency_code', 'CLP')
                ->where('currency_decimals', 0)
                ->where('timezone', 'America/Santiago')
                ->where('locale', 'es-CL')
                ->where('prices_include_tax', true)
                ->where('cash_rounding_step', 1)
                ->where('read_only', false)
                ->missing('responsible_name')
                ->missing('fiscal_provider_config')
                ->missing('is_setup_completed')
                ->missing('system_configs'));

        $this->assertSame($beforeCount, DB::table('system_configs')->count());
        $this->assertSame(0, DB::table('system_configs')->whereIn('config_key', $this->keys())->count());
    }

    public function test_lectura_muestra_los_valores_guardados_sin_exponer_otras_claves(): void
    {
        DB::table('system_configs')->where('config_key', 'country')->update(['config_value' => 'US']);
        DB::table('system_configs')->where('config_key', 'currency_code')->update(['config_value' => 'USD']);
        DB::table('system_configs')->where('config_key', 'currency_decimals')->update(['config_value' => '2']);
        DB::table('system_configs')->where('config_key', 'timezone')->update(['config_value' => 'UTC']);
        DB::table('system_configs')->where('config_key', 'locale')->update(['config_value' => 'en-US']);
        DB::table('system_configs')->where('config_key', 'prices_include_tax')->update(['config_value' => '0']);
        DB::table('system_configs')->where('config_key', 'cash_rounding_step')->update(['config_value' => '10']);

        $this->actingAs($this->admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/General')
                ->where('country', 'US')
                ->where('currency_code', 'USD')
                ->where('currency_decimals', 2)
                ->where('timezone', 'UTC')
                ->where('locale', 'en-US')
                ->where('prices_include_tax', false)
                ->where('cash_rounding_step', 10)
                ->where('read_only', false)
                ->missing('business_name')
                ->missing('receipt_header')
                ->missing('is_secret')
                ->missing('system_configs'));
    }

    public function test_guardado_persiste_las_siete_claves_normalizadas_y_preserva_metadatos(): void
    {
        DB::table('system_configs')->where('config_key', 'currency_code')->update([
            'description' => 'Descripción heredada',
            'value_type' => 'text',
            'is_secret' => 1,
            'module_code' => 'legacy',
        ]);

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload([
                'country' => ' us ',
                'currency_code' => ' usd ',
                'currency_decimals' => 4,
                'timezone' => 'UTC',
                'locale' => 'en-US',
                'prices_include_tax' => false,
                'cash_rounding_step' => 10,
            ]))
            ->assertRedirect(route('settings.general.edit'))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $this->assertSame('US', $this->configValue('country'));
        $this->assertSame('USD', $this->configValue('currency_code'));
        $this->assertSame('4', $this->configValue('currency_decimals'));
        $this->assertSame('UTC', $this->configValue('timezone'));
        $this->assertSame('en-US', $this->configValue('locale'));
        $this->assertSame('0', $this->configValue('prices_include_tax'));
        $this->assertSame('10', $this->configValue('cash_rounding_step'));

        $currency = DB::table('system_configs')->where('config_key', 'currency_code')->firstOrFail();
        $this->assertSame('Descripción heredada', $currency->description);
        $this->assertSame('text', $currency->value_type);
        $this->assertSame(1, (int) $currency->is_secret);
        $this->assertSame('legacy', $currency->module_code);
        $this->assertSame($this->admin->id, (int) $currency->updated_by_user_id);

        $this->actingAs($this->admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('country', 'US')
                ->where('currency_code', 'USD')
                ->where('currency_decimals', 4)
                ->where('timezone', 'UTC')
                ->where('locale', 'en-US')
                ->where('prices_include_tax', false)
                ->where('cash_rounding_step', 10));
    }

    public function test_guardado_crea_solo_las_filas_faltantes_con_tipos_contractuales(): void
    {
        DB::table('system_configs')->where('config_key', 'country')->delete();
        DB::table('system_configs')->where('config_key', 'prices_include_tax')->delete();

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload())
            ->assertRedirect(route('settings.general.edit'));

        $country = DB::table('system_configs')->where('config_key', 'country')->firstOrFail();
        $this->assertSame('CL', $country->config_value);
        $this->assertSame('string', $country->value_type);
        $this->assertSame(0, (int) $country->is_secret);
        $this->assertNull($country->module_code);
        $this->assertSame($this->admin->id, (int) $country->updated_by_user_id);

        $prices = DB::table('system_configs')->where('config_key', 'prices_include_tax')->firstOrFail();
        $this->assertSame('1', $prices->config_value);
        $this->assertSame('bool', $prices->value_type);
        $this->assertSame(0, (int) $prices->is_secret);
        $this->assertNull($prices->module_code);
    }

    public function test_guardado_preserva_pii_secretos_comprobante_y_otras_filas(): void
    {
        $pii = 'PII-FICTICIA-GENERAL-741852';
        $secret = '{"secreto":"no-debe-tocarse"}';

        DB::table('system_configs')->where('config_key', 'responsible_name')->update(['config_value' => $pii]);
        DB::table('system_configs')->where('config_key', 'responsible_email')->update(['config_value' => 'pii-general@example.test']);
        DB::table('system_configs')->where('config_key', 'responsible_phone')->update(['config_value' => '+56 9 0000 1111']);
        DB::table('system_configs')->where('config_key', 'fiscal_provider_config')->update(['config_value' => $secret]);
        DB::table('system_configs')->where('config_key', 'receipt_header')->update(['config_value' => 'Encabezado intacto']);
        DB::table('system_configs')->where('config_key', 'is_setup_completed')->update(['config_value' => '1']);

        $owned = $this->keys();

        $before = DB::table('system_configs')
            ->whereNotIn('config_key', $owned)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload([
                'country' => 'AR',
                'currency_code' => 'ARS',
                'currency_decimals' => 2,
                'timezone' => 'America/Argentina/Buenos_Aires',
                'locale' => 'es-AR',
                'prices_include_tax' => false,
                'cash_rounding_step' => 1,
            ]))
            ->assertRedirect(route('settings.general.edit'));

        $after = DB::table('system_configs')
            ->whereNotIn('config_key', $owned)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertSame($pii, $this->configValue('responsible_name'));
        $this->assertSame($secret, $this->configValue('fiscal_provider_config'));
        $this->assertSame('Encabezado intacto', $this->configValue('receipt_header'));
        $this->assertSame('1', $this->configValue('is_setup_completed'));
    }

    public function test_validaciones_de_formato_rechazan_cada_campo_sin_persistir_ni_auditar(): void
    {
        $invalid = [
            'country' => 'CHL',
            'currency_code' => 'CL',
            'currency_decimals' => 5,
            'timezone' => 'Marte/Olympus',
            'locale' => 'es_CL',
            'prices_include_tax' => 'quizas',
            'cash_rounding_step' => 0,
        ];

        $before = $this->ownedValues();

        foreach ($invalid as $field => $value) {
            $this->actingAs($this->admin)
                ->put('/settings/general', $this->validPayload([$field => $value]))
                ->assertSessionHasErrors($field);
        }

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_campos_extra_rechazan_el_payload_completo_sin_escribir(): void
    {
        $before = $this->ownedValues();

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload([
                'evil_key' => 'no',
                'is_secret' => 1,
                'value_type' => 'json',
                'module_code' => 'hack',
            ]))
            ->assertSessionHasErrors('payload');

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseMissing('system_configs', ['config_key' => 'evil_key']);
        $this->assertSame(0, (int) DB::table('system_configs')->where('config_key', 'country')->value('is_secret'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_put_incompleto_rechaza_sin_escribir_ni_auditar(): void
    {
        $before = $this->ownedValues();

        foreach ($this->keys() as $missing) {
            $this->actingAs($this->admin)
                ->put('/settings/general', Arr::except($this->validPayload(), [$missing]))
                ->assertSessionHasErrors($missing);
        }

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_auditoria_registra_actor_y_solo_claves_realmente_cambiadas(): void
    {
        DB::table('system_configs')->where('config_key', 'currency_code')->update(['config_value' => 'USD']);
        DB::table('system_configs')->where('config_key', 'timezone')->update(['config_value' => 'UTC']);
        DB::table('system_configs')->where('config_key', 'prices_include_tax')->update(['config_value' => '0']);

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload())
            ->assertRedirect(route('settings.general.edit'));

        $rows = DB::table('audit_logs')->where('event', 'core.general_settings.updated')->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame($this->admin->id, (int) $row->user_id);
        $this->assertNotNull($row->created_at);
        $this->assertSame('system_configs', $row->auditable_type);
        $this->assertSame('core', $row->module_code);
        $this->assertNull($row->old_values);
        $this->assertSame(0, (int) $row->is_personal_data_access);

        $newValues = json_decode((string) $row->new_values, true);

        $this->assertSame(
            ['currency_code', 'timezone', 'prices_include_tax'],
            $newValues['changed_keys']
        );
    }

    public function test_sin_cambios_no_escribe_filas_ni_audita(): void
    {
        $before = DB::table('system_configs')
            ->whereIn('config_key', $this->keys())
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload())
            ->assertRedirect(route('settings.general.edit'))
            ->assertSessionHasNoErrors();

        $after = DB::table('system_configs')
            ->whereIn('config_key', $this->keys())
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_fallo_de_auditoria_revierte_las_siete_claves(): void
    {
        $before = $this->ownedValues();

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
                ->put('/settings/general', $this->validPayload([
                    'country' => 'AR',
                    'currency_code' => 'ARS',
                    'currency_decimals' => 2,
                    'timezone' => 'America/Argentina/Buenos_Aires',
                    'locale' => 'es-AR',
                    'prices_include_tax' => false,
                    'cash_rounding_step' => 10,
                ]));
            $this->fail('Se esperaba la excepción del registro de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_suspendida_fuera_de_gracia_bloquea_escritura_sin_persistir(): void
    {
        $this->suspendLicense(null);

        $this->actingAs($this->admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $before = $this->ownedValues();

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload([
                'country' => 'AR',
                'currency_code' => 'ARS',
            ]))
            ->assertSessionHasErrors('read_only');

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_suspendida_con_gracia_vencida_bloquea_escritura_sin_persistir(): void
    {
        $this->suspendLicense(now()->subDays(2)->toDateString());

        $before = $this->ownedValues();

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload(['country' => 'AR']))
            ->assertSessionHasErrors('read_only');

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_suspendida_con_gracia_futura_permite_escritura(): void
    {
        $this->suspendLicense(now()->addDays(3)->toDateString());

        $this->actingAs($this->admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', false));

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload(['country' => 'AR']))
            ->assertRedirect(route('settings.general.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('AR', $this->configValue('country'));
    }

    public function test_sin_fila_de_licencia_permite_escritura(): void
    {
        $this->deleteLicense();

        $this->actingAs($this->admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', false));

        $this->actingAs($this->admin)
            ->put('/settings/general', $this->validPayload(['country' => 'AR']))
            ->assertRedirect(route('settings.general.edit'));

        $this->assertSame('AR', $this->configValue('country'));
    }
}
