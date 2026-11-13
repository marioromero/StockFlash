<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserAccessFingerprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return inertia('Auth/Login', [
            'canResetPassword' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = strtolower((string) $request->email);
        $key = 'login:' . Str::lower($login);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => __('demasiados_intentos, intente nuevamente en :seconds segundos.', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::attempt(['email' => $login, 'password' => $request->password], $request->boolean('remember'))) {
            RateLimiter::hit($key, 30);

            $user = User::where('email', $login)->first();
            if ($user) {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        'email' => __('cuenta_inactiva'),
                    ]);
                }
                if ($user->isLocked()) {
                    throw ValidationException::withMessages([
                        'email' => __('cuenta_bloqueada_temporalmente'),
                    ]);
                }
                $user->incrementFailedLogin();
                if ($user->isLocked()) {
                    throw ValidationException::withMessages([
                        'email' => __('cuenta_bloqueada_temporalmente'),
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'email' => __('credenciales_incorrectas'),
            ]);
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('cuenta_inactiva'),
            ]);
        }

        if ($user->isLocked()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('cuenta_bloqueada_temporalmente'),
            ]);
        }

        $user->recordLogin($request->ip());
        $request->session()->put('session_started_at', now());
        $request->session()->put('last_activity', now());
        $request->session()->put(
            UserAccessFingerprint::SESSION_KEY,
            UserAccessFingerprint::for($user),
        );

        if ($user->mustChangePassword()) {
            return redirect()->route('password.change')->with('warning', 'debe_cambiar_contrasena');
        }

        return redirect()->intended(route('dashboard'));
    }
}
