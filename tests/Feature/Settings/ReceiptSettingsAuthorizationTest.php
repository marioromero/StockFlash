<?php

namespace Tests\Feature\Settings;

use App\Models\BranchUser;
use Illuminate\Support\Facades\Route;

class ReceiptSettingsAuthorizationTest extends ReceiptSettingsTestCase
{
    public function test_usuario_global_sin_settings_manage_recibe_403_en_lectura_y_escritura(): void
    {
        $sinPermiso = $this->makeUser([
            'global_role_id' => $this->role('cashier')->id,
        ]);

        $this->actingAs($sinPermiso)
            ->get('/settings/receipt')
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->put('/settings/receipt', $this->validPayload())
            ->assertForbidden();

        $this->assertSame('80mm', $this->configValue('receipt_paper'));
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
            ->get('/settings/receipt')
            ->assertForbidden();

        $this->actingAs($usuario)
            ->put('/settings/receipt', $this->validPayload())
            ->assertForbidden();

        $this->assertSame('80mm', $this->configValue('receipt_paper'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $this->get('/settings/receipt')->assertRedirect('/login');
        $this->put('/settings/receipt', $this->validPayload())->assertRedirect('/login');

        $this->assertSame('80mm', $this->configValue('receipt_paper'));
    }

    public function test_rutas_exigen_permiso_y_middleware_del_grupo_protegido(): void
    {
        $edit = Route::getRoutes()->getByName('settings.receipt.edit');
        $update = Route::getRoutes()->getByName('settings.receipt.update');

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
}
