<?php

namespace App\Http\Middleware;

use App\Support\UserAccessFingerprint;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica requisitos de sesión (SEC-05):
 * - Fingerprint de acceso vigente (contraseña, rol, asignaciones, sucursal
 *   inicial y estado). Sin fingerprint o con diferencias se invalida la sesión.
 * - Usuario activo (is_active = 1).
 * - Duración absoluta de sesión (session_absolute_hours, default 12h).
 * - Inactividad según contexto: POS 30 min, administración 60 min
 *   (session_idle_minutes_pos / session_idle_minutes_admin).
 * - Actualiza last_activity en cada request protegido.
 */
class VerifySession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Public HTML must not require or mutate an authenticated session.
        if ($request->routeIs('roadmap.index', 'docs.index')) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // 1. Fingerprint de acceso (SEC-05 / USERS-001). Se recalcula desde la
        //    base de datos en cada petición autenticada. Un fingerprint ausente
        //    (sesión previa al despliegue) también invalida la sesión.
        $storedFingerprint = $request->session()->get(UserAccessFingerprint::SESSION_KEY);

        if (! UserAccessFingerprint::matches($user, $storedFingerprint)) {
            $this->invalidate($request);

            abort(403, 'Sesión invalidada por cambio de acceso.');
        }

        // 2. Usuario activo: si fue desactivado, invalidar sesión.
        if (! $user->is_active) {
            $this->invalidate($request);

            abort(403, 'Cuenta desactivada.');
        }

        // 3. Leer configuración de sesiones desde system_configs.
        $configs = DB::table('system_configs')->pluck('config_value', 'config_key');

        $absoluteHours = (int) ($configs['session_absolute_hours'] ?? 12);
        $idleMinutesPos = (int) ($configs['session_idle_minutes_pos'] ?? 30);
        $idleMinutesAdmin = (int) ($configs['session_idle_minutes_admin'] ?? 60);

        // 4. Duración absoluta por sesión: session_started_at + session_absolute_hours.
        //    last_login_at es solo fallback cuando la sesión no registra su inicio
        //    (sin escribirlo ni extender sesiones existentes).
        $sessionStartedAt = $request->session()->get('session_started_at');
        $inicioSesion = $sessionStartedAt instanceof \DateTimeInterface
            ? $sessionStartedAt
            : $user->last_login_at;
        if ($inicioSesion instanceof \DateTimeInterface) {
            $limiteAbsoluto = $inicioSesion->copy()->addHours($absoluteHours);
            if (now()->gt($limiteAbsoluto)) {
                $this->invalidate($request);

                abort(403, 'Sesión expirada por duración absoluta.');
            }
        }

        // 5. Inactividad: last_activity de la sesión + idle minutes.
        $ultimaActividad = $request->session()->get('last_activity');
        if ($ultimaActividad instanceof \DateTimeInterface) {
            $idleMinutes = $user->isGlobal() ? $idleMinutesAdmin : $idleMinutesPos;
            $limiteInactividad = $ultimaActividad->copy()->addMinutes($idleMinutes);
            if (now()->gt($limiteInactividad)) {
                $this->invalidate($request);

                abort(403, 'Sesión expirada por inactividad.');
            }
        }

        // 6. Actualizar last_activity en cada request protegido.
        $request->session()->put('last_activity', now());

        return $next($request);
    }

    private function invalidate(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
