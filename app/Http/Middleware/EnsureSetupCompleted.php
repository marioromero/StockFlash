<?php

namespace App\Http\Middleware;

use App\Support\Setup\SetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exclusividad segura del primer inicio (SETUP-001 · CORE-02).
 *
 * Mientras `is_setup_completed` no esté activo, todo tráfico privado se
 * redirige al asistente (GET/HTML) o se bloquea con 423 (mutaciones/JSON).
 * Solo se eximen por nombre exacto de ruta las excepciones acotadas. No se
 * inspeccionan query, body ni cabeceras: no existe bypass por payload.
 */
class EnsureSetupCompleted
{
    /**
     * Rutas exentas mientras la instalación no está configurada.
     *
     * `login.store` es el POST de ingreso: la ruta GET ya se llama `login`,
     * por lo que el POST requiere un nombre propio para poder eximirse sin
     * abrir un prefijo.
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTES = [
        // INSTALL-001: se eximen por nombre exacto para que /install pueda
        // operar sin esquema. No existe exención por prefijo ni comodín.
        'install.show',
        'install.bootstrap',
        'install.preflight',
        'install.connection',
        'install.run',
        'setup.show',
        'setup.store',
        'login',
        'login.store',
        'logout',
        'password.change',
        'password.update',
        'docs.index',
        'roadmap.index',
        'home',
    ];

    public function __construct(private readonly SetupService $setup) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->setup->schemaReady()) {
            // El POST del asistente no puede validar contra tablas ausentes:
            // responde 503 sin intentar migrar. El resto de rutas exentas
            // (home/login/docs) conserva su comportamiento normal.
            if ($this->isExempt($request) && ! $request->routeIs('setup.store')) {
                return $next($request);
            }

            return response(
                'El esquema de la base de datos no está preparado. La instalación no se ejecuta automáticamente; contacte al responsable técnico.',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        if ($this->setup->isCompleted()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $isReadOnlyHtml = ($request->isMethod('GET') || $request->isMethod('HEAD'))
            && ! $request->expectsJson();

        if ($isReadOnlyHtml) {
            return redirect()->route('setup.show');
        }

        abort(423, 'La instalación requiere completar la configuración inicial.');
    }

    private function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name)) {
            return false;
        }

        return in_array($name, self::EXEMPT_ROUTES, true);
    }
}
