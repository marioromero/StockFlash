<?php

namespace Tests\Feature\Core;

use App\Contracts\LicenseValidator;
use App\Support\License\InstallationStatusReport;
use App\Support\License\LicenseReadOnlyRule;
use App\Support\License\LicenseStateResolver;
use App\Support\License\LocalLicenseStub;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class LicenseValidatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const LIMIT_KEYS = [
        'max_branches',
        'max_pos_stations',
        'max_channels',
        'max_users',
        'max_products',
        'max_monthly_sales',
    ];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_stub_implementa_el_contrato_completo(): void
    {
        $stub = new LocalLicenseStub();

        $this->assertInstanceOf(LicenseValidator::class, $stub);

        foreach ([
            'isValid',
            'getLicenseInfo',
            'getLimits',
            'hasModule',
            'getDaysRemaining',
            'reportStatus',
        ] as $method) {
            $this->assertTrue(method_exists($stub, $method), "Falta el método {$method}.");
        }
    }

    public function test_stub_devuelve_limites_ilimitados_y_marca_simulacion(): void
    {
        $stub = new LocalLicenseStub();

        $this->assertTrue($stub->isValid());

        $limits = $stub->getLimits();

        foreach (self::LIMIT_KEYS as $key) {
            $this->assertArrayHasKey($key, $limits);
            $this->assertSame(0, $limits[$key], "{$key} debe ser 0 (ilimitado) en simulación.");
        }

        $info = $stub->getLicenseInfo();

        $this->assertTrue($info['is_simulated']);
        $this->assertSame('simulation', $info['mode']);
        $this->assertSame('active', $info['status']);
        $this->assertNull($info['expires_at']);
        $this->assertArrayNotHasKey('installation_key', $info);
        $this->assertArrayNotHasKey('signed_payload', $info);
        $this->assertArrayNotHasKey('signature', $info);
    }

    public function test_stub_declara_modulos_simulados_y_sin_vencimiento(): void
    {
        $stub = new LocalLicenseStub();

        $this->assertTrue($stub->hasModule('pos'));
        $this->assertTrue($stub->hasModule('bodega'));
        $this->assertFalse($stub->hasModule(''));
        $this->assertFalse($stub->hasModule('   '));
        $this->assertNull($stub->getDaysRemaining());
    }

    public function test_stub_reporta_estado_sin_trafico_saliente(): void
    {
        Http::fake();

        (new LocalLicenseStub())->reportStatus(new InstallationStatusReport(
            appVersion: '2.2.0',
            schemaVersion: '000020',
            activeModules: ['pos', 'bodega'],
            versionChannel: 'stable',
            lastUpdatedAt: '2026-11-16T10:00:00Z',
        ));

        Http::assertNothingSent();
    }

    public function test_stub_no_puede_instanciarse_en_produccion(): void
    {
        $original = app()['env'];
        app()['env'] = 'production';

        try {
            $this->expectException(LogicException::class);

            new LocalLicenseStub();
        } finally {
            app()['env'] = $original;
        }
    }

    public function test_stub_no_modifica_la_fila_de_licencia(): void
    {
        $this->setLicense(['status' => 'suspended', 'grace_until' => '2020-01-01']);

        $before = (array) DB::table('license_states')->orderBy('id')->first();

        $stub = new LocalLicenseStub();
        $stub->isValid();
        $stub->getLicenseInfo();
        $stub->getLimits();
        $stub->hasModule('pos');
        $stub->getDaysRemaining();
        $stub->reportStatus(new InstallationStatusReport('2.2.0', '000020'));

        $after = (array) DB::table('license_states')->orderBy('id')->first();

        $this->assertSame($before, $after);
    }

    public function test_resolver_sin_fila_informa_no_configurado_sin_conceder_creacion(): void
    {
        DB::table('license_states')->delete();

        $resolver = new LicenseStateResolver();
        $snapshot = $resolver->resolve();
        $notice = $resolver->dashboardNotice();

        $this->assertFalse($snapshot->configured);
        $this->assertNull($snapshot->status);
        $this->assertFalse($snapshot->readOnly);
        $this->assertFalse($snapshot->inGrace);
        $this->assertNull($snapshot->expiresAt);
        $this->assertNull($snapshot->graceUntil);

        $this->assertSame('unconfigured', $notice['type']);
        $this->assertSame('warning', $notice['severity']);
        $this->assertStringContainsString('licencia configurada', $notice['message']);
        $this->assertStringContainsString('bloqueadas', $notice['message']);
        $this->assertNull($notice['date']);
        $this->assertNull($notice['date_label']);
    }

    public function test_resolver_active_no_muestra_banner_ni_infiere_suspension_por_vencimiento(): void
    {
        $this->setLicense([
            'status' => 'active',
            'expires_at' => '2020-01-01',
            'grace_until' => null,
        ]);

        $resolver = new LicenseStateResolver();

        $this->assertFalse($resolver->isReadOnly());
        $this->assertNull($resolver->dashboardNotice());
    }

    public function test_resolver_expiring_soon_muestra_fecha_de_termino_sin_bloquear(): void
    {
        $this->setLicense([
            'status' => 'expiring_soon',
            'expires_at' => '2026-12-01',
            'grace_until' => null,
        ]);

        $resolver = new LicenseStateResolver();
        $notice = $resolver->dashboardNotice();

        $this->assertFalse($resolver->isReadOnly());
        $this->assertSame('expiring_soon', $notice['type']);
        $this->assertSame('warning', $notice['severity']);
        $this->assertSame('2026-12-01', $notice['date']);
        $this->assertSame('Fecha de término', $notice['date_label']);
    }

    public function test_resolver_expiring_soon_sin_fecha_no_inventa_fecha(): void
    {
        $this->setLicense(['status' => 'expiring_soon', 'expires_at' => null]);

        $notice = (new LicenseStateResolver())->dashboardNotice();

        $this->assertSame('expiring_soon', $notice['type']);
        $this->assertNull($notice['date']);
        $this->assertNull($notice['date_label']);
    }

    public function test_resolver_suspended_con_gracia_futura_avisa_sin_bloquear(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 16, 10, 0, 0));

        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => '2026-11-17',
        ]);

        $resolver = new LicenseStateResolver();
        $snapshot = $resolver->resolve();
        $notice = $resolver->dashboardNotice();

        $this->assertTrue($snapshot->inGrace);
        $this->assertFalse($snapshot->readOnly);
        $this->assertFalse($resolver->isReadOnly());
        $this->assertSame('suspended_grace', $notice['type']);
        $this->assertSame('warning', $notice['severity']);
        $this->assertSame('2026-11-17', $notice['date']);
        $this->assertSame('Fin del período de gracia', $notice['date_label']);
    }

    public function test_resolver_suspended_con_gracia_del_mismo_dia_sigue_en_gracia(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 16, 10, 0, 0));

        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => '2026-11-16',
        ]);

        $resolver = new LicenseStateResolver();

        $this->assertFalse($resolver->isReadOnly());
        $this->assertSame('suspended_grace', $resolver->dashboardNotice()['type']);
    }

    public function test_resolver_suspended_con_gracia_vencida_es_solo_lectura(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 16, 10, 0, 0));

        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => '2026-11-15',
        ]);

        $resolver = new LicenseStateResolver();
        $snapshot = $resolver->resolve();
        $notice = $resolver->dashboardNotice();

        $this->assertTrue($snapshot->readOnly);
        $this->assertFalse($snapshot->inGrace);
        $this->assertTrue($resolver->isReadOnly());
        $this->assertSame('suspended_read_only', $notice['type']);
        $this->assertSame('danger', $notice['severity']);
        $this->assertStringContainsString('modo solo lectura', $notice['message']);
        $this->assertSame('2026-11-15', $notice['date']);
    }

    public function test_resolver_suspended_sin_gracia_es_solo_lectura_sin_fecha(): void
    {
        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => null,
        ]);

        $resolver = new LicenseStateResolver();
        $notice = $resolver->dashboardNotice();

        $this->assertTrue($resolver->isReadOnly());
        $this->assertSame('suspended_read_only', $notice['type']);
        $this->assertSame('danger', $notice['severity']);
        $this->assertNull($notice['date']);
        $this->assertNull($notice['date_label']);
    }

    public function test_resolver_no_expone_secretos_ni_limites_confidenciales(): void
    {
        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => '2020-01-01',
            'installation_key' => 'SECRET-INSTALLATION-KEY',
            'signed_payload' => 'SECRET-SIGNED-PAYLOAD',
            'signature' => 'SECRET-SIGNATURE',
            'message' => 'SECRET-CONTROL-MESSAGE',
            'plan_code' => 'enterprise',
            'max_users' => 37,
            'max_products' => 12345,
        ]);

        $resolver = new LicenseStateResolver();
        $snapshotFields = get_object_vars($resolver->resolve());
        $notice = $resolver->dashboardNotice();

        $this->assertSame(
            ['configured', 'status', 'readOnly', 'inGrace', 'expiresAt', 'graceUntil'],
            array_keys($snapshotFields)
        );

        $this->assertSame(
            ['type', 'severity', 'message', 'date', 'date_label'],
            array_keys($notice)
        );

        $serialized = json_encode([$snapshotFields, $notice], JSON_THROW_ON_ERROR);

        foreach ([
            'SECRET-INSTALLATION-KEY',
            'SECRET-SIGNED-PAYLOAD',
            'SECRET-SIGNATURE',
            'SECRET-CONTROL-MESSAGE',
            'enterprise',
            '12345',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $serialized);
        }
    }

    public function test_resolver_y_stub_no_realizan_solicitudes_salientes(): void
    {
        Http::fake();

        $this->setLicense(['status' => 'expiring_soon', 'expires_at' => '2026-12-01']);

        $resolver = new LicenseStateResolver();
        $resolver->resolve();
        $resolver->isReadOnly();
        $resolver->dashboardNotice();

        $stub = new LocalLicenseStub();
        $stub->reportStatus(new InstallationStatusReport('2.2.0', '000020'));

        Http::assertNothingSent();
    }

    public function test_regla_de_solo_lectura_se_comparte_con_los_guards_existentes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 16, 10, 0, 0));

        $this->assertFalse(LicenseReadOnlyRule::isReadOnly(null));
        $this->assertFalse(LicenseReadOnlyRule::isReadOnly((object) ['status' => 'active', 'grace_until' => null]));
        $this->assertTrue(LicenseReadOnlyRule::isReadOnly((object) ['status' => 'suspended', 'grace_until' => null]));
        $this->assertFalse(LicenseReadOnlyRule::isReadOnly((object) ['status' => 'suspended', 'grace_until' => '2026-11-17']));
        $this->assertTrue(LicenseReadOnlyRule::isReadOnly((object) ['status' => 'suspended', 'grace_until' => '2026-11-15']));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function setLicense(array $overrides = []): void
    {
        DB::table('license_states')->updateOrInsert(
            ['installation_key' => 'test-installation'],
            array_merge([
                'status' => 'active',
                'plan_code' => 'demo',
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );
    }
}
