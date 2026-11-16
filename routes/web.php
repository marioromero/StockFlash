<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'version' => 'Laravel ' . app()->version(),
    ]);
})->name('home');

// --- Manual HTML de usuario (DOCS-001) ---
// El contenido es autoría exclusiva de ChatGPT; OpenCode solo sirve y verifica.
Route::get('/docs/', fn () => response(
    file_get_contents(public_path('docs/index.html')),
    200,
    ['Content-Type' => 'text/html; charset=UTF-8']
))->name('docs.index');

// --- Autenticación (AUTH-001) ---

Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'store']);
Route::post('/logout', [\App\Http\Controllers\Auth\LogoutController::class, 'destroy'])->name('logout');

// Cambio de contraseña (autenticados; también verifica el fingerprint SEC-05)
Route::middleware(['auth', 'verify.session'])->group(function () {
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

    // --- Roadmap del propietario (ROADMAP-001) ---
    // HTML de autoría exclusiva de ChatGPT, fuera de `public/`.
    Route::get('/roadmap', fn () => response(
        file_get_contents(resource_path('roadmap/index.html')),
        200,
        [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]
    ))->middleware('can.permission:settings.manage')->name('roadmap.index');

    // --- Administración global de usuarios (USERS-001 · CORE-03) ---
    // Es administración global: NO usa `branch.access`.
    Route::middleware('can.permission:users.manage')->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])
            ->name('users.index');
        Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])
            ->name('users.create');
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])
            ->name('users.store');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])
            ->name('users.edit');
        Route::match(['put', 'patch'], '/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])
            ->name('users.update');
        Route::match(['post', 'patch'], '/users/{user}/activate', [\App\Http\Controllers\UserController::class, 'activate'])
            ->name('users.activate');
        Route::match(['post', 'patch'], '/users/{user}/deactivate', [\App\Http\Controllers\UserController::class, 'deactivate'])
            ->name('users.deactivate');
        Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])
            ->name('users.destroy');
    });

    // --- Ajustes del comprobante interno (CFG-001 · CORE-09) ---
    // Solo las tres claves de Parte 2 §3.1; autenticación y permiso real en
    // servidor. No usa `branch.access`: es configuración global.
    Route::middleware('can.permission:settings.manage')->group(function () {
        Route::get('/settings/receipt', [\App\Http\Controllers\Settings\ReceiptSettingsController::class, 'edit'])
            ->name('settings.receipt.edit');
        Route::put('/settings/receipt', [\App\Http\Controllers\Settings\ReceiptSettingsController::class, 'update'])
            ->name('settings.receipt.update');
    });

    // --- Administración global de sucursales (BRANCH-001 · CORE-05) ---
    // Es administración global: NO usa `branch.access`.
    Route::middleware('can.permission:settings.manage')->group(function () {
        Route::get('/branches', [\App\Http\Controllers\BranchController::class, 'index'])
            ->name('branches.index');
        Route::get('/branches/create', [\App\Http\Controllers\BranchController::class, 'create'])
            ->name('branches.create');
        Route::post('/branches', [\App\Http\Controllers\BranchController::class, 'store'])
            ->name('branches.store');
        Route::get('/branches/{branch}/edit', [\App\Http\Controllers\BranchController::class, 'edit'])
            ->name('branches.edit');
        Route::match(['put', 'patch'], '/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'update'])
            ->name('branches.update');
        Route::match(['post', 'patch'], '/branches/{branch}/deactivate', [\App\Http\Controllers\BranchController::class, 'deactivate'])
            ->name('branches.deactivate');
        Route::match(['post', 'patch'], '/branches/{branch}/activate', [\App\Http\Controllers\BranchController::class, 'activate'])
            ->name('branches.activate');
        Route::delete('/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'destroy'])
            ->name('branches.destroy');
    });
});
