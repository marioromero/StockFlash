<?php

return [
    /*
     * INSTALL-001 · CORE-17 — Instalador web de una sola instalación.
     *
     * Fail-closed por defecto: el instalador permanece cerrado salvo que el
     * responsable técnico lo habilite y configure un token de arranque fuera
     * de la aplicación pública. Nunca se entrega un token por defecto usable.
     */
    'enabled' => (bool) env('INSTALL_ENABLED', false),
    'bootstrap_token' => env('INSTALL_BOOTSTRAP_TOKEN'),
    'min_bootstrap_token_length' => 32,

    // Ruta del archivo de configuración local que se actualiza de forma
    // atómica solo tras la confirmación explícita. `null` = base_path('.env').
    // Las pruebas inyectan un escritor falso o un archivo temporal.
    'env_path' => env('INSTALL_ENV_PATH'),

    'app_version' => env('APP_VERSION', '0.1.0'),
    'release_channel' => env('INSTALL_RELEASE_CHANNEL', 'stable'),

    // Única línea orientativa; el instalador NO instala cron ni despliega.
    'cron_line' => 'php artisan schedule:run',

    // Límites de seguridad del flujo técnico.
    'lock_seconds' => 300,
    'rate_limit' => 5,
    'rate_decay_seconds' => 60,

    /*
     * B-02: el instalador no puede depender de `sessions`/`cache` en la BD
     * antes de crear el esquema. Estas tiendas se aplican SOLO a las rutas
     * install/install/* mediante `UseInstallStores`. En producción el default
     * es file; las pruebas usan una tienda array aislada por app.
     */
    'session' => [
        'driver' => env('INSTALL_SESSION_DRIVER', 'file'),
        'files' => env('INSTALL_SESSION_FILES', storage_path('framework/install-sessions')),
    ],

    'cache' => [
        'store' => env('INSTALL_CACHE_STORE', 'install'),
        'driver' => env('INSTALL_CACHE_DRIVER', 'file'),
        'path' => env('INSTALL_CACHE_PATH', storage_path('framework/cache/install')),
        'lock_path' => env('INSTALL_CACHE_LOCK_PATH', storage_path('framework/cache/install')),
    ],

    'minimum_php_version' => '8.3.0',

    'required_extensions' => [
        'pdo',
        'pdo_mysql',
        'mbstring',
        'openssl',
        'ctype',
        'json',
        'tokenizer',
        'xml',
        'fileinfo',
    ],

    'required_files' => [
        'artisan',
        'vendor/autoload.php',
    ],

    'writable_paths' => [
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ],
];
