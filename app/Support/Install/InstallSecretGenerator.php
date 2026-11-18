<?php

namespace App\Support\Install;

/**
 * Generación CSPRNG de las claves de la instalación.
 *
 * - APP_KEY: formato Laravel `base64:` + 32 bytes aleatorios.
 * - BLIND_INDEX_KEY: 32 bytes en hexadecimal (64 caracteres) para HMAC-SHA256.
 * - BACKUP_ENCRYPTION_KEY: `base64:` + 32 bytes aleatorios, distinta de APP_KEY.
 *
 * Los valores solo se entregan al escritor de secretos; nunca a la interfaz,
 * props Inertia, logs, flashes ni auditoría.
 */
final class InstallSecretGenerator
{
    /**
     * @return array{app_key: string, blind_index_key: string, backup_encryption_key: string}
     */
    public function generate(): array
    {
        $appKey = $this->base64Key();
        $backupKey = $this->base64Key();

        // La probabilidad de colisión es despreciable; se garantiza igualmente
        // que las dos claves de 32 bytes nunca sean idénticas.
        while (hash_equals($appKey, $backupKey)) {
            $backupKey = $this->base64Key();
        }

        return [
            'app_key' => $appKey,
            'blind_index_key' => bin2hex(random_bytes(32)),
            'backup_encryption_key' => $backupKey,
        ];
    }

    private function base64Key(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }
}
