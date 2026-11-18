<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallSecretWriter;
use RuntimeException;

/**
 * Escritura atómica del archivo de configuración local.
 *
 * - Escribe un temporal en el mismo directorio y lo renombra.
 * - Conserva las líneas existentes y reemplaza solo las claves administradas.
 * - Nunca registra ni devuelve los valores.
 * - Las pruebas inyectan un escritor falso o una ruta temporal.
 */
final class AtomicEnvSecretWriter implements InstallSecretWriter
{
    public function __construct(private ?string $path = null) {}

    public function persist(InstallDatabaseConfig $database, array $secrets): array
    {
        $path = $this->path ?? $this->configuredPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new RuntimeException('El directorio de configuración local no existe.');
        }

        $values = [
            'APP_KEY' => $secrets['app_key'],
            'BLIND_INDEX_KEY' => $secrets['blind_index_key'],
            'BACKUP_ENCRYPTION_KEY' => $secrets['backup_encryption_key'],
            'DB_CONNECTION' => $database->driver,
            'DB_HOST' => $database->host,
            'DB_PORT' => (string) $database->port,
            'DB_DATABASE' => $database->database,
            'DB_USERNAME' => $database->username,
            'DB_PASSWORD' => $database->password,
            // m-06: un DB_URL/DB_SOCKET heredado puede anular el destino
            // escrito; se neutralizan para que la BD elegida prevalezca.
            'DB_URL' => '',
            'DB_SOCKET' => '',
        ];

        foreach ($values as $value) {
            if (preg_match('/[\r\n\0]/', $value)) {
                throw new RuntimeException('Un valor de configuración contiene caracteres no permitidos.');
            }
        }

        $merged = $this->mergeLines($path, $values, [
            'APP_KEY',
            'BLIND_INDEX_KEY',
            'BACKUP_ENCRYPTION_KEY',
        ]);
        $contents = implode(PHP_EOL, $merged['lines']).PHP_EOL;

        $temporary = tempnam($directory, '.env.install.');

        if ($temporary === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de configuración.');
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new RuntimeException('No fue posible escribir el archivo temporal de configuración.');
            }

            @chmod($temporary, 0600);

            if (! @rename($temporary, $path)) {
                // En Windows el rename sobre un archivo existente puede fallar;
                // se reintenta sin dejar el temporal huérfano.
                @unlink($path);

                if (! @rename($temporary, $path)) {
                    throw new RuntimeException('No fue posible publicar el archivo de configuración.');
                }
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return [
            'preserved' => $merged['preserved'],
            'written' => $merged['written'],
        ];
    }

    /**
     * @param  array<string, string>  $values
     * @param  list<string>  $secretKeys
     * @return array{lines: list<string>, preserved: list<string>, written: list<string>}
     */
    private function mergeLines(string $path, array $values, array $secretKeys): array
    {
        $lines = [];

        if (is_file($path)) {
            $existing = file($path, FILE_IGNORE_NEW_LINES);

            if ($existing !== false) {
                $lines = $existing;
            }
        }

        $pending = $values;
        $preserved = [];
        $written = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];

            if (! array_key_exists($key, $pending)) {
                continue;
            }

            if (in_array($key, $secretKeys, true) && ! $this->hasEmptyValue($line)) {
                // M-03: conservar la clave existente byte a byte, sin revelar
                // su valor. Solo se completan claves ausentes o vacías.
                $preserved[] = $key;
                unset($pending[$key]);

                continue;
            }

            $lines[$index] = $key.'='.$this->quote($pending[$key]);
            $written[] = $key;
            unset($pending[$key]);
        }

        foreach ($pending as $key => $value) {
            $lines[] = $key.'='.$this->quote($value);
            $written[] = $key;
        }

        return [
            'lines' => array_values($lines),
            'preserved' => $preserved,
            'written' => $written,
        ];
    }

    private function hasEmptyValue(string $line): bool
    {
        $value = preg_replace('/^\s*[A-Z0-9_]+\s*=\s*/', '', $line);

        if (! is_string($value)) {
            return true;
        }

        $value = trim($value);

        // m-08: comentario en la misma línea después de un valor vacío.
        if (preg_match('/^(["\'])(.*?)\1\s*(?:#.*)?$/', $value, $matches) === 1) {
            return trim($matches[2]) === '';
        }

        $value = preg_replace('/(?:^|\s+)#.*$/', '', $value);

        return ! is_string($value) || trim($value) === '';
    }

    private function quote(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.:\/+\-]*$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\\"', '\\$'],
            $value,
        ).'"';
    }

    private function configuredPath(): string
    {
        $configured = config('install.env_path');

        return is_string($configured) && $configured !== ''
            ? $configured
            : base_path('.env');
    }
}
