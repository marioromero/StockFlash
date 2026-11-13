<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fingerprint de acceso de la sesión (SEC-05 / USERS-001).
 *
 * Resume los componentes que conceden o quitan acceso: identidad, hash de
 * contraseña, rol global, sucursal inicial, estado activo y asignaciones
 * `branch_id:role_id`. Se calcula con HMAC-SHA256 usando la clave de la
 * aplicación y se guarda en la sesión tras el login. `VerifySession` lo
 * recalcula en cada petición autenticada; cualquier diferencia invalida la
 * sesión (cambio de contraseña, rol, asignaciones, sucursal inicial o estado).
 *
 * El valor resultante nunca se registra, audita ni expone.
 */
class UserAccessFingerprint
{
    /** Clave de sesión donde se almacena el fingerprint. */
    public const SESSION_KEY = 'user_access_fingerprint';

    public static function for(User $user): string
    {
        $assignments = DB::table('branch_user')
            ->where('user_id', $user->getKey())
            ->orderBy('branch_id')
            ->orderBy('role_id')
            ->get(['branch_id', 'role_id'])
            ->map(fn (object $row): string => $row->branch_id.':'.$row->role_id)
            ->implode(',');

        $payload = json_encode([
            'version' => 1,
            'id' => (int) $user->getKey(),
            'password' => (string) $user->getAuthPassword(),
            'global_role_id' => $user->global_role_id === null ? null : (int) $user->global_role_id,
            'default_branch_id' => $user->default_branch_id === null ? null : (int) $user->default_branch_id,
            'is_active' => (bool) $user->is_active,
            'assignments' => $assignments,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', (string) $payload, (string) config('app.key'));
    }

    /**
     * Compara en tiempo constante el fingerprint de la sesión con el actual.
     */
    public static function matches(User $user, mixed $stored): bool
    {
        return is_string($stored) && $stored !== '' && hash_equals(self::for($user), $stored);
    }
}
