<?php

namespace Tests\Feature\Install;

use App\Support\Install\InstallService;
use Inertia\Testing\AssertableInertia as Assert;

class InstallBootstrapSecurityTest extends InstallTestCase
{
    public function test_instalador_deshabilitado_responde_404(): void
    {
        config(['install.enabled' => false]);

        $this->get('/install')->assertNotFound();
        $this->post('/install/bootstrap', ['bootstrap_token' => self::BOOTSTRAP_TOKEN])->assertNotFound();
        $this->post('/install/preflight', [])->assertNotFound();
        $this->post('/install/run', [])->assertNotFound();
    }

    public function test_instalador_sin_token_configurado_responde_404(): void
    {
        config(['install.enabled' => true, 'install.bootstrap_token' => null]);

        $this->get('/install')->assertNotFound();
        $this->post('/install/bootstrap', ['bootstrap_token' => 'x'])->assertNotFound();
    }

    public function test_token_corto_no_habilita_el_instalador(): void
    {
        config(['install.bootstrap_token' => 'corto']);

        $this->get('/install')->assertNotFound();
    }

    public function test_token_incorrecto_no_autoriza_y_no_se_conserva_en_old_input(): void
    {
        $response = $this->from('/install')->post('/install/bootstrap', [
            'bootstrap_token' => 'token-incorrecto-de-prueba',
        ]);

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('bootstrap_token');
        $this->assertNull(session()->getOldInput('bootstrap_token'));

        $this->get('/install')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Install/Wizard')
                ->where('authorized', false)
                ->missing('flow_token')
                ->missing('preflight')
                ->missing('connection')
        );
    }

    public function test_token_correcto_autoriza_y_emite_token_de_flujo(): void
    {
        $this->authorizeBootstrap();

        $response = $this->get('/install');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Install/Wizard')
            ->where('authorized', true)
            ->where('flow_token', session(InstallService::SESSION_FLOW))
            ->has('steps', 4)
            ->has('database_defaults')
            ->missing('secrets')
            ->missing('app_key')
        );
    }

    public function test_token_de_arranque_ausente_es_error_de_validacion(): void
    {
        $response = $this->from('/install')->post('/install/bootstrap', []);

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('bootstrap_token');
    }

    public function test_rate_limit_bloquea_intentos_repetidos(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/install/bootstrap', [
                'bootstrap_token' => 'token-incorrecto-'.$attempt,
            ])->assertRedirect();
        }

        $this->post('/install/bootstrap', [
            'bootstrap_token' => 'token-incorrecto-final',
        ])->assertStatus(429);
    }

    public function test_endpoints_de_pasos_exigen_autorizacion_bootstrap(): void
    {
        $this->post('/install/preflight', ['install_token' => 'x'])->assertStatus(403);
        $this->post('/install/connection', array_merge(
            ['install_token' => 'x'],
            $this->validDatabasePayload(),
        ))->assertStatus(403);
        $this->post('/install/run', $this->validRunPayload('x'))->assertStatus(403);
    }

    public function test_token_de_arranque_es_de_un_solo_uso_incluso_en_otra_sesion(): void
    {
        $this->authorizeBootstrap();

        // Simula otra sesión/visitante sin la autorización previa.
        $this->flushSession();

        $response = $this->from('/install')->post('/install/bootstrap', [
            'bootstrap_token' => self::BOOTSTRAP_TOKEN,
        ]);

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('bootstrap_token');

        $this->get('/install')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Install/Wizard')
                ->where('authorized', false)
        );
    }

    public function test_post_sin_token_csrf_devuelve_419_conductual(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $response = $this->withHeader('Sec-Fetch-Site', 'cross-site')->post('/install/bootstrap', [
                'bootstrap_token' => self::BOOTSTRAP_TOKEN,
            ]);

            $response->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_password_de_bd_no_queda_en_old_input_tras_error_de_validacion(): void
    {
        $token = $this->getFlowToken();

        $response = $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(['port' => 70000]),
        ));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('port');
        $this->assertNull(session()->getOldInput('db_password'));
    }
}
