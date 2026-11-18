<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallConnectionTester;
use PDO;
use Throwable;

/**
 * Prueba de conexión con PDO, sin shell ni interpolación de comandos.
 *
 * Los valores llegan validados por `InstallConnectionRequest`. El mensaje de
 * error es deliberadamente genérico para no exponer credenciales ni detalles
 * internos del servidor.
 */
final class PdoInstallConnectionTester implements InstallConnectionTester
{
    public function test(InstallDatabaseConfig $database): array
    {
        $driver = $database->driver === 'mariadb' ? 'mysql' : $database->driver;

        if (! in_array($driver, ['mysql'], true)) {
            return ['ok' => false, 'message' => 'El motor de base de datos seleccionado no está soportado.'];
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $driver,
            $database->host,
            $database->port,
            $database->database,
        );

        try {
            $pdo = new PDO($dsn, $database->username, $database->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $pdo->query('SELECT 1');

            return ['ok' => true, 'message' => 'Conexión verificada con el destino indicado.'];
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'No fue posible conectar con los datos indicados. Revise host, puerto, base de datos y credenciales.'];
        }
    }
}
