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
            ->assertSee('id="traceability"', false)
            ->assertSee('id="overall-progress"', false);
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
        $response->assertSee('class="stats"', false);
        $response->assertSee('details class="stage', false);
        $response->assertSee('details class="module"', false);
        $response->assertSee('details class="req"', false);

        foreach (range(3, 10) as $stage) {
            $response->assertSee('id="r'.$stage.'"', false);
        }

        $response->assertDontSee('Catálogo detallado de funcionalidades', false);
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
        $this->assertStringContainsString('id="overall-progress"', $html);
        $this->assertStringContainsString('id="traceability"', $html);
        $this->assertStringContainsString('class="stats"', $html);
        $this->assertStringContainsString('details class="stage', $html);
        $this->assertStringContainsString('details class="module"', $html);
        $this->assertStringContainsString('details class="req"', $html);
        $this->assertStringContainsString('@media(max-width:640px)', $html);
        $this->assertStringNotContainsString('id="feature-catalog"', $html);
        $this->assertStringNotContainsString('id="feature-search"', $html);
        $this->assertStringNotContainsString('class="metric"', $html);
        $this->assertStringNotContainsString('class="block"', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);

        foreach (range(3, 10) as $stage) {
            $this->assertStringContainsString('id="r'.$stage.'"', $html);
        }

        preg_match_all('/data-code="([A-Z]+-[0-9]+)"/', $html, $ids);
        preg_match_all('/class="req" data-code="[A-Z]+-[0-9]+" data-state="done"/', $html, $done);
        $this->assertSame(265, count($ids[1]));
        $this->assertSame(count($ids[1]), count(array_unique($ids[1])));
        $this->assertSame(19, count($done[0]));
        $this->assertStringContainsString('aria-valuenow="19" aria-valuemin="0" aria-valuemax="169"', $html);

        // Default to R7 commercial MVP: approved P0/P1/P1★ through R7,
        // except channel/online-only and integrated-fiscal-only requirements.
        $this->assertStringContainsString('id="progress-scope"', $html);
        $this->assertStringContainsString('<option value="r7" selected>', $html);
        $this->assertStringContainsString('<option value="all">', $html);
        $this->assertStringContainsString('id="scope-percentage">11,2</span>', $html);
        $this->assertStringContainsString('id="scope-total">169</span>', $html);
        $this->assertStringContainsString('id="scope-p0-total">104</span>', $html);
        $this->assertStringContainsString("selector.addEventListener('change', render)", $html);

        preg_match_all('/class="req" data-code="([A-Z]+-[0-9]+)" data-state="([^"]+)" data-priority="([^"]+)" data-r7="([01])"/', $html, $scoped, PREG_SET_ORDER);
        $this->assertCount(265, $scoped);
        $r7 = array_values(array_filter($scoped, fn (array $match): bool => $match[4] === '1'));
        $this->assertCount(169, $r7);
        $this->assertCount(19, array_filter($r7, fn (array $match): bool => $match[2] === 'done'));
        $this->assertCount(104, array_filter($r7, fn (array $match): bool => $match[3] === 'P0'));
        foreach (['INV-19', 'INV-20', 'SAL-16', 'SAL-20', 'SAL-21', 'SAL-22', 'SEC-18', 'BKP-09', 'BKP-10', 'BKP-11'] as $excluded) {
            $matches = array_values(array_filter($scoped, fn (array $match): bool => $match[1] === $excluded));
            $this->assertCount(1, $matches);
            $this->assertSame('0', $matches[0][4]);
        }
        // Both modes are read-only calculations. No Ajax, data mutation or remote calls.


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
