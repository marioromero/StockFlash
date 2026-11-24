<?php

namespace Tests\Feature\PosStations;

use App\Models\PosStation;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * RBAC global/por sucursal, alcance, módulo POS, licencia y regresiones.
 */
class PosStationAccessTest extends PosStationTestCase
{
    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/settings/pos-stations')->assertRedirect('/login');
        $this->post('/settings/pos-stations', [])->assertRedirect('/login');
        $this->get('/settings/pos-stations/1')->assertRedirect('/login');
        $this->patch('/settings/pos-stations/1', [])->assertRedirect('/login');
        $this->post('/settings/pos-stations/1/activate')->assertRedirect('/login');
        $this->post('/settings/pos-stations/1/deactivate')->assertRedirect('/login');
        $this->post('/settings/pos-stations/1/bind')->assertRedirect('/login');
        $this->post('/settings/pos-stations/1/revoke')->assertRedirect('/login');
    }

    public function test_sin_settings_manage_todas_las_rutas_son_403(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'AUTH-POS']);

        $user = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([])->id,
        ]);

        $this->actingAs($user)->get('/settings/pos-stations')->assertForbidden();
        $this->actingAs($user)->get("/settings/pos-stations/{$station->id}")->assertForbidden();
        $this->actingAs($user)->post('/settings/pos-stations', $this->stationPayload(['branch_id' => $branch->id]))->assertForbidden();
        $this->actingAs($user)->patch("/settings/pos-stations/{$station->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->post("/settings/pos-stations/{$station->id}/activate")->assertForbidden();
        $this->actingAs($user)->post("/settings/pos-stations/{$station->id}/deactivate")->assertForbidden();
        $this->actingAs($user)->post("/settings/pos-stations/{$station->id}/bind")->assertForbidden();
        $this->actingAs($user)->post("/settings/pos-stations/{$station->id}/revoke")->assertForbidden();
    }

    public function test_permiso_en_cualquier_sucursal_habilita_rutas_globales_y_limita_alcance(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();

        $stationA = $this->insertStation($branchA->id, ['code' => 'SCOPE-A']);
        $stationB = $this->insertStation($branchB->id, ['code' => 'SCOPE-B']);

        $manager = $this->makeBranchUser($branchB, $this->makeRoleWithPermissions(['settings.manage']));

        $this->actingAs($manager)
            ->get('/settings/pos-stations')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($stationA, $stationB, $branchB): void {
                $props = $page->toArray()['props'];

                $this->assertSame(1, $props['stations']['total']);
                $this->assertSame($stationB->id, $props['stations']['data'][0]['id']);
                $this->assertNotContains($stationA->id, array_column($props['stations']['data'], 'id'));
                $this->assertSame([$branchB->id], array_column($props['branch_options'], 'id'));
            });

        $this->actingAs($manager)->get("/settings/pos-stations/{$stationB->id}")->assertOk();
        $this->actingAs($manager)->get("/settings/pos-stations/{$stationA->id}")->assertNotFound();

        $this->actingAs($manager)
            ->get("/settings/pos-stations?branch_id={$branchB->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stations.total', 1));

        $this->actingAs($manager)
            ->get("/settings/pos-stations?branch_id={$branchA->id}")
            ->assertSessionHasErrors('branch_id');
    }

    public function test_permiso_solo_en_sucursal_inactiva_no_habilita_rutas(): void
    {
        $inactive = $this->makeBranch(['is_active' => false]);
        $manager = $this->makeBranchUser($inactive, $this->makeRoleWithPermissions(['settings.manage']));

        $this->actingAs($manager)->get('/settings/pos-stations')->assertForbidden();
        $this->actingAs($manager)
            ->post('/settings/pos-stations', $this->stationPayload(['branch_id' => $inactive->id]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_pos_stations', false));
    }

    public function test_idor_sobre_otra_sucursal_es_404_en_todas_las_mutaciones(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $manager = $this->makeBranchUser($branchA, $this->makeRoleWithPermissions(['settings.manage']));
        $stationB = $this->insertStation($branchB->id, ['code' => 'IDOR-POS']);

        $this->actingAs($manager)
            ->get("/settings/pos-stations/{$stationB->id}")
            ->assertNotFound();
        $this->actingAs($manager)
            ->patch("/settings/pos-stations/{$stationB->id}", ['name' => 'X'])
            ->assertNotFound();
        $this->actingAs($manager)
            ->post("/settings/pos-stations/{$stationB->id}/activate")
            ->assertNotFound();
        $this->actingAs($manager)
            ->post("/settings/pos-stations/{$stationB->id}/deactivate")
            ->assertNotFound();
        $this->actingAs($manager)
            ->post("/settings/pos-stations/{$stationB->id}/bind")
            ->assertNotFound();
        $this->actingAs($manager)
            ->post("/settings/pos-stations/{$stationB->id}/revoke")
            ->assertNotFound();

        $stationB->refresh();
        $this->assertTrue($stationB->is_active);
        $this->assertNull($stationB->getAttribute('device_token_hash'));
    }

    public function test_modulo_pos_deshabilitado_o_ausente_bloquea_todas_las_rutas(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'MOD-POS']);

        $this->disablePosModule();

        $this->actingAs($this->admin)->get('/settings/pos-stations')->assertForbidden();
        $this->actingAs($this->admin)->get("/settings/pos-stations/{$station->id}")->assertForbidden();
        $this->actingAs($this->admin)->post('/settings/pos-stations', $this->stationPayload(['branch_id' => $branch->id]))->assertForbidden();
        $this->actingAs($this->admin)->patch("/settings/pos-stations/{$station->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/deactivate")->assertForbidden();
        $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind")->assertForbidden();

        $this->removePosModuleRow();

        $this->actingAs($this->admin)->get('/settings/pos-stations')->assertForbidden();
        $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind")->assertForbidden();
    }

    public function test_modulo_pos_no_es_sustituido_por_otra_fila(): void
    {
        $branch = $this->makeBranch();
        $this->insertStation($branch->id, ['code' => 'NO-FALLBACK']);

        DB::table('installation_modules')->where('module_code', 'pos')->delete();
        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'sellers'],
            ['is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($this->admin)->get('/settings/pos-stations')->assertForbidden();
    }

    public function test_licencia_solo_lectura_permite_consultar_pero_no_escribir(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'RO-POS']);
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get('/settings/pos-stations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->get("/settings/pos-stations/{$station->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->post('/settings/pos-stations', $this->stationPayload([
                'code' => 'RO-NEW-POS',
                'branch_id' => $branch->id,
            ]))
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", ['name' => 'No debe cambiar'])
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/deactivate")
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/bind")
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/revoke")
            ->assertSessionHasErrors('read_only');

        $this->assertDatabaseMissing('pos_stations', ['code' => 'RO-NEW-POS']);
        $this->assertTrue($station->refresh()->is_active);
        $this->assertNull($station->getAttribute('device_token_hash'));
    }

    public function test_suspension_dentro_de_gracia_no_bloquea_escrituras(): void
    {
        $branch = $this->makeBranch();
        $this->setLicense('suspended', now()->addDays(2)->toDateString());

        $station = $this->createStation($this->admin, ['code' => 'GRACE-POS', 'branch_id' => $branch->id]);

        $this->assertSame('GRACE-POS', $station->code);
    }

    public function test_identificadores_no_numericos_o_inexistentes_son_404(): void
    {
        $this->actingAs($this->admin)->get('/settings/pos-stations/abc')->assertNotFound();
        $this->actingAs($this->admin)->get('/settings/pos-stations/999999')->assertNotFound();
        $this->actingAs($this->admin)->patch('/settings/pos-stations/abc', [])->assertNotFound();
        $this->actingAs($this->admin)->post('/settings/pos-stations/999999/deactivate')->assertNotFound();
        $this->actingAs($this->admin)->post('/settings/pos-stations/999999/bind')->assertNotFound();
    }

    public function test_listado_paginado_acotado_y_busqueda(): void
    {
        $branch = $this->makeBranch();

        for ($index = 1; $index <= 30; $index++) {
            $this->insertStation($branch->id, [
                'code' => sprintf('PAG-%02d', $index),
                'name' => sprintf('Estación %02d', $index),
            ]);
        }

        $this->insertStation($branch->id, ['code' => 'ANA-POS', 'name' => 'Ana Pérez']);
        $this->insertStation($branch->id, ['code' => 'PCT-100', 'name' => 'Porcentaje 100%']);
        $this->insertStation($branch->id, ['code' => 'PCT-100X', 'name' => 'Porcentaje 100X']);

        $this->actingAs($this->admin)
            ->get('/settings/pos-stations')
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $props = $page->toArray()['props'];
                $this->assertSame(33, $props['stations']['total']);
                $this->assertSame(25, $props['stations']['per_page']);
                $this->assertCount(25, $props['stations']['data']);
            });

        $this->actingAs($this->admin)
            ->get('/settings/pos-stations?per_page=500')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stations.per_page', 100));

        $this->actingAs($this->admin)
            ->get('/settings/pos-stations?q=Ana')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stations.total', 1)
                ->where('stations.data.0.name', 'Ana Pérez'));

        $this->actingAs($this->admin)
            ->get('/settings/pos-stations?q=pct-100')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stations.total', 2));

        $this->actingAs($this->admin)
            ->get('/settings/pos-stations?q=%25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stations.total', 1)
                ->where('stations.data.0.name', 'Porcentaje 100%'));
    }

    public function test_get_no_escribe_ni_emite_token(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'READONLY-GET']);
        $updatedAt = DB::table('pos_stations')->where('id', $station->id)->value('updated_at');
        $audits = DB::table('audit_logs')->count();

        $this->actingAs($this->admin)->get('/settings/pos-stations')->assertOk();
        $this->actingAs($this->admin)->get("/settings/pos-stations/{$station->id}")->assertOk();

        $station->refresh();
        $this->assertNull($station->getAttribute('device_token_hash'));
        $this->assertNull($station->last_seen_at);
        $this->assertSame(
            (string) $updatedAt,
            (string) DB::table('pos_stations')->where('id', $station->id)->value('updated_at'),
        );
        $this->assertSame($audits, DB::table('audit_logs')->count());
        $this->assertSame(1, PosStation::query()->count());
    }

    public function test_listado_y_detalle_no_exponen_hash_ni_token(): void
    {
        $branch = $this->makeBranch();
        $hash = hash('sha256', 'secreto-que-no-debe-salir');
        $station = $this->insertStation($branch->id, ['code' => 'HIDE-POS'], $hash);

        $this->assertArrayNotHasKey('device_token_hash', $station->toArray());
        $this->assertStringNotContainsString($hash, (string) json_encode($station->toArray()));

        $index = $this->actingAs($this->admin)->get('/settings/pos-stations')->assertOk();
        $index->assertDontSee('secreto-que-no-debe-salir', false);
        $index->assertDontSee($hash, false);

        $this->actingAs($this->admin)
            ->get("/settings/pos-stations/{$station->id}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($hash): void {
                $props = $page->toArray()['props'];
                $this->assertTrue($props['station']['has_device']);
                $this->assertArrayNotHasKey('device_token_hash', $props['station']);
                $this->assertStringNotContainsString($hash, json_encode($props));
            });
    }

    public function test_enlace_dashboard_solo_con_modulo_y_permiso(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_pos_stations', true));

        $viewer = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([])->id,
        ]);

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_pos_stations', false));

        $this->disablePosModule();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_pos_stations', false));
    }

    public function test_rutas_publicas_documentacion_roadmap_y_setup_siguen_disponibles(): void
    {
        $this->get('/docs/')->assertOk();
        $roadmap = $this->get('/roadmap')->assertOk();
        $this->assertStringContainsString('no-store', (string) $roadmap->headers->get('Cache-Control'));

        $setup = $this->get('/setup');
        $this->assertContains($setup->getStatusCode(), [200, 302]);
    }

    public function test_sesion_y_csrf_pertenecen_al_grupo_web(): void
    {
        $this->get('/settings/pos-stations')->assertRedirect('/login');

        $route = app('router')->getRoutes()->getByName('settings.pos-stations.store');
        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
    }
}
