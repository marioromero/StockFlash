<?php

namespace Tests\Feature\Catalog;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

class CatalogMastersAuthorizationTest extends CatalogMastersTestCase
{
    public function test_get_con_catalog_view_responde_200_sin_formularios_de_escritura(): void
    {
        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');

        $this->actingAs($viewer)
            ->get('/catalog/masters')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Masters')
                ->where('can_manage', false)
                ->has('tax_rates')
                ->has('categories')
                ->has('brands')
                ->has('tags')
                ->has('attributes')
            );
    }

    public function test_get_sin_catalog_view_recibe_403(): void
    {
        $branch = $this->makeBranch();
        $noPermission = $this->makeBranchUser($branch, $this->makeNoPermissionRole());

        $this->actingAs($noPermission)->get('/catalog/masters')->assertForbidden();
    }

    public function test_post_put_delete_sin_catalog_manage_reciben_403_y_no_escriben(): void
    {
        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');
        $tag = Tag::create(['name' => 'Intacta', 'slug' => 'intacta']);

        $this->actingAs($viewer)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'No autorizada']))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put("/catalog/masters/tags/{$tag->id}", $this->tagPayload(['name' => 'Tampoco']))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete("/catalog/masters/tags/{$tag->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Intacta']);
        $this->assertDatabaseMissing('tags', ['name' => 'No autorizada']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'catalog.tags.created']);
    }

    public function test_usuario_por_sucursal_con_view_y_manage_en_su_sucursal_opera(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeBranchUser($branch, 'supervisor');

        $this->actingAs($manager)->get('/catalog/masters')->assertOk();

        $this->actingAs($manager)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'Etiqueta autorizada']))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseHas('tags', ['name' => 'Etiqueta autorizada']);
    }

    public function test_branch_id_en_query_o_body_no_eleva_el_ambito_de_autorizacion(): void
    {
        $branchA = $this->makeBranch(['code' => 'CAT-A']);
        $branchB = $this->makeBranch(['code' => 'CAT-B']);
        $noPermissionRole = $this->makeNoPermissionRole();

        $user = $this->makeUser([
            'email' => 'manipulador@prueba.cl',
            'default_branch_id' => $branchA->id,
            'global_role_id' => null,
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $user->id, 'branch_id' => $branchA->id, 'role_id' => $noPermissionRole->id],
            ['user_id' => $user->id, 'branch_id' => $branchB->id, 'role_id' => $this->role('supervisor')->id],
        ]);

        $this->actingAs($user)
            ->get('/catalog/masters?branch_id='.$branchB->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/catalog/masters?branch_id[]='.$branchB->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/catalog/masters/tags', $this->tagPayload([
                'name' => 'No debe crearse',
                'branch_id' => $branchB->id,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/catalog/masters/tags', $this->tagPayload([
                'name' => 'Tampoco debe crearse',
                'branch_id' => [$branchB->id],
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('tags', ['name' => 'No debe crearse']);
        $this->assertDatabaseMissing('tags', ['name' => 'Tampoco debe crearse']);
    }

    public function test_modulo_habilitado_no_otorga_permiso_si_el_usuario_no_lo_tiene(): void
    {
        $branch = $this->makeBranch();
        $noPermission = $this->makeBranchUser($branch, $this->makeNoPermissionRole());

        DB::table('installation_modules')->updateOrInsert(
            ['module_code' => 'catalog'],
            [
                'is_enabled' => 1,
                'enabled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($noPermission)->get('/catalog/masters')->assertForbidden();
        $this->actingAs($noPermission)
            ->post('/catalog/masters/tags', $this->tagPayload())
            ->assertForbidden();
    }

    public function test_rutas_tienen_middlewares_de_permiso_y_estan_dentro_del_grupo_protegido(): void
    {
        $expectations = [
            'catalog.masters.index' => 'can.permission:catalog.view',
            'catalog.masters.tax-rates.store' => 'can.permission:catalog.manage',
            'catalog.masters.tax-rates.update' => 'can.permission:catalog.manage',
            'catalog.masters.tax-rates.destroy' => 'can.permission:catalog.manage',
            'catalog.masters.categories.store' => 'can.permission:catalog.manage',
            'catalog.masters.categories.update' => 'can.permission:catalog.manage',
            'catalog.masters.categories.destroy' => 'can.permission:catalog.manage',
            'catalog.masters.brands.store' => 'can.permission:catalog.manage',
            'catalog.masters.brands.update' => 'can.permission:catalog.manage',
            'catalog.masters.brands.destroy' => 'can.permission:catalog.manage',
            'catalog.masters.tags.store' => 'can.permission:catalog.manage',
            'catalog.masters.tags.update' => 'can.permission:catalog.manage',
            'catalog.masters.tags.destroy' => 'can.permission:catalog.manage',
            'catalog.masters.attributes.store' => 'can.permission:catalog.manage',
            'catalog.masters.attributes.update' => 'can.permission:catalog.manage',
            'catalog.masters.attributes.destroy' => 'can.permission:catalog.manage',
            'catalog.masters.attribute-values.store' => 'can.permission:catalog.manage',
            'catalog.masters.attribute-values.update' => 'can.permission:catalog.manage',
            'catalog.masters.attribute-values.destroy' => 'can.permission:catalog.manage',
        ];

        foreach ($expectations as $name => $permissionMiddleware) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Falta la ruta {$name}.");

            $middleware = $route->gatherMiddleware();

            $this->assertContains($permissionMiddleware, $middleware, "La ruta {$name} no exige {$permissionMiddleware}.");
            $this->assertContains('auth', $middleware, "La ruta {$name} no exige auth.");
            $this->assertContains('password.change', $middleware, "La ruta {$name} no exige password.change.");
            $this->assertContains('verify.session', $middleware, "La ruta {$name} no exige verify.session.");
            $this->assertNotContains('branch.access', $middleware, "La ruta {$name} no debe usar branch.access.");
        }
    }

    public function test_dashboard_expone_catalog_view_solo_a_quien_lo_tiene(): void
    {
        $branch = $this->makeBranch();
        $viewer = $this->makeBranchUser($branch, 'cashier');

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->contains('catalog.view'))
            );
    }

    public function test_visibilidad_del_enlace_usa_el_permiso_de_la_sucursal_por_defecto(): void
    {
        $default = $this->makeBranch(['code' => 'CAT-DEF']);
        $other = $this->makeBranch(['code' => 'CAT-OTHER']);

        // catalog.view SOLO en una sucursal distinta de la default: ni enlace ni acceso.
        $outside = $this->makeUser([
            'email' => 'view-otra-sucursal@prueba.cl',
            'default_branch_id' => $default->id,
            'global_role_id' => null,
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $outside->id, 'branch_id' => $default->id, 'role_id' => $this->makeNoPermissionRole()->id],
            ['user_id' => $outside->id, 'branch_id' => $other->id, 'role_id' => $this->role('cashier')->id],
        ]);

        $this->actingAs($outside)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions): bool => ! collect($permissions)->contains('catalog.view'))
            );

        $this->actingAs($outside)->get('/catalog/masters')->assertForbidden();

        // Mismo permiso en la sucursal default: enlace visible y acceso 200.
        $inside = $this->makeBranchUser($default, 'cashier');

        $this->actingAs($inside)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->contains('catalog.view'))
            );

        $this->actingAs($inside)->get('/catalog/masters')->assertOk();
    }

    public function test_catalog_manage_tambien_se_acota_a_la_sucursal_por_defecto(): void
    {
        $default = $this->makeBranch(['code' => 'CAT-M-DEF']);
        $other = $this->makeBranch(['code' => 'CAT-M-OTHER']);

        $outside = $this->makeUser([
            'email' => 'manage-otra-sucursal@prueba.cl',
            'default_branch_id' => $default->id,
            'global_role_id' => null,
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $outside->id, 'branch_id' => $default->id, 'role_id' => $this->makeNoPermissionRole()->id],
            ['user_id' => $outside->id, 'branch_id' => $other->id, 'role_id' => $this->role('supervisor')->id],
        ]);

        $this->actingAs($outside)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions): bool => ! collect($permissions)->contains('catalog.manage'))
            );

        $this->actingAs($outside)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'No autorizada fuera']))
            ->assertForbidden();

        $this->assertDatabaseMissing('tags', ['name' => 'No autorizada fuera']);

        // supervisor en la sucursal default: manage visible y escritura permitida.
        $manager = $this->makeBranchUser($default, 'supervisor');

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->contains('catalog.manage'))
            );

        $this->actingAs($manager)->get('/catalog/masters')->assertOk();
        $this->actingAs($manager)
            ->post('/catalog/masters/tags', $this->tagPayload(['name' => 'Autorizada default']))
            ->assertRedirect(route('catalog.masters.index'));

        $this->assertDatabaseHas('tags', ['name' => 'Autorizada default']);
    }
}
