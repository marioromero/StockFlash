<?php

namespace Tests\Feature\Install;

use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

class InstallGuardRegressionTest extends InstallTestCase
{
    public function test_exenciones_del_guard_son_por_nombre_exacto_sin_comodin(): void
    {
        $middleware = (string) file_get_contents(base_path('app/Http/Middleware/EnsureSetupCompleted.php'));

        foreach ([
            'install.show',
            'install.bootstrap',
            'install.preflight',
            'install.connection',
            'install.run',
        ] as $name) {
            $this->assertStringContainsString("'{$name}'", $middleware);
        }

        $this->assertStringNotContainsString("'install.'", $middleware);
        $this->assertStringNotContainsString("'install*'", $middleware);
        $this->assertStringNotContainsString("str_starts_with", $middleware);
    }

    public function test_install_funciona_sin_esquema_y_las_rutas_privadas_siguen_protegidas(): void
    {
        Schema::drop('system_configs');

        $this->get('/install')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Install/Wizard')
                ->where('authorized', false)
        );

        $this->get('/dashboard')->assertStatus(503);
        $this->get('/roadmap')->assertStatus(503);
        $this->get('/docs/')->assertOk();

        $login = $this->post('/login', [
            'email' => 'nadie@prueba.test',
            'password' => 'ContrasenaSegura123!',
        ]);
        $this->assertNotSame(503, $login->getStatusCode());
    }

    public function test_roadmap_sigue_exigiendo_autenticacion_cuando_el_esquema_esta_listo(): void
    {
        $this->markSetupCompleted();

        $this->get('/roadmap')->assertRedirect(route('login'));
    }

    public function test_rutas_del_instalador_tienen_metodos_esperados_y_csrf(): void
    {
        $routes = app('router')->getRoutes();

        // El grupo web incluye la protección CSRF/request forgery del framework.
        $this->assertTrue(class_exists(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class));

        $show = $routes->getByName('install.show');
        $this->assertNotNull($show);
        $this->assertContains('GET', $show->methods());
        $this->assertNotContains('POST', $show->methods());

        foreach ([
            'install.bootstrap',
            'install.preflight',
            'install.connection',
            'install.run',
        ] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Ruta {$name} ausente.");
            $this->assertContains('POST', $route->methods());
            $this->assertNotContains('GET', $route->methods());

            $expanded = implode('|', $route->gatherMiddleware());
            $this->assertStringContainsString('web', $expanded);
            $this->assertStringContainsString('EnsureInstallerEnabled', $expanded);
        }

        $this->assertNull($routes->getByName('install.complete'));
    }

    public function test_setup_y_auth_no_fueron_alterados_por_el_instalador(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNotNull($routes->getByName('setup.show'));
        $this->assertNotNull($routes->getByName('setup.store'));
        $this->assertNotNull($routes->getByName('login'));
        $this->assertNotNull($routes->getByName('login.store'));
        $this->assertNotNull($routes->getByName('docs.index'));

        $this->get('/login')->assertOk();
        $this->get('/setup')->assertOk();
    }
}
