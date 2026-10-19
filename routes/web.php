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
Route::middleware(['auth', 'password.change'])->group(function () {
    Route::get('/dashboard', function () {
        return inertia('Dashboard');
    })->name('dashboard');
});
