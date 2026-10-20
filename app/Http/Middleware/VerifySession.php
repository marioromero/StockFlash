<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica requisitos de sesión (SEC-05):
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
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // 1. Usuario activo: si fue desactivado, invalidar sesión.
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Cuenta desactivada.');
        }

        // 2. Leer configuración de sesiones desde system_configs.
        $configs = DB::table('system_configs')->pluck('config_value', 'config_key');

        $absoluteHours = (int) ($configs['session_absolute_hours'] ?? 12);
        $idleMinutesPos = (int) ($configs['session_idle_minutes_pos'] ?? 30);
        $idleMinutesAdmin = (int) ($configs['session_idle_minutes_admin'] ?? 60);

        // 3. Duración absoluta: last_login_at + session_absolute_hours.
        if ($user->last_login_at instanceof \DateTimeInterface) {
            $limiteAbsoluto = $user->last_login_at->addHours($absoluteHours);
            if (now()->gt($limiteAbsoluto)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Sesión expirada por duración absoluta.');
            }
        }

        // 4. Inactividad: last_activity de la sesión + idle minutes.
        $ultimaActividad = $request->session()->get('last_activity');
        if ($ultimaActividad instanceof \DateTimeInterface) {
            $idleMinutes = $user->isGlobal() ? $idleMinutesAdmin : $idleMinutesPos;
            $limiteInactividad = $ultimaActividad->addMinutes($idleMinutes);
            if (now()->gt($limiteInactividad)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Sesión expirada por inactividad.');
            }
        }

        // 5. Actualizar last_activity en cada request protegido.
        $request->session()->put('last_activity', now());

        return $next($request);
    }
}
