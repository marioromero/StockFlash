<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function show()
    {
        return inertia('Auth/PasswordChange');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $minLength = (int) config('system_configs.password_min_length', 15);

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:' . $minLength,
                'max:64',
                'confirmed',
            ],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('contrasena_actual_incorrecta'),
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('la_contrasena_nueva_debe_diferir_de_la_actual'),
            ]);
        }

        $commonPasswords = (array) config('system_configs.common_passwords', [
            '123456789012345',
            'password123456789',
            'admin1234567890',
            '1234567890123456',
            'qwerty123456789',
        ]);
        if (in_array(strtolower($request->password), array_map('strtolower', $commonPasswords))) {
            throw ValidationException::withMessages([
                'password' => __('contrasena_comun_o_comprometida'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'contrasena_cambiada_inicie_sesion_nuevamente');
    }
}
