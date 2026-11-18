<?php

namespace Tests\Feature\Install;

use App\Support\Install\InstallService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class InstallRunTest extends InstallTestCase
{
    public function test_instalacion_exitosa_registra_una_sola_fila_y_muestra_cron(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->post('/install/run', $this->validRunPayload($token));

        $response->assertRedirect(route('setup.show'));

        $this->assertSame(1, $this->migrator->migrateCalls);
        $this->assertSame(1, $this->migrator->seedCalls);
        $this->assertSame(1, $this->configurator->calls);
        $this->assertSame(1, $this->secretWriter->calls);
        $this->assertSame(1, $this->installationInfoCount());
        $this->assertSame(0, $this->usersCount());
        $this->assertSame(0, $this->branchesCount());

        $row = DB::table('installation_info')->first();
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $row->installation_id,
        );
        $this->assertSame('9.9.9', $row->app_version);
        $this->assertSame($this->migrator->schemaVersion, $row->schema_version);
        $this->assertSame('stable', $row->release_channel);
        $this->assertNotEmpty($row->installed_at);

        $secrets = $this->secretWriter->secrets;
        $this->assertIsArray($secrets);
        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $secrets['app_key']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secrets['blind_index_key']);
        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $secrets['backup_encryption_key']);
        $this->assertNotSame($secrets['app_key'], $secrets['backup_encryption_key']);

        // El flujo se limpia y los secretos no quedan en sesión.
        $this->assertFalse(session()->has(InstallService::SESSION_FLOW));
        $session = $this->sessionJson();
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $session);
        }
        $this->assertStringNotContainsString('Clave-Ficticia-123!', $session);

        // M-02: la línea de cron se muestra en el primer render de /setup.
        $this->get('/setup')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Setup/Wizard')
                ->where('install_notice.completed', true)
                ->where('install_notice.cron_command', 'php artisan schedule:run')
                ->where('install_notice.app_version', '9.9.9')
                ->where('install_notice.release_channel', 'stable')
        );

        foreach ($secrets as $secret) {
            $this->get('/setup')->assertDontSee($secret, false);
        }
    }

    public function test_sin_confirmacion_explicita_no_instala(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token, [
            'confirm_destination' => false,
        ]));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('confirm_destination');
        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_frase_de_confirmacion_incorrecta_no_instala(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token, [
            'confirmation_phrase' => 'INSTALAR AHORA',
        ]));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('confirmation_phrase');
        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_sin_preflight_no_instala(): void
    {
        $token = $this->getFlowToken();

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('preflight');
        $this->assertSame(0, $this->migrator->migrateCalls);
    }

    public function test_sin_prueba_de_conexion_no_instala(): void
    {
        $token = $this->passPreflight();

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('db');
        $this->assertSame(0, $this->migrator->migrateCalls);
    }

    public function test_destino_cambiado_desde_la_prueba_no_instala(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token, [
            'host' => 'otro-destino.test',
        ]));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('db');
        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_token_de_flujo_invalido_responde_419(): void
    {
        $this->passPreflight();

        $this->post('/install/run', $this->validRunPayload('token-invalido'))->assertStatus(419);
        $this->assertSame(0, $this->migrator->migrateCalls);
    }

    public function test_fallo_del_migrador_no_marca_exito_ni_borra_datos(): void
    {
        DB::table('system_configs')->insert([
            'config_key' => 'install_marker',
            'config_value' => 'conservar',
            'value_type' => 'string',
            'is_secret' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrator->migrateException = 'fallo simulado de migración';
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('install');
        $this->assertSame(1, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->migrator->seedCalls);
        $this->assertSame(0, $this->installationInfoCount());
        $this->assertSame(0, $this->usersCount());
        $this->assertSame(0, $this->branchesCount());
        $this->assertSame('conservar', DB::table('system_configs')->where('config_key', 'install_marker')->value('config_value'));

        $session = $this->sessionJson();
        $this->assertStringNotContainsString('Clave-Ficticia-123!', $session);
        $this->assertStringNotContainsString('base64:', $session);
    }

    public function test_fallo_de_semillas_no_marca_exito_ni_inserta_installation_info(): void
    {
        $this->migrator->seedException = 'fallo simulado de semillas';
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('install');
        $this->assertSame(1, $this->migrator->migrateCalls);
        $this->assertSame(1, $this->migrator->seedCalls);
        $this->assertSame(0, $this->installationInfoCount());
        $this->assertSame(0, $this->usersCount());
        $this->assertSame(0, $this->branchesCount());
    }

    public function test_fallo_del_escritor_de_secretos_no_migra(): void
    {
        $this->secretWriter->exception = 'fallo simulado de escritura';
        $token = $this->passPreflight();
        $this->passConnection($token);

        $response = $this->from('/install')->post('/install/run', $this->validRunPayload($token));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('install');
        $this->assertSame(1, $this->secretWriter->calls);
        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_doble_envio_concurrente_es_bloqueado_por_lock(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $lock = Cache::store((string) config('install.cache.store'))->lock(InstallService::LOCK_KEY, 60);
        $this->assertTrue($lock->get());

        try {
            $this->post('/install/run', $this->validRunPayload($token))->assertStatus(423);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_segundo_intento_tras_instalar_es_bloqueado(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $this->post('/install/run', $this->validRunPayload($token))->assertRedirect(route('setup.show'));

        $this->get('/install')->assertStatus(423);
        $this->post('/install/run', $this->validRunPayload($token))->assertStatus(403);

        $this->assertSame(1, $this->installationInfoCount());
        $this->assertSame(1, $this->migrator->migrateCalls);
        $this->assertSame(1, $this->migrator->seedCalls);
    }
}
