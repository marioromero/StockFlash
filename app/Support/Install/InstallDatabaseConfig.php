<?php

namespace App\Support\Install;

/**
 * Credenciales y destino de la base de datos elegida por el operador.
 *
 * El password nunca se entrega a la interfaz, a un log, a un flash ni a una
 * respuesta HTTP. `__debugInfo` evita que un `var_dump` accidental lo muestre.
 */
final readonly class InstallDatabaseConfig
{
    public function __construct(
        public string $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        #[\SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['driver'] ?? 'mariadb'),
            (string) ($data['host'] ?? ''),
            (int) ($data['port'] ?? 3306),
            (string) ($data['database'] ?? ''),
            (string) ($data['username'] ?? ''),
            (string) ($data['db_password'] ?? ''),
        );
    }

    /**
     * Etiqueta pública no secreta usada para verificar que la instalación se
     * ejecuta contra el mismo destino que ya se probó.
     */
    public function label(): string
    {
        return $this->host.':'.$this->port.'/'.$this->database;
    }

    /**
     * @return array<string, mixed>
     */
    public function toConnectionConfig(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => (string) $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => '[redactado]',
        ];
    }
}
