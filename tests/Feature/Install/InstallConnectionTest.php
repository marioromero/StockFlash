<?php

namespace Tests\Feature\Install;

use App\Support\Install\InstallService;
use App\Support\Install\PdoInstallConnectionTester;

class InstallConnectionTest extends InstallTestCase
{
    public function test_conexion_exitosa_se_registra_sin_password(): void
    {
        $token = $this->getFlowToken();

        $response = $this->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(),
        ));

        $response->assertRedirect(route('install.show'));
        $this->assertSame(1, $this->connection->calls);

        $stored = session(InstallService::SESSION_CONNECTION);
        $this->assertIsArray($stored);
        $this->assertTrue($stored['ok']);
        $this->assertSame('db.prueba.test:3306/stockflash_prueba', $stored['label']);
        $this->assertArrayNotHasKey('db_password', $stored);
        $this->assertArrayNotHasKey('password', $stored);

        $session = $this->sessionJson();
        $this->assertStringNotContainsString('Clave-Ficticia-123!', $session);
        $this->assertStringNotContainsString('db_password', $session);
    }

    public function test_conexion_fallida_no_instala_y_reporta_error(): void
    {
        $this->connection->fail('No fue posible conectar con los datos indicados.');
        $token = $this->getFlowToken();

        $response = $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(),
        ));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('db');

        $stored = session(InstallService::SESSION_CONNECTION);
        $this->assertIsArray($stored);
        $this->assertFalse($stored['ok']);
        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
    }

    public function test_host_invalido_es_rechazado_sin_probar_conexion(): void
    {
        $token = $this->getFlowToken();

        $response = $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(['host' => 'db; DROP TABLE users']),
        ));

        $response->assertRedirect('/install');
        $response->assertSessionHasErrors('host');
        $this->assertSame(0, $this->connection->calls);
    }

    public function test_puerto_invalido_es_rechazado(): void
    {
        $token = $this->getFlowToken();

        foreach ([0, 65536, 'abc'] as $port) {
            $this->from('/install')->post('/install/connection', array_merge(
                ['install_token' => $token],
                $this->validDatabasePayload(['port' => $port]),
            ))->assertSessionHasErrors('port');
        }

        $this->assertSame(0, $this->connection->calls);
    }

    public function test_nombre_de_base_de_datos_y_usuario_invalidos_son_rechazados(): void
    {
        $token = $this->getFlowToken();

        $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(['database' => 'base;drop']),
        ))->assertSessionHasErrors('database');

        $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(['username' => 'user name']),
        ))->assertSessionHasErrors('username');

        $this->assertSame(0, $this->connection->calls);
    }

    public function test_password_con_salto_de_linea_es_rechazado(): void
    {
        $token = $this->getFlowToken();

        $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(['db_password' => "linea1\nlinea2"]),
        ))->assertSessionHasErrors('db_password');

        $this->assertSame(0, $this->connection->calls);
    }

    public function test_driver_no_soportado_es_rechazado(): void
    {
        $token = $this->getFlowToken();

        $this->from('/install')->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(['driver' => 'sqlite']),
        ))->assertSessionHasErrors('driver');

        $this->assertSame(0, $this->connection->calls);
    }

    public function test_tester_real_rechaza_driver_no_soportado_sin_conectar(): void
    {
        $result = (new PdoInstallConnectionTester)->test(
            \App\Support\Install\InstallDatabaseConfig::fromArray($this->validDatabasePayload([
                'driver' => 'sqlite',
            ])),
        );

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('Clave-Ficticia-123!', $result['message']);
    }
}
