<?php

namespace Tests\Feature\Settings;

use App\Models\BranchUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class BusinessSettingsAuthorizationTest extends BusinessSettingsTestCase
{
    public function test_usuario_global_sin_settings_manage_recibe_403_en_lectura_y_escritura(): void
    {
        $sinPermiso = $this->makeUser([
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($sinPermiso)
            ->get('/settings/business')
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->put('/settings/business', $this->validPayload())
            ->assertForbidden();

        $this->assertNull($this->configValue('business_name'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_usuario_por_sucursal_sin_settings_manage_recibe_403_en_lectura_y_escritura(): void
    {
        $branch = $this->makeBranch();
        $usuario = $this->makeUser([
            'global_role_id' => null,
            'default_branch_id' => $branch->id,
        ]);

        BranchUser::create([
            'user_id' => $usuario->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($usuario)
            ->get('/settings/business')
            ->assertForbidden();

        $this->actingAs($usuario)
            ->put('/settings/business', $this->validPayload())
            ->assertForbidden();

        $this->assertNull($this->configValue('business_name'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $this->get('/settings/business')->assertRedirect('/login');
        $this->put('/settings/business', $this->validPayload())->assertRedirect('/login');

        $this->assertNull($this->configValue('business_name'));
    }

    public function test_rutas_exigen_permiso_y_middleware_del_grupo_protegido(): void
    {
        $edit = Route::getRoutes()->getByName('settings.business.edit');
        $update = Route::getRoutes()->getByName('settings.business.update');

        $this->assertNotNull($edit);
        $this->assertNotNull($update);

        foreach ([$edit, $update] as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware);
            $this->assertContains('password.change', $middleware);
            $this->assertContains('verify.session', $middleware);
            $this->assertContains('can.permission:settings.manage', $middleware);
        }
    }

    public function test_patch_no_esta_soportado_y_no_modifica_las_nueve_claves(): void
    {
        DB::table('system_configs')->where('config_key', 'business_name')->update(['config_value' => 'Comercio intacto']);
        DB::table('system_configs')->where('config_key', 'responsible_email')->update(['config_value' => 'intacto@prueba.cl']);

        $before = DB::table('system_configs')
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->actingAs($this->admin)
            ->patch('/settings/business', [
                'business_name' => 'No debe aplicarse',
            ])
            ->assertStatus(405);

        $after = DB::table('system_configs')
            ->orderBy('config_key')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($before, $after);
        $this->assertSame('Comercio intacto', $this->configValue('business_name'));
        $this->assertSame('intacto@prueba.cl', $this->configValue('responsible_email'));
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
