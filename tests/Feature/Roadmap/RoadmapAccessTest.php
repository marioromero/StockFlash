<?php

namespace Tests\Feature\Roadmap;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoadmapAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRole = Role::query()->where('code', 'central_admin')->firstOrFail();
        $this->cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();
    }

    public function test_guest_can_read_roadmap_without_login(): void
    {
        $this->get('/roadmap')
            ->assertOk()
            ->assertSee('Avance del proyecto | StockFlash', false)
            ->assertSee('Catálogo detallado de funcionalidades', false);
    }

    public function test_authenticated_user_without_settings_manage_can_read_public_roadmap(): void
    {
        $cashier = $this->makeUser(['global_role_id' => $this->cashierRole->id]);

        $this->actingAs($cashier)->get('/roadmap')
            ->assertOk()
            ->assertSee('Avance del proyecto | StockFlash', false);
    }

    public function test_admin_with_settings_manage_can_view_the_roadmap(): void
    {
        $admin = $this->makeUser(['global_role_id' => $this->adminRole->id]);

        $response = $this->actingAs($admin)->get('/roadmap');

        $response->assertOk();
        $this->assertStringContainsString(
            'text/html',
            (string) $response->headers->get('content-type')
        );
        $response->assertSee('Avance del proyecto | StockFlash', false);
        $response->assertSee('id="expand"', false);
        $response->assertSee('id="collapse"', false);
        $response->assertSee('details class="step"', false);

        foreach (range(0, 10) as $stage) {
            $response->assertSee('R'.$stage, false);
        }

        $response->assertSee('R7', false);
        $response->assertSee('Primera versión vendible', false);
    }

    public function test_roadmap_html_is_not_publicly_served(): void
    {
        $this->assertFileExists(resource_path('roadmap/index.html'));
        $this->assertFileDoesNotExist(public_path('roadmap/index.html'));
    }

    public function test_roadmap_html_is_self_contained_accessible_and_responsive(): void
    {
        $html = (string) file_get_contents(resource_path('roadmap/index.html'));

        $this->assertStringContainsString('lang="es-CL"', $html);
        $this->assertStringContainsString('Avance del proyecto | StockFlash', $html);
        $this->assertStringContainsString('details class="step"', $html);
        $this->assertStringContainsString('id="feature-catalog"', $html);
        $this->assertStringContainsString('details class="module-details"', $html);
        $this->assertStringContainsString('id="feature-status-filter"', $html);
        $this->assertStringContainsString('id="feature-search"', $html);
        $this->assertStringContainsString('id="expand"', $html);
        $this->assertStringContainsString('id="collapse"', $html);
        $this->assertStringContainsString('@media(max-width:640px)', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);

        foreach (range(0, 10) as $stage) {
            $this->assertStringContainsString('R'.$stage, $html);
        }

        $this->assertStringNotContainsString('<script src=', $html);
        $this->assertStringNotContainsString('fetch(', $html);
        $this->assertStringNotContainsString('XMLHttpRequest', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
        $this->assertStringNotContainsString('type="password"', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_dashboard_has_public_roadmap_button_for_all_authenticated_users(): void
    {
        $dashboard = (string) file_get_contents(resource_path('js/Pages/Dashboard.vue'));

        $this->assertStringNotContainsString('v-if="canViewRoadmap"', $dashboard);
        $this->assertStringContainsString('href="/roadmap"', $dashboard);
        $this->assertStringContainsString('Avance del proyecto', $dashboard);
        $this->assertStringContainsString('rel="noopener noreferrer"', $dashboard);
        $this->assertStringContainsString('aria-label="Abrir el avance del proyecto en una nueva pestaña"', $dashboard);
    }

    public function test_home_page_links_to_public_roadmap_and_manual(): void
    {
        $this->get('/')->assertOk();
        $home = (string) file_get_contents(resource_path('js/Pages/Home.vue'));
        $this->assertStringContainsString('href="/roadmap"', $home);
        $this->assertStringContainsString('href="/docs/"', $home);
    }

    public function test_roadmap_route_is_public_and_setup_remains_guarded(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('roadmap.index', $routes);
        $this->assertStringContainsString("resource_path('roadmap/index.html')", $routes);
        $this->assertStringNotContainsString("public_path('roadmap", $routes);

        $publicRoute = strpos($routes, "Route::get('/roadmap'");
        $authenticatedGroup = strpos($routes, "Route::middleware(['auth', 'password.change', 'verify.session'])");
        $this->assertNotFalse($publicRoute);
        $this->assertNotFalse($authenticatedGroup);
        $this->assertLessThan($authenticatedGroup, $publicRoute);

        $this->assertStringContainsString("Route::get('/setup'", $routes);
        $this->assertStringContainsString('isPristine()', (string) file_get_contents(app_path('Http/Controllers/SetupController.php')));
    }

    public function test_unavailable_setup_page_links_to_public_guide_and_roadmap(): void
    {
        $view = (string) file_get_contents(resource_path('js/Pages/Setup/Unavailable.vue'));
        $this->assertStringContainsString('href="/roadmap"', $view);
        $this->assertStringContainsString('href="/docs/"', $view);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create(array_merge([
            'name' => 'Usuario Roadmap',
            'email' => "roadmap-{$suffix}@prueba.cl",
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => true,
        ], $overrides));
    }
}
