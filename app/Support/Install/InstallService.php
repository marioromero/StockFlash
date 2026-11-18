<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use App\Support\Install\Contracts\InstallMigrator;
use App\Support\Install\Contracts\InstallSecretWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Estado y orquestación del instalador técnico (INSTALL-001 · CORE-17).
 *
 * Solo prepara entorno, esquema y semillas. No crea usuarios, sucursales ni
 * datos comerciales: eso sigue siendo responsabilidad exclusiva de `/setup`.
 * Ningún método borra datos, reinicializa usuarios ni revierte migraciones.
 */
final class InstallService
{
    public const SESSION_BOOTSTRAP = 'install_bootstrap_authorized';
    public const SESSION_FLOW = 'install_flow_token';
    public const SESSION_PREFLIGHT = 'install_preflight';
    public const SESSION_CONNECTION = 'install_connection';
    public const SESSION_FAILURE = 'install_failure';
    public const LOCK_KEY = 'stockflash.install.run';

    private const REQUIRED_INSTALLATION_COLUMNS = [
        'installation_id',
        'app_version',
        'schema_version',
        'release_channel',
        'installed_at',
    ];

    public function __construct(
        private readonly InstallSecretGenerator $secrets,
        private readonly InstallDatabaseConfigurator $configurator,
        private readonly InstallSecretWriter $writer,
        private readonly InstallMigrator $migrator,
    ) {}

    /**
     * Fail-closed: sin habilitación explícita y token configurado no existe
     * instalador público.
     */
    public function assertEnabled(): void
    {
        if (! $this->enabled()) {
            abort(404);
        }
    }

    public function enabled(): bool
    {
        if (! (bool) config('install.enabled', false)) {
            return false;
        }

        return $this->configuredToken() !== null;
    }

    public function matchesBootstrapToken(string $provided): bool
    {
        $expected = $this->configuredToken();

        if ($expected === null) {
            return false;
        }

        $provided = trim($provided);

        if ($provided === '' || $this->bootstrapTokenConsumed($provided)) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Consume el token de arranque en una tienda fuera de la BD.
     *
     * El marcador sobrevive a la sesión para que el token no pueda reutilizarse
     * desde otra sesión mientras el instalador siga habilitado.
     */
    public function consumeBootstrapToken(string $provided): void
    {
        $this->installCache()->put(
            $this->bootstrapConsumedKey($provided),
            true,
            now()->addDay(),
        );
    }

    public function bootstrapTokenConsumed(string $provided): bool
    {
        return $this->installCache()->has($this->bootstrapConsumedKey($provided));
    }

    public function authorizeBootstrap(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put(
            self::SESSION_BOOTSTRAP,
            hash('sha256', (string) $this->configuredToken()),
        );
    }

    public function isBootstrapAuthorized(Request $request): bool
    {
        $expected = $this->configuredToken();
        $stored = $request->session()->get(self::SESSION_BOOTSTRAP);

        return $expected !== null
            && is_string($stored)
            && $stored !== ''
            && hash_equals(hash('sha256', $expected), $stored);
    }

    public function assertBootstrapAuthorized(Request $request): void
    {
        if (! $this->isBootstrapAuthorized($request)) {
            abort(403, 'El instalador requiere autorización técnica con token de arranque.');
        }
    }

    public function issueFlowToken(Request $request): string
    {
        $existing = $request->session()->get(self::SESSION_FLOW);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $request->session()->put(self::SESSION_FLOW, $token);

        return $token;
    }

    public function assertFlowToken(Request $request, ?string $provided): void
    {
        $expected = $request->session()->get(self::SESSION_FLOW);

        if (
            ! is_string($expected)
            || $expected === ''
            || ! is_string($provided)
            || $provided === ''
            || ! hash_equals($expected, $provided)
        ) {
            abort(419, 'La sesión del instalador expiró. Recargue la página e intente nuevamente.');
        }
    }

    public function forgetFlowToken(Request $request): void
    {
        $request->session()->forget(self::SESSION_FLOW);
    }

    /**
     * Estado de la instancia. Nunca modifica ni borra datos.
     *
     * @return array{code: string, message: string}
     */
    public function state(): array
    {
        try {
            if (Schema::hasTable('installation_info')) {
                if (! Schema::hasColumns('installation_info', self::REQUIRED_INSTALLATION_COLUMNS)) {
                    return [
                        'code' => 'SCHEMA_INCONSISTENT',
                        'message' => 'El esquema de instalación existente no es compatible. No se modificará ningún dato; se requiere revisión técnica manual no destructiva.',
                    ];
                }

                if (DB::table('installation_info')->count() > 0) {
                    return [
                        'code' => 'ALREADY_INSTALLED',
                        'message' => 'Esta instancia ya fue instalada. Por seguridad no es posible reinstalar, borrar datos, reiniciar usuarios ni sobrescribir claves.',
                    ];
                }
            }

            foreach (['users', 'branches'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->count() > 0) {
                    return [
                        'code' => 'INSTANCE_NOT_PRISTINE',
                        'message' => 'La instancia ya contiene usuarios o sucursales. El instalador no reinstala ni reinicia información existente.',
                    ];
                }
            }

            return ['code' => 'VIRGIN', 'message' => 'Instancia sin registro de instalación ni datos operativos.'];
        } catch (Throwable) {
            return [
                'code' => 'DATABASE_UNAVAILABLE',
                'message' => 'No fue posible verificar el estado de la base de datos. No se ejecutó ninguna acción; revise la conexión de forma manual.',
            ];
        }
    }

    /**
     * @param  array{ok: bool, checks: list<array{id: string, label: string, status: string, detail: string}>}  $result
     */
    public function storePreflight(Request $request, array $result): void
    {
        $request->session()->put(self::SESSION_PREFLIGHT, $result);
    }

    /**
     * @return array{ok: bool, checks?: list<array{id: string, label: string, status: string, detail: string}>}|null
     */
    public function preflightResult(Request $request): ?array
    {
        $result = $request->session()->get(self::SESSION_PREFLIGHT);

        return is_array($result) ? $result : null;
    }

    public function preflightPassed(Request $request): bool
    {
        return ($this->preflightResult($request)['ok'] ?? false) === true;
    }

    /**
     * @param  array{ok: bool, message: string, label: string}  $result
     */
    public function storeConnection(Request $request, array $result): void
    {
        $request->session()->put(self::SESSION_CONNECTION, $result);
    }

    /**
     * @return array{ok: bool, message?: string, label?: string}|null
     */
    public function connectionResult(Request $request): ?array
    {
        $result = $request->session()->get(self::SESSION_CONNECTION);

        return is_array($result) ? $result : null;
    }

    public function connectionPassed(Request $request): bool
    {
        return ($this->connectionResult($request)['ok'] ?? false) === true;
    }

    public function connectionMatches(Request $request, InstallDatabaseConfig $database): bool
    {
        $label = $this->connectionResult($request)['label'] ?? null;

        return is_string($label) && $label !== '' && $label === $database->label();
    }

    /**
     * Ejecuta la instalación una sola vez.
     *
     * Orden B-03: lock → configurar conexión destino → evaluar virginidad en
     * esa conexión → escribir secretos → migrar → sembrar → registrar.
     * Devuelve un resultado sin secretos. En fallas intermedias no marca éxito,
     * no inserta `installation_info` y no revierte migraciones automáticamente.
     *
     * @return array{ok: bool, blocked?: bool, installation_id?: string, preserved_keys?: list<string>, message: string}
     */
    public function perform(InstallDatabaseConfig $database): array
    {
        $lock = $this->installCache()->lock(self::LOCK_KEY, (int) config('install.lock_seconds', 300));

        if (! $lock->get()) {
            return [
                'ok' => false,
                'blocked' => true,
                'message' => 'Ya hay una instalación en curso. Espere a que finalice antes de reintentar.',
            ];
        }

        try {
            // La virginidad SIEMPRE se evalúa contra la BD destino elegida,
            // nunca contra la conexión por defecto de `.env`.
            $this->configurator->configure($database);

            $state = $this->state();

            if ($state['code'] !== 'VIRGIN') {
                return ['ok' => false, 'blocked' => true, 'message' => $state['message']];
            }

            $secrets = $this->secrets->generate();
            $writeResult = $this->writer->persist($database, $secrets);
            $this->migrator->migrate();
            $this->migrator->seed();
            $installationId = $this->recordInstallationInfo();

            return [
                'ok' => true,
                'installation_id' => $installationId,
                'preserved_keys' => $writeResult['preserved'] ?? [],
                'message' => 'Instalación técnica completada una sola vez. Continúe con la configuración inicial.',
            ];
        } catch (Throwable $exception) {
            // Sin mensajes de excepción: podrían contener credenciales de BD.
            logger()->error('install.failed', [
                'exception' => $exception::class,
            ]);

            return [
                'ok' => false,
                'message' => 'La instalación no se completó. No se marcó como exitosa ni se revirtieron datos automáticamente; revise el registro técnico del servidor antes de reintentar.',
            ];
        } finally {
            $lock->release();
        }
    }

    public function recordFailure(Request $request, string $message): void
    {
        $request->session()->put(self::SESSION_FAILURE, $message);
    }

    public function failure(Request $request): ?string
    {
        $message = $request->session()->get(self::SESSION_FAILURE);

        return is_string($message) && $message !== '' ? $message : null;
    }

    public function resetSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_BOOTSTRAP,
            self::SESSION_FLOW,
            self::SESSION_PREFLIGHT,
            self::SESSION_CONNECTION,
            self::SESSION_FAILURE,
        ]);
    }

    private function installCache(): \Illuminate\Contracts\Cache\Repository
    {
        $store = trim((string) config('install.cache.store', 'install'));

        return Cache::store($store !== '' ? $store : 'install');
    }

    private function bootstrapConsumedKey(string $provided): string
    {
        return 'install.bootstrap.consumed.'.hash('sha256', trim($provided));
    }

    private function configuredToken(): ?string
    {
        $token = config('install.bootstrap_token');

        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);
        $minimum = max(1, (int) config('install.min_bootstrap_token_length', 32));

        return strlen($token) >= $minimum ? $token : null;
    }

    private function recordInstallationInfo(): string
    {
        if (! Schema::hasTable('installation_info')) {
            throw new RuntimeException('No existe la tabla installation_info después de migrar.');
        }

        if (DB::table('installation_info')->count() > 0) {
            throw new RuntimeException('La instancia ya tiene un registro de instalación.');
        }

        // Revalidación final en la conexión destino, dentro del lock.
        foreach (['users', 'branches'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() > 0) {
                throw new RuntimeException('La instancia destino ya contiene usuarios o sucursales.');
            }
        }

        $installationId = Str::uuid()->toString();
        $channel = (string) config('install.release_channel', 'stable');

        if (! in_array($channel, ['stable', 'beta'], true)) {
            $channel = 'stable';
        }

        $now = now();

        DB::table('installation_info')->insert([
            'installation_id' => $installationId,
            'app_version' => substr((string) config('install.app_version', '0.1.0'), 0, 20),
            'schema_version' => $this->migrator->schemaVersion(),
            'release_channel' => $channel,
            'installed_at' => $now,
            'last_update_at' => null,
            'last_update_status' => 'none',
            'recovery_package_exported_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $installationId;
    }
}
