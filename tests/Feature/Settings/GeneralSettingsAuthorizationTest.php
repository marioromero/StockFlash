<?php

namespace Tests\Feature\Settings;

use App\Models\BranchUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class GeneralSettingsAuthorizationTest extends GeneralSettingsTestCase
{
    public function test_usuario_global_sin_settings_manage_recibe_403_en_lectura_y_escritura(): void
    {
        $sinPermiso = $this->makeUser([
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $before = $this->ownedValues();

        $this->actingAs($sinPermiso)
            ->get('/settings/general')
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->put('/settings/general', $this->validPayload(['country' => 'AR']))
            ->assertForbidden();

        $this->assertSame($before, $this->ownedValues());
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

        $before = $this->ownedValues();

        $this->actingAs($usuario)
            ->get('/settings/general')
            ->assertForbidden();

        $this->actingAs($usuario)
            ->put('/settings/general', $this->validPayload(['country' => 'AR']))
            ->assertForbidden();

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $before = $this->ownedValues();

        $this->get('/settings/general')->assertRedirect('/login');
        $this->put('/settings/general', $this->validPayload(['country' => 'AR']))->assertRedirect('/login');

        $this->assertSame($before, $this->ownedValues());
    }

    public function test_rutas_exigen_permiso_y_middleware_del_grupo_protegido(): void
    {
        $edit = Route::getRoutes()->getByName('settings.general.edit');
        $update = Route::getRoutes()->getByName('settings.general.update');

        $this->assertNotNull($edit);
        $this->assertNotNull($update);

        foreach ([$edit, $update] as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware);
            $this->assertContains('password.change', $middleware);
            $this->assertContains('verify.session', $middleware);
            $this->assertContains('can.permission:settings.manage', $middleware);
            $this->assertNotContains('branch.access', $middleware);
        }
    }

    public function test_patch_no_esta_soportado_y_no_modifica_las_siete_claves(): void
    {
        $before = $this->ownedValues();

        $this->actingAs($this->admin)
            ->patch('/settings/general', [
                'country' => 'AR',
            ])
            ->assertStatus(405);

        $this->assertSame($before, $this->ownedValues());
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
