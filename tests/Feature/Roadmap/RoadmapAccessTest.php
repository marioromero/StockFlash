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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/roadmap')->assertRedirect('/login');
    }

    public function test_authenticated_user_without_settings_manage_gets_403(): void
    {
        $cashier = $this->makeUser(['global_role_id' => $this->cashierRole->id]);

        $this->actingAs($cashier)->get('/roadmap')->assertForbidden();
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

    public function test_dashboard_has_roadmap_button_for_authorized_users_only(): void
    {
        $dashboard = (string) file_get_contents(resource_path('js/Pages/Dashboard.vue'));

        $this->assertStringContainsString('v-if="canViewRoadmap"', $dashboard);
        $this->assertStringContainsString('href="/roadmap"', $dashboard);
        $this->assertStringContainsString('Avance del proyecto', $dashboard);
        $this->assertStringContainsString('rel="noopener noreferrer"', $dashboard);
        $this->assertStringContainsString('aria-label="Abrir el avance del proyecto en una nueva pestaña"', $dashboard);
    }

    public function test_roadmap_route_is_protected_and_not_served_from_public(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('roadmap.index', $routes);
        $this->assertStringContainsString('can.permission:settings.manage', $routes);
        $this->assertStringContainsString("resource_path('roadmap/index.html')", $routes);
        $this->assertStringNotContainsString("public_path('roadmap", $routes);
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
