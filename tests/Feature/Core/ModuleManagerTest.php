<?php

namespace Tests\Feature\Core;

use App\Support\AuditLogger;
use App\Support\Modules\ModuleCatalog;
use App\Support\Modules\ModuleManager;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_exacto_de_codigos_y_dependencias_duras_blandas(): void
    {
        $expectedHard = [
            'core' => [],
            'catalog' => ['core'],
            'inventory' => ['catalog'],
            'purchasing' => ['inventory'],
            'sales' => ['catalog'],
            'pos' => ['sales'],
            'cash_control' => ['sales'],
            'customers' => ['core'],
            'sellers' => ['core'],
            'promotions' => ['catalog'],
            'combos' => ['catalog'],
            'price_lists' => ['catalog'],
            'fiscal' => ['sales'],
            'channels' => ['catalog'],
            'woo_sync' => ['channels'],
            'reports_basic' => ['core'],
            'reports_advanced' => ['reports_basic'],
            'audit_log' => ['core'],
            'customer_credit' => ['customers', 'sales'],
            'loyalty' => ['customers', 'sales'],
            'offline' => ['pos'],
            'lots_serials' => ['inventory'],
        ];

        $expectedSoft = [
            'core' => [],
            'catalog' => [],
            'inventory' => [],
            'purchasing' => [],
            'sales' => ['inventory', 'customers', 'sellers', 'cash_control', 'channels'],
            'pos' => ['cash_control', 'customers', 'sellers'],
            'cash_control' => ['pos'],
            'customers' => [],
            'sellers' => ['sales'],
            'promotions' => ['sales'],
            'combos' => ['inventory'],
            'price_lists' => ['customers', 'channels'],
            'fiscal' => ['customers', 'channels'],
            'channels' => ['inventory', 'sales', 'customers'],
            'woo_sync' => [],
            'reports_basic' => ['*'],
            'reports_advanced' => [],
            'audit_log' => [],
            'customer_credit' => [],
            'loyalty' => [],
            'offline' => [],
            'lots_serials' => [],
        ];

        $this->assertSame($expectedHard, ModuleCatalog::hardDependencies());
        $this->assertSame($expectedSoft, ModuleCatalog::softDependencies());
        $this->assertSame(array_keys($expectedHard), ModuleCatalog::codes());
        $this->assertCount(22, ModuleCatalog::codes());
    }

    public function test_estado_habilitacion_y_deshabilitacion_tecnicas(): void
    {
        $this->setLicense();

        $core = $this->manager()->state('core');
        $this->assertTrue($core['is_enabled']);
        $this->assertNull($core['enabled_at']);
        $this->assertSame([], $core['hard_dependencies']);
        $this->assertDatabaseCount('installation_modules', 0);

        $initial = $this->manager()->state('catalog');
        $this->assertFalse($initial['is_enabled']);
        $this->assertSame(['core'], $initial['hard_dependencies']);
        $this->assertSame([], $initial['soft_dependencies']);

        $enabled = $this->manager()->enable('catalog');
        $this->assertSame('catalog', $enabled['code']);
        $this->assertSame(['catalog'], $enabled['changed_modules']);

        $row = $this->moduleRow('catalog');
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->is_enabled);
        $this->assertNotNull($row->enabled_at);
        $this->assertNull($row->disabled_at);
        $this->assertTrue($this->manager()->state('catalog')['is_enabled']);

        $enabledAt = $row->enabled_at;

        $disabled = $this->manager()->disable('catalog');
        $this->assertSame(['catalog'], $disabled['changed_modules']);

        $row = $this->moduleRow('catalog');
        $this->assertSame(0, (int) $row->is_enabled);
        $this->assertSame($enabledAt, $row->enabled_at);
        $this->assertNotNull($row->disabled_at);
        $this->assertFalse($this->manager()->state('catalog')['is_enabled']);

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertNotNull($this->auditRow('core.module_enabled'));
        $this->assertNotNull($this->auditRow('core.module_disabled'));
    }

    public function test_cadena_de_dependencias_duras_bloquea_y_protege_indirectos(): void
    {
        $this->setLicense();

        try {
            $this->manager()->enable('inventory');
            $this->fail('Se esperaba DomainException por falta de catalog.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('catalog', $exception->getMessage());
        }

        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->manager()->enable('catalog');
        $this->manager()->enable('inventory');
        $this->manager()->enable('purchasing');

        try {
            $this->manager()->disable('catalog');
            $this->fail('Se esperaba DomainException por dependientes directos e indirectos.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('inventory', $exception->getMessage());
            $this->assertStringContainsString('purchasing', $exception->getMessage());
        }

        $this->assertSame(1, (int) $this->moduleRow('catalog')->is_enabled);
        $this->assertSame(1, (int) $this->moduleRow('inventory')->is_enabled);
        $this->assertSame(1, (int) $this->moduleRow('purchasing')->is_enabled);
    }

    public function test_woo_sync_activa_channels_en_la_misma_transaccion(): void
    {
        $this->setLicense();
        $this->manager()->enable('catalog');

        $result = $this->manager()->enable('woo_sync');

        $this->assertSame(['channels', 'woo_sync'], $result['changed_modules']);
        $this->assertSame(1, (int) $this->moduleRow('channels')->is_enabled);
        $this->assertSame(1, (int) $this->moduleRow('woo_sync')->is_enabled);
        $this->assertNotNull($this->moduleRow('channels')->enabled_at);
        $this->assertNotNull($this->moduleRow('woo_sync')->enabled_at);

        $audit = DB::table('audit_logs')
            ->where('event', 'core.module_enabled')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame(
            ['channels', 'woo_sync'],
            json_decode((string) $audit->new_values, true)['changed_modules']
        );
    }

    public function test_woo_sync_rechaza_si_channels_no_esta_licenciado_sin_cambios(): void
    {
        $this->setLicense(['catalog', 'woo_sync']);
        $this->manager()->enable('catalog');

        try {
            $this->manager()->enable('woo_sync');
            $this->fail('Se esperaba DomainException porque channels no está licenciado.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('channels', $exception->getMessage());
        }

        $this->assertNull($this->moduleRow('channels'));
        $this->assertNull($this->moduleRow('woo_sync'));
    }

    public function test_woo_sync_rechaza_si_channels_esta_activo_pero_no_licenciado(): void
    {
        $this->setLicense(['catalog', 'woo_sync']);
        $this->seedModule('catalog', enabled: true);
        $this->seedModule('channels', enabled: true);

        try {
            $this->manager()->enable('woo_sync');
            $this->fail('Se esperaba DomainException porque channels no está licenciado.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('channels', $exception->getMessage());
        }

        $this->assertNull($this->moduleRow('woo_sync'));
        $this->assertSame(1, (int) $this->moduleRow('channels')->is_enabled);
    }

    public function test_woo_sync_rechaza_si_catalog_no_esta_activo_sin_cambios(): void
    {
        $this->setLicense();
        $this->assertNull($this->moduleRow('catalog'));

        try {
            $this->manager()->enable('woo_sync');
            $this->fail('Se esperaba DomainException porque catalog no está activo.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('catalog', $exception->getMessage());
        }

        $this->assertNull($this->moduleRow('channels'));
        $this->assertNull($this->moduleRow('woo_sync'));
    }

    public function test_fallo_de_auditoria_revierte_woo_sync_y_channels_completos(): void
    {
        $this->setLicense();
        $this->seedModule('catalog', enabled: true);

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
                throw new RuntimeException('Auditoría forzada a fallar.');
            }
        });

        try {
            $this->app->make(ModuleManager::class)->enable('woo_sync');
            $this->fail('Se esperaba RuntimeException de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría forzada a fallar.', $exception->getMessage());
        }

        $this->assertNull($this->moduleRow('channels'));
        $this->assertNull($this->moduleRow('woo_sync'));
        $this->assertSame(1, (int) $this->moduleRow('catalog')->is_enabled);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_licencia_faltante_falla_cerrado_sin_escrituras(): void
    {
        try {
            $this->manager()->enable('catalog');
            $this->fail('Se esperaba DomainException sin licencia local.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('licencia', $exception->getMessage());
        }

        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_json_de_modulos_invalido_falla_cerrado_sin_escrituras(): void
    {
        $this->setLicenseRaw('{no-es-json');

        try {
            $this->manager()->enable('catalog');
            $this->fail('Se esperaba DomainException por JSON inválido.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('inválida', $exception->getMessage());
        }

        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_modulo_excluido_de_la_licencia_falla_cerrado_sin_escrituras(): void
    {
        $this->setLicense(['catalog']);

        try {
            $this->manager()->enable('inventory');
            $this->fail('Se esperaba DomainException por módulo no licenciado.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('inventory', $exception->getMessage());
        }

        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_comodin_de_licencia_permite_habilitar(): void
    {
        $this->setLicense(['*']);

        $result = $this->manager()->enable('catalog');

        $this->assertSame(['catalog'], $result['changed_modules']);
        $this->assertSame(1, (int) $this->moduleRow('catalog')->is_enabled);
    }

    public function test_suspension_fuera_de_gracia_bloquea_cambios(): void
    {
        $this->setLicense(
            ['*'],
            status: 'suspended',
            graceUntil: now()->subDays(2)->toDateString(),
        );

        try {
            $this->manager()->enable('catalog');
            $this->fail('Se esperaba DomainException fuera de gracia.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('solo lectura', $exception->getMessage());
        }

        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_suspension_dentro_de_gracia_permite_cambios(): void
    {
        $this->setLicense(
            ['*'],
            status: 'suspended',
            graceUntil: now()->addDays(2)->toDateString(),
        );

        $result = $this->manager()->enable('catalog');

        $this->assertSame(['catalog'], $result['changed_modules']);
        $this->assertSame(1, (int) $this->moduleRow('catalog')->is_enabled);
    }

    public function test_core_no_se_puede_desactivar_y_siempre_se_reporta_habilitado(): void
    {
        $enabled = $this->manager()->enable('core');

        $this->assertSame([], $enabled['changed_modules']);
        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertTrue($this->manager()->state('core')['is_enabled']);

        try {
            $this->manager()->disable('core');
            $this->fail('Se esperaba DomainException al desactivar core.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('core', $exception->getMessage());
        }

        $this->assertDatabaseCount('installation_modules', 0);
    }

    public function test_idempotencia_sin_reescribir_timestamps_ni_duplicar_auditoria(): void
    {
        $this->setLicense();

        $this->manager()->enable('catalog');
        $firstEnabled = $this->moduleRow('catalog');

        $second = $this->manager()->enable('catalog');
        $secondEnabled = $this->moduleRow('catalog');

        $this->assertSame([], $second['changed_modules']);
        $this->assertSame($firstEnabled->enabled_at, $secondEnabled->enabled_at);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'core.module_enabled')->count());

        $this->manager()->disable('catalog');
        $firstDisabled = $this->moduleRow('catalog');

        $second = $this->manager()->disable('catalog');
        $secondDisabled = $this->moduleRow('catalog');

        $this->assertSame([], $second['changed_modules']);
        $this->assertSame($firstDisabled->disabled_at, $secondDisabled->disabled_at);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'core.module_disabled')->count());
    }

    public function test_auditoria_atomica_sin_secretos_ni_json_de_licencia(): void
    {
        DB::table('license_states')->insert([
            'installation_key' => 'SECRET-INSTALLATION-KEY',
            'status' => 'active',
            'modules_enabled' => json_encode(['*']),
            'signed_payload' => 'SIGNED-PAYLOAD-X',
            'signature' => 'SIGNATURE-X',
            'message' => 'SECRET-MESSAGE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->manager()->enable('catalog');

        $audit = $this->auditRow('core.module_enabled');
        $this->assertNotNull($audit);
        $this->assertSame('core', $audit->module_code);
        $this->assertSame('installation_modules', $audit->auditable_type);

        $payload = json_decode((string) $audit->new_values, true);
        $this->assertSame('catalog', $payload['module']);
        $this->assertSame(['catalog'], $payload['changed_modules']);
        $this->assertArrayHasKey('actor', $payload);

        $serialized = (string) $audit->old_values.(string) $audit->new_values;

        foreach ([
            'SECRET-INSTALLATION-KEY',
            'SIGNED-PAYLOAD-X',
            'SIGNATURE-X',
            'SECRET-MESSAGE',
            'modules_enabled',
            'installation_key',
            'signed_payload',
            'signature',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
    }

    public function test_settings_se_preservan_al_habilitar_y_deshabilitar(): void
    {
        $this->setLicense();
        $settings = json_encode(['theme' => 'compacto', 'note' => 'conservar']);

        $this->seedModule('catalog', enabled: false, settings: $settings);

        $this->manager()->enable('catalog');
        $this->assertSame($settings, $this->moduleRow('catalog')->settings);

        $this->manager()->disable('catalog');
        $this->assertSame($settings, $this->moduleRow('catalog')->settings);
        $this->assertNotNull($this->moduleRow('catalog')->enabled_at);
    }

    public function test_modulo_no_declarado_es_rechazado_sin_escrituras(): void
    {
        $this->setLicense();

        foreach (['enable', 'disable', 'state'] as $operation) {
            try {
                $this->manager()->{$operation}('not_a_module');
                $this->fail("Se esperaba DomainException en {$operation}.");
            } catch (DomainException $exception) {
                $this->assertStringContainsString('desconocido', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('installation_modules', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function manager(): ModuleManager
    {
        return $this->app->make(ModuleManager::class);
    }

    /**
     * @param  list<string>  $modules
     */
    private function setLicense(
        array $modules = ['*'],
        string $status = 'active',
        ?string $graceUntil = null,
    ): void {
        $this->setLicenseRaw(json_encode($modules), $status, $graceUntil);
    }

    private function setLicenseRaw(
        string $modulesEnabled,
        string $status = 'active',
        ?string $graceUntil = null,
    ): void {
        DB::table('license_states')->insert([
            'installation_key' => 'test-installation',
            'status' => $status,
            'plan_code' => 'demo',
            'modules_enabled' => $modulesEnabled,
            'grace_until' => $graceUntil,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedModule(
        string $code,
        bool $enabled,
        ?string $settings = null,
    ): void {
        DB::table('installation_modules')->insert([
            'module_code' => $code,
            'is_enabled' => $enabled ? 1 : 0,
            'enabled_at' => $enabled ? now() : null,
            'disabled_at' => null,
            'settings' => $settings,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function moduleRow(string $code): ?object
    {
        return DB::table('installation_modules')->where('module_code', $code)->first();
    }

    private function auditRow(string $event): ?object
    {
        return DB::table('audit_logs')->where('event', $event)->orderBy('id')->first();
    }
}
