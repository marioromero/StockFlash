<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'version' => 'Laravel ' . app()->version(),
    ]);
})->name('home');

// --- Autenticación (AUTH-001) ---

Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'store']);
Route::post('/logout', [\App\Http\Controllers\Auth\LogoutController::class, 'destroy'])->name('logout');

// Cambio de contraseña (solo autenticados)
Route::middleware(['auth'])->group(function () {
    Route::get('/password/change', [\App\Http\Controllers\Auth\PasswordController::class, 'show'])->name('password.change');
    Route::put('/password/change', [\App\Http\Controllers\Auth\PasswordController::class, 'update'])->name('password.update');
});

// --- Dashboard protegido (AUTH-001) ---
Route::middleware(['auth', 'password.change', 'verify.session'])->group(function () {
    Route::get('/dashboard', function () {
        return inertia('Dashboard');
    })->name('dashboard');

    // Rutas de prueba de autorización (AUTH-001). No son CRUD: devuelven
    // un payload simple para verificar que el middleware de permisos y de
    // acceso a sucursal se ejecuta correctamente.
    Route::get('/usuarios', function () {
        return response()->json(['ok' => true, 'seccion' => 'usuarios']);
    })->middleware('can.permission:users.manage')->name('usuarios.index');

    Route::get('/dashboard/branch/{branch}', function (string $branch) {
        return response()->json(['ok' => true, 'sucursal' => $branch]);
    })->middleware('branch.access')->name('dashboard.branch');
});
