<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tiendas no dependientes del esquema para el instalador (INSTALL-001 · B-02).
 *
 * Debe ejecutarse antes de `StartSession` en el grupo web. Solo afecta las
 * rutas exactas del instalador; el resto de la aplicación conserva sus
 * drivers configurados. La sesión y la caché (rate limit, lock, consumo del
 * token bootstrap) usan una tienda dedicada que no requiere tablas.
 *
 * M-05: si las tiendas file no se pueden crear, cae a array y marca
 * `install.runtime_storage_unavailable` para que el preflight lo reporte.
 * o-07: restaura la configuración al terminar el request.
 */
class UseInstallStores
{
    /**
     * @var list<string>
     */
    private const INSTALL_ROUTES = [
        'install.show',
        'install.bootstrap',
        'install.preflight',
        'install.connection',
        'install.run',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || ! in_array($routeName, self::INSTALL_ROUTES, true)) {
            return $next($request);
        }

        $store = trim((string) config('install.cache.store', 'install'));

        if ($store === '') {
            $store = 'install';
        }

        $original = [
            'session.driver' => config('session.driver'),
            'session.files' => config('session.files'),
            'cache.default' => config('cache.default'),
            'cache.limiter' => config('cache.limiter'),
            'install.runtime_storage_unavailable' => config('install.runtime_storage_unavailable', false),
        ];

        $path = (string) config('install.cache.path', storage_path('framework/cache/install'));
        $driver = (string) config('install.cache.driver', 'file');
        $sessionDriver = (string) config('install.session.driver', 'file');
        $sessionFiles = (string) config('install.session.files', storage_path('framework/install-sessions'));
        $lockPath = (string) config('install.cache.lock_path', $path);
        $storageUnavailable = false;

        try {
            if ($sessionDriver === 'file') {
                File::ensureDirectoryExists($sessionFiles);

                if (! is_dir($sessionFiles) || ! is_writable($sessionFiles)) {
                    throw new \RuntimeException('La tienda de sesión del instalador no es escribible.');
                }
            }

            if ($driver === 'file') {
                File::ensureDirectoryExists($path);
                File::ensureDirectoryExists($lockPath);

                if (! is_dir($path) || ! is_writable($path) || ! is_dir($lockPath) || ! is_writable($lockPath)) {
                    throw new \RuntimeException('La tienda de caché del instalador no es escribible.');
                }
            }
        } catch (Throwable) {
            $storageUnavailable = true;
        }

        if ($storageUnavailable) {
            config([
                'install.runtime_storage_unavailable' => true,
                'cache.stores.'.$store => [
                    'driver' => 'array',
                    'serialize' => false,
                ],
                'cache.default' => $store,
                'cache.limiter' => $store,
                'session.driver' => 'array',
            ]);
        } else {
            config([
                'install.runtime_storage_unavailable' => false,
                'cache.stores.'.$store => [
                    'driver' => $driver,
                    'path' => $path,
                    'lock_path' => $lockPath,
                ],
                'cache.default' => $store,
                // RateLimiter se resuelve contra esta tienda explícita.
                'cache.limiter' => $store,
                'session.driver' => $sessionDriver,
                'session.files' => $sessionFiles,
            ]);
        }

        if (app()->resolved('cache')) {
            app('cache')->purge();
        }

        try {
            return $next($request);
        } finally {
            config($original);

            if (app()->resolved('cache')) {
                app('cache')->purge();
            }
        }
    }
}
