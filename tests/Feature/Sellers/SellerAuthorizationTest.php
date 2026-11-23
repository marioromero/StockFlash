<?php

namespace Tests\Feature\Sellers;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * RBAC global/por sucursal, gate de módulo, licencia y regresiones públicas.
 */
class SellerAuthorizationTest extends SellerTestCase
{
    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/sellers')->assertRedirect('/login');
        $this->get('/sellers/1')->assertRedirect('/login');
        $this->post('/sellers', [])->assertRedirect('/login');
        $this->patch('/sellers/1', [])->assertRedirect('/login');
        $this->post('/sellers/1/activate')->assertRedirect('/login');
        $this->post('/sellers/1/deactivate')->assertRedirect('/login');
    }

    public function test_sin_permiso_todas_las_rutas_son_403(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'AUTH-1']);

        $user = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([])->id,
        ]);

        $this->actingAs($user)->get('/sellers')->assertForbidden();
        $this->actingAs($user)->get("/sellers/{$seller->id}")->assertForbidden();
        $this->actingAs($user)->post('/sellers', $this->sellerPayload(['branches' => [$branch->id]]))->assertForbidden();
        $this->actingAs($user)->patch("/sellers/{$seller->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->post("/sellers/{$seller->id}/activate")->assertForbidden();
        $this->actingAs($user)->post("/sellers/{$seller->id}/deactivate")->assertForbidden();
    }

    public function test_sellers_view_reports_solo_no_concede_administracion(): void
    {
        $user = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['sellers.view_reports'])->id,
        ]);

        $this->actingAs($user)->get('/sellers')->assertForbidden();
        $this->actingAs($user)
            ->post('/sellers', $this->sellerPayload(['branches' => [$this->makeBranch()->id]]))
            ->assertForbidden();
    }

    public function test_permiso_en_cualquier_sucursal_habilita_rutas_globales(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        $readerRole = $this->makeRoleWithPermissions(['sellers.view_reports']);
        $managerRole = $this->makeRoleWithPermissions(['sellers.manage']);

        $user = $this->makeBranchUser($branchA, $readerRole);
        $this->assignBranch($user, $branchB, $managerRole);

        $this->actingAs($user)->get('/sellers')->assertOk();

        $seller = $this->createSeller($user, [$branchB->id], ['code' => 'NONDEFAULT-1']);

        $this->assertSame($branchB->id, (int) DB::table('branch_seller')
            ->where('seller_id', $seller->id)
            ->value('branch_id'));
    }

    public function test_permiso_en_sucursal_no_predeterminada_sin_permiso_en_la_predeterminada(): void
    {
        $default = $this->makeBranch();
        $other = $this->makeBranch();
        $managerRole = $this->makeRoleWithPermissions(['sellers.manage']);

        $user = $this->makeBranchUser($default, $this->makeRoleWithPermissions([]));
        $this->assignBranch($user, $other, $managerRole);

        $this->actingAs($user)->get('/sellers')->assertOk();

        $seller = $this->createSeller($user, [$other->id], ['code' => 'OTHER-1']);
        $this->assertSame($other->id, (int) DB::table('branch_seller')
            ->where('seller_id', $seller->id)
            ->value('branch_id'));
    }

    public function test_modulo_deshabilitado_bloquea_todas_las_rutas(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'MOD-1']);
        $this->disableSellersModule();

        $this->actingAs($this->admin)->get('/sellers')->assertForbidden();
        $this->actingAs($this->admin)->get("/sellers/{$seller->id}")->assertForbidden();
        $this->actingAs($this->admin)->post('/sellers', $this->sellerPayload(['branches' => [$branch->id]]))->assertForbidden();
        $this->actingAs($this->admin)->patch("/sellers/{$seller->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->admin)->post("/sellers/{$seller->id}/activate")->assertForbidden();
        $this->actingAs($this->admin)->post("/sellers/{$seller->id}/deactivate")->assertForbidden();
    }

    public function test_fila_explicita_sellers_manda_sobre_pos_y_sin_fila_usa_pos(): void
    {
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'sellers'],
            ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($this->admin)->get('/sellers')->assertForbidden();

        $this->usePosFallbackWithoutSellersRow();
        $this->actingAs($this->admin)->get('/sellers')->assertOk();

        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'pos'],
            ['is_enabled' => 0, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($this->admin)->get('/sellers')->assertForbidden();
    }

    public function test_licencia_solo_lectura_permite_consultar_pero_no_escribir(): void
    {
        $branch = $this->makeBranch();
        $seller = $this->createSeller($this->admin, [$branch->id], ['code' => 'RO-1']);
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get('/sellers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->get("/sellers/{$seller->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->post('/sellers', $this->sellerPayload([
                'code' => 'RO-NEW',
                'branches' => [$branch->id],
            ]))
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->patch("/sellers/{$seller->id}", [
                'name' => 'No debe cambiar',
                'branches' => [$branch->id],
            ])
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post("/sellers/{$seller->id}/deactivate")
            ->assertSessionHasErrors('read_only');

        $this->assertDatabaseMissing('sellers', ['code' => 'RO-NEW']);
        $this->assertTrue($seller->refresh()->is_active);
    }

    public function test_identificadores_no_numericos_o_inexistentes_son_404(): void
    {
        $this->actingAs($this->admin)->get('/sellers/abc')->assertNotFound();
        $this->actingAs($this->admin)->get('/sellers/999999')->assertNotFound();
        $this->actingAs($this->admin)->patch('/sellers/abc', [])->assertNotFound();
        $this->actingAs($this->admin)->post('/sellers/999999/deactivate')->assertNotFound();
    }

    public function test_enlace_dashboard_solo_con_modulo_y_permiso(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_sellers', true));

        $viewer = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['sellers.view_reports'])->id,
        ]);

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_sellers', false));

        $this->disableSellersModule();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_sellers', false));
    }

    public function test_rutas_publicas_documentacion_roadmap_y_setup_siguen_disponibles(): void
    {
        $this->get('/docs/')->assertOk();
        $roadmap = $this->get('/roadmap')->assertOk();
        $this->assertStringContainsString('no-store', (string) $roadmap->headers->get('Cache-Control'));

        $setup = $this->get('/setup');
        $this->assertContains($setup->getStatusCode(), [200, 302]);
    }
}
