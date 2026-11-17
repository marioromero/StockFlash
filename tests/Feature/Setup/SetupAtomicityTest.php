<?php

namespace Tests\Feature\Setup;

use App\Models\Branch;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SetupAtomicityTest extends SetupTestCase
{
    public function test_post_valido_crea_una_sucursal_un_admin_y_solo_las_16_claves(): void
    {
        $this->setLicenseFixture();
        $beforeCount = DB::table('system_configs')->count();
        $receiptHeader = $this->config('receipt_header');
        $fiscalProvider = $this->config('fiscal_provider_config');
        $licenseBefore = (array) DB::table('license_states')->first();

        $response = $this->postSetup();

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, DB::table('branch_user')->count());

        $branch = Branch::query()->firstOrFail();
        $this->assertSame('SUC-01', $branch->code);
        $this->assertSame('Sucursal Inicial', $branch->name);
        $this->assertSame('store', $branch->type);
        $this->assertSame('Avenida Ficticia 456', $branch->address);
        $this->assertSame('Providencia', $branch->commune);
        $this->assertSame('Santiago', $branch->city);
        $this->assertSame('Metropolitana', $branch->region);
        $this->assertSame('+56 2 8765 4321', $branch->phone);
        $this->assertTrue($branch->is_active);
        $this->assertTrue($branch->requires_cash_control);
        $this->assertNull($branch->settings);

        $admin = User::query()->firstOrFail();
        $roleId = DB::table('roles')->where('code', 'central_admin')->where('is_system', 1)->value('id');

        $this->assertSame('Admin Central', $admin->name);
        $this->assertSame('admin@prueba.test', $admin->email);
        $this->assertSame((int) $roleId, (int) $admin->global_role_id);
        $this->assertTrue($admin->is_active);
        $this->assertFalse($admin->must_change_password);
        $this->assertNotNull($admin->password_changed_at);
        $this->assertNull($admin->pin_hash);
        $this->assertTrue(Hash::check('ContrasenaSegura123!', $admin->password));
        $this->assertNotSame('ContrasenaSegura123!', $admin->password);

        $this->assertSame('1', $this->flagValue());
        $this->assertSame(
            (int) $admin->id,
            (int) DB::table('system_configs')
                ->where('config_key', 'is_setup_completed')
                ->value('updated_by_user_id'),
        );

        $this->assertSame($beforeCount, DB::table('system_configs')->count());
        $this->assertSame($receiptHeader, $this->config('receipt_header'));
        $this->assertSame($fiscalProvider, $this->config('fiscal_provider_config'));
        $this->assertSame($licenseBefore, (array) DB::table('license_states')->first());

        $expected = [
            'business_name' => 'Comercio Ficticio',
            'fiscal_id' => '76000000-0',
            'fiscal_name' => 'Comercio Ficticio SpA',
            'commercial_activity' => 'Venta minorista ficticia',
            'fiscal_address' => 'Calle Ficticia 123',
            'fiscal_commune' => 'Santiago',
            'responsible_name' => 'Responsable Ficticio',
            'responsible_email' => 'responsable@prueba.test',
            'responsible_phone' => '+56 2 1234 5678',
            'country' => 'CL',
            'currency_code' => 'CLP',
            'currency_decimals' => '0',
            'timezone' => 'America/Santiago',
            'locale' => 'es-CL',
            'prices_include_tax' => '1',
            'cash_rounding_step' => '1',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $this->config($key), "Valor inesperado para {$key}");
        }

        $this->auditContainsNoPiiOrSecrets();
        $this->assertSame(3, DB::table('audit_logs')->count());
        $this->assertSame(
            3,
            DB::table('audit_logs')->where('module_code', 'core')->count(),
        );
        $this->assertSame(
            1,
            DB::table('audit_logs')->where('event', 'core.setup.completed')->count(),
        );

        $this->assertNull(session('setup_flow_token'));
    }

    public function test_segundo_post_tras_exito_no_crea_nada_ni_reabre_el_asistente(): void
    {
        $this->postSetup()->assertRedirect(route('login'));

        $branchId = Branch::query()->value('id');
        $adminId = User::query()->value('id');

        $this->post('/setup', $this->validPayload(['setup_token' => 'cualquier-token']))
            ->assertStatus(404);

        $this->get('/setup')->assertRedirect(route('login'));

        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame((int) $branchId, (int) Branch::query()->value('id'));
        $this->assertSame((int) $adminId, (int) User::query()->value('id'));
    }

    public function test_rollback_integral_si_falla_la_auditoria(): void
    {
        $token = $this->getFlowToken();

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
                throw new RuntimeException('fallo de auditoría simulado');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->post('/setup', $this->validPayload(['setup_token' => $token]));
            $this->fail('Se esperaba la excepción de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertSame(0, Branch::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame('0', $this->flagValue());
        $this->assertNull($this->config('business_name'));
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_rollback_integral_si_falla_la_persistencia_del_admin(): void
    {
        $token = $this->getFlowToken();

        User::creating(function (): void {
            throw new RuntimeException('fallo de persistencia simulado');
        });

        try {
            $this->withoutExceptionHandling()
                ->post('/setup', $this->validPayload(['setup_token' => $token]));
            $this->fail('Se esperaba la excepción de persistencia.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fallo de persistencia simulado', $exception->getMessage());
        } finally {
            User::flushEventListeners();
        }

        $this->assertSame(0, Branch::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame('0', $this->flagValue());
        $this->assertNull($this->config('business_name'));
    }

    public function test_email_duplicado_preexistente_bloquea_sin_escrituras(): void
    {
        User::create([
            'name' => 'Admin Existente',
            'email' => 'admin@prueba.test',
            'password' => 'ContrasenaSegura123!',
            'global_role_id' => DB::table('roles')->where('code', 'central_admin')->value('id'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->post('/setup', $this->validPayload(['setup_token' => 'token']));

        $response->assertStatus(409);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Branch::query()->count());
        $this->assertSame('0', $this->flagValue());
        $this->assertNull($this->config('business_name'));
    }

    public function test_dos_finalizaciones_solo_escriben_una_vez(): void
    {
        $this->postSetup()->assertRedirect(route('login'));

        $firstBranch = Branch::query()->firstOrFail();
        $firstAdmin = User::query()->firstOrFail();

        $this->post('/setup', $this->validPayload([
            'setup_token' => 'otro-token',
            'branch_code' => 'SUC-02',
            'admin_email' => 'otro-admin@prueba.test',
        ]))->assertStatus(404);

        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame($firstBranch->code, Branch::query()->value('code'));
        $this->assertSame($firstAdmin->email, User::query()->value('email'));
    }

    private function setLicenseFixture(): void
    {
        DB::table('license_states')->updateOrInsert(
            ['installation_key' => 'setup-test'],
            [
                'status' => 'active',
                'plan_code' => 'free',
                'max_users' => 50,
                'max_branches' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function config(string $key): ?string
    {
        return DB::table('system_configs')
            ->where('config_key', $key)
            ->value('config_value');
    }

    private function auditContainsNoPiiOrSecrets(): void
    {
        $forbidden = [
            'ContrasenaSegura123!',
            'admin@prueba.test',
            'Admin Central',
            '76000000-0',
            'Responsable Ficticio',
            'Comercio Ficticio',
            '$2y$',
        ];

        foreach (DB::table('audit_logs')->get() as $row) {
            $blob = (string) $row->old_values . (string) $row->new_values;

            foreach ($forbidden as $value) {
                $this->assertStringNotContainsString($value, $blob);
            }
        }
    }
}
