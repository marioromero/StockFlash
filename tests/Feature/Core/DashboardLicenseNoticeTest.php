<?php

namespace Tests\Feature\Core;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardLicenseNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->setLicense();

        $this->admin = $this->makeUser('Admin Central', [
            'global_role_id' => $this->role('central_admin')->id,
        ]);
    }

    public function test_usuario_anonimo_no_accede_al_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_licencia_active_no_muestra_banner(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice', null)
                ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->contains('settings.manage'))
            );
    }

    public function test_licencia_expiring_soon_muestra_aviso_con_fecha(): void
    {
        $this->setLicense([
            'status' => 'expiring_soon',
            'expires_at' => '2026-12-01',
        ]);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice.type', 'expiring_soon')
                ->where('license_notice.severity', 'warning')
                ->where('license_notice.date', '2026-12-01')
                ->where('license_notice.date_label', 'Fecha de término')
            );
    }

    public function test_licencia_suspended_con_gracia_futura_avisa_sin_bloquear(): void
    {
        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice.type', 'suspended_grace')
                ->where('license_notice.severity', 'warning')
                ->where('license_notice.date', now()->addDays(3)->toDateString())
                ->where('license_notice.date_label', 'Fin del período de gracia')
            );
    }

    public function test_licencia_suspended_fuera_de_gracia_avisa_solo_lectura(): void
    {
        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice.type', 'suspended_read_only')
                ->where('license_notice.severity', 'danger')
                ->where('license_notice.message', fn ($message): bool => str_contains($message, 'modo solo lectura'))
                ->where('license_notice.date', now()->subDay()->toDateString())
            );
    }

    public function test_licencia_suspended_sin_gracia_avisa_solo_lectura_sin_fecha(): void
    {
        $this->setLicense([
            'status' => 'suspended',
            'grace_until' => null,
        ]);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice.type', 'suspended_read_only')
                ->where('license_notice.severity', 'danger')
                ->where('license_notice.date', null)
                ->where('license_notice.date_label', null)
            );
    }

    public function test_sin_fila_de_licencia_informa_no_configurado_y_no_permite_crear_sucursales(): void
    {
        DB::table('license_states')->delete();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice.type', 'unconfigured')
                ->where('license_notice.severity', 'warning')
                ->where('license_notice.date', null)
            );

        $this->actingAs($this->admin)
            ->post('/branches', [
                'code' => 'SUC-NOLIC',
                'name' => 'Sucursal sin licencia',
                'type' => 'store',
            ])
            ->assertSessionHasErrors('max_branches');

        $this->assertDatabaseMissing('branches', ['code' => 'SUC-NOLIC']);
    }

    public function test_dashboard_no_expone_secretos_ni_campos_crudos_de_licencia(): void
    {
        $this->setLicense([
            'status' => 'expiring_soon',
            'expires_at' => '2026-12-01',
            'installation_key' => 'SECRET-INSTALLATION-KEY',
            'signed_payload' => 'SECRET-SIGNED-PAYLOAD',
            'signature' => 'SECRET-SIGNATURE',
            'message' => 'SECRET-CONTROL-MESSAGE',
            'max_users' => 37,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        foreach ([
            'SECRET-INSTALLATION-KEY',
            'SECRET-SIGNED-PAYLOAD',
            'SECRET-SIGNATURE',
            'SECRET-CONTROL-MESSAGE',
            'installation_key',
            'signed_payload',
            'max_users',
        ] as $needle) {
            $response->assertDontSee($needle, false);
        }
    }

    public function test_banner_no_altera_permisos_existentes_de_usuarios_sin_administracion(): void
    {
        $this->setLicense(['status' => 'expiring_soon', 'expires_at' => '2026-12-01']);

        $cashier = $this->makeUser('Cajero Dashboard', [
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($cashier)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('license_notice.type', 'expiring_soon')
                ->where('auth.permissions', fn ($permissions): bool => ! collect($permissions)->contains('settings.manage'))
                ->where('auth.permissions', fn ($permissions): bool => ! collect($permissions)->contains('users.manage'))
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(string $name, array $overrides = []): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create(array_merge([
            'name' => $name,
            'email' => "usuario-{$suffix}@prueba.cl",
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
            'must_change_password' => false,
        ], $overrides));
    }

    protected function role(string $code): Role
    {
        return Role::query()->where('code', $code)->firstOrFail();
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
                'max_users' => 0,
                'max_branches' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );
    }
}
