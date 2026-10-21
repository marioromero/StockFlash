<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

class AdminSetupCommand extends Command
{
    protected $signature = 'admin:setup {--email= : Correo del administrador} {--password= : Contraseña del administrador}';

    protected $description = 'Ejecuta migraciones, seeders y crea el administrador inicial (DEV)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Solo entorno local.');
            return 1;
        }

        $this->call('migrate');
        $this->call('db:seed');

        $email = $this->option('email');
        $password = $this->option('password');

        if (!$email || !$password) {
            $this->error('Se requieren --email y --password.');
            return 1;
        }

        if (strlen($password) < 15) {
            $this->error('La contraseña debe tener al menos 15 caracteres.');
            return 1;
        }

        if (User::where('email', $email)->exists()) {
            $this->info("Ya existe un usuario con el correo {$email}.");
            return 0;
        }

        $adminRole = Role::where('code', 'central_admin')->first();
        if (!$adminRole) {
            $this->error('No existe el rol central_admin.');
            return 1;
        }

        User::create([
            'name' => 'Administrador inicial',
            'email' => $email,
            'password' => Hash::make($password),
            'global_role_id' => $adminRole->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->info("Administrador creado: {$email} (rol central_admin).");
        return 0;
    }
}

