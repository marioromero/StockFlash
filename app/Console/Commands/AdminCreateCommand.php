<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea el administrador inicial en entorno DEV.
 *
 * Excepción de desarrollo: no marca el onboarding como completado, no
 * desactiva /setup, no usa credenciales predefinidas. Solo para desarrollo local.
 */
class AdminCreateCommand extends Command
{
    protected $signature = 'admin:create {--email= : Correo del administrador}
                            {--password= : Contraseña (se pide por consola si no se indica)}';

    protected $description = 'Crea el administrador inicial en entorno de desarrollo';

    public function handle(): int
    {
        if (! $this->isLocalEnvironment()) {
            $this->error('Este comando solo puede ejecutarse en entorno local de desarrollo.');
            return 1;
        }

        $email = $this->option('email') ?? $this->ask('Correo del administrador');
        $password = $this->option('password') ?? $this->secret('Contraseña del administrador (mínimo 15 caracteres)');

        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el correo {$email}.");
            return 1;
        }

        if (strlen($password) < 15) {
            $this->error('La contraseña debe tener al menos 15 caracteres.');
            return 1;
        }

        $adminRole = \App\Models\Role::where('code', 'central_admin')->first();
        if (! $adminRole) {
            $this->error('No existe el rol central_admin. Ejecute primero los seeders de DB-001.');
            return 1;
        }

        $user = User::create([
            'name' => 'Administrador inicial',
            'email' => $email,
            'password' => Hash::make($password),
            'global_role_id' => $adminRole->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->info("Administrador creado: {$user->email} (ID {$user->id}, rol central_admin).");
        $this->warn('Recuerde guardar la contraseña de forma segura. No se almacena en texto plano.');

        return 0;
    }

    protected function isLocalEnvironment(): bool
    {
        return app()->environment('local', 'testing');
    }
}
