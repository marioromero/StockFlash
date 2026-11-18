<?php

namespace App\Http\Controllers;

use App\Http\Requests\Install\InstallBootstrapRequest;
use App\Http\Requests\Install\InstallConnectionRequest;
use App\Http\Requests\Install\InstallRunRequest;
use App\Support\Install\Contracts\InstallConnectionTester;
use App\Support\Install\Contracts\InstallPreflightChecker;
use App\Support\Install\InstallDatabaseConfig;
use App\Support\Install\InstallService;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Instalador técnico de una sola instalación (INSTALL-001 · CORE-17).
 *
 * No crea usuarios, sucursales, licencias ni datos comerciales. No ejecuta
 * migraciones por GET. Todas las mutaciones exigen sesión autorizada por token
 * de arranque, token de flujo, CSRF del stack web y confirmación explícita.
 */
class InstallController extends Controller
{
    public function __construct(
        private readonly InstallService $install,
        private readonly InstallPreflightChecker $preflight,
        private readonly InstallConnectionTester $connectionTester,
    ) {}

    public function show(Request $request): SymfonyResponse
    {
        $this->install->assertEnabled();

        $state = $this->install->state();

        if (! in_array($state['code'], ['VIRGIN', 'DATABASE_UNAVAILABLE'], true)) {
            return $this->unavailable($request, $state['code'], $state['message']);
        }

        $authorized = $this->install->isBootstrapAuthorized($request);

        $props = [
            'authorized' => $authorized,
            'state' => $state,
            'steps' => $this->steps(),
            'cron_line' => (string) config('install.cron_line', 'php artisan schedule:run'),
            'app_version' => (string) config('install.app_version', '0.1.0'),
            'release_channel' => $this->releaseChannel(),
            'storage_warning' => (bool) config('install.runtime_storage_unavailable', false),
        ];

        if ($authorized) {
            $props['flow_token'] = $this->install->issueFlowToken($request);
            $props['preflight'] = $this->install->preflightResult($request);
            $props['connection'] = $this->install->connectionResult($request);
            $props['failure'] = $this->install->failure($request);
            $props['database_defaults'] = [
                'driver' => 'mariadb',
                'host' => '',
                'port' => 3306,
                'database' => '',
                'username' => '',
            ];
        }

        return $this->inertia($request, 'Install/Wizard', $props);
    }

    public function bootstrap(InstallBootstrapRequest $request): RedirectResponse
    {
        $this->install->assertEnabled();

        $key = 'install.bootstrap.'.$request->ip();
        $max = max(1, (int) config('install.rate_limit', 5));
        $limiter = new CacheRateLimiter(Cache::store($this->installStore()));

        if ($limiter->tooManyAttempts($key, $max)) {
            abort(429, 'Demasiados intentos de autorización. Espere antes de reintentar.');
        }

        $limiter->hit($key, (int) config('install.rate_decay_seconds', 60));

        $token = (string) $request->validated('bootstrap_token');

        if (! $this->install->matchesBootstrapToken($token)) {
            throw ValidationException::withMessages([
                'bootstrap_token' => 'El token de arranque no es válido o ya fue utilizado.',
            ]);
        }

        // m-01: el token es de un solo uso, incluso desde otra sesión.
        $this->install->consumeBootstrapToken($token);
        $limiter->clear($key);
        $this->install->authorizeBootstrap($request);

        return redirect()->route('install.show');
    }

    public function preflightStep(Request $request): RedirectResponse
    {
        $this->install->assertEnabled();
        $this->install->assertBootstrapAuthorized($request);
        $this->install->assertFlowToken($request, $this->token($request));

        $result = $this->preflight->check();
        $this->install->storePreflight($request, $result);

        if (! ($result['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'preflight' => 'La verificación previa encontró requisitos pendientes. Corrija los puntos fallidos antes de continuar.',
            ]);
        }

        return redirect()->route('install.show');
    }

    public function connectionStep(InstallConnectionRequest $request): RedirectResponse
    {
        $this->install->assertEnabled();
        $this->install->assertBootstrapAuthorized($request);
        $this->install->assertFlowToken($request, (string) $request->validated('install_token'));

        $database = InstallDatabaseConfig::fromArray($request->validated());
        $result = $this->connectionTester->test($database);

        $this->install->storeConnection($request, [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'label' => $database->label(),
        ]);

        if (! ($result['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'db' => (string) ($result['message'] ?? 'No fue posible conectar con la base de datos indicada.'),
            ]);
        }

        return redirect()->route('install.show');
    }

    public function run(InstallRunRequest $request): RedirectResponse
    {
        $this->install->assertEnabled();
        $this->install->assertBootstrapAuthorized($request);

        $data = $request->validated();
        $this->install->assertFlowToken($request, (string) $data['install_token']);

        if (! $this->install->preflightPassed($request)) {
            throw ValidationException::withMessages([
                'preflight' => 'Debe completar la verificación previa correctamente antes de instalar.',
            ]);
        }

        if (! $this->install->connectionPassed($request)) {
            throw ValidationException::withMessages([
                'db' => 'Debe probar la conexión de base de datos antes de instalar.',
            ]);
        }

        $database = InstallDatabaseConfig::fromArray($data);

        if (! $this->install->connectionMatches($request, $database)) {
            throw ValidationException::withMessages([
                'db' => 'El destino cambió desde la última prueba. Vuelva a probar la conexión antes de instalar.',
            ]);
        }

        // Revalidación de conexión inmediatamente antes de cualquier efecto.
        $connection = $this->connectionTester->test($database);

        if (! ($connection['ok'] ?? false)) {
            $this->install->storeConnection($request, [
                'ok' => false,
                'message' => (string) ($connection['message'] ?? ''),
                'label' => $database->label(),
            ]);

            throw ValidationException::withMessages([
                'db' => (string) ($connection['message'] ?? 'No fue posible conectar con la base de datos indicada.'),
            ]);
        }

        $outcome = $this->install->perform($database);

        if (($outcome['blocked'] ?? false) === true) {
            abort(423, $outcome['message']);
        }

        if (! $outcome['ok']) {
            $this->install->recordFailure($request, $outcome['message']);

            throw ValidationException::withMessages([
                'install' => $outcome['message'],
            ]);
        }

        $this->install->resetSession($request);

        // B-04: sin flash entre tiendas. `/setup` calcula el aviso determinista
        // desde `installation_info` y `is_setup_completed`.
        return redirect()->route('setup.show');
    }

    private function token(Request $request): ?string
    {
        $token = $request->input('install_token');

        return is_string($token) ? $token : null;
    }

    private function installStore(): string
    {
        $store = trim((string) config('install.cache.store', 'install'));

        return $store !== '' ? $store : 'install';
    }

    private function unavailable(Request $request, string $reason, string $message): SymfonyResponse
    {
        return $this->inertia($request, 'Install/Unavailable', [
            'reason_code' => $reason,
            'message' => $message,
        ], 423);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function inertia(Request $request, string $component, array $props, int $status = 200): SymfonyResponse
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->setStatusCode($status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function steps(): array
    {
        return [
            ['id' => 1, 'label' => 'Requisitos previos'],
            ['id' => 2, 'label' => 'Conexión de base de datos'],
            ['id' => 3, 'label' => 'Confirmación de destino'],
            ['id' => 4, 'label' => 'Instalación y cron'],
        ];
    }

    private function releaseChannel(): string
    {
        $channel = (string) config('install.release_channel', 'stable');

        return in_array($channel, ['stable', 'beta'], true) ? $channel : 'stable';
    }
}
