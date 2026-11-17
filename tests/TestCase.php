<?php

namespace Tests;

use App\Models\User;
use App\Support\UserAccessFingerprint;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // SETUP-001: la suite existente representa una instalación ya
        // configurada. Solo se aplica si el flujo de migraciones ya creó la
        // tabla; las pruebas del asistente la revierten explícitamente.
        $this->markSetupCompleted();
    }

    /**
     * Fuerza el fixture de instalación completada (idempotente).
     */
    public function markSetupCompleted(): void
    {
        $this->writeSetupFlag('1');
    }

    /**
     * Fuerza el fixture de instalación pendiente.
     */
    public function markSetupIncomplete(): void
    {
        $this->writeSetupFlag('0');
    }

    /**
     * Los seeders de configuración resetean `is_setup_completed` a '0'. Tras
     * cada siembra se restablece el fixture de instalación configurada para
     * que las pruebas existentes sigan representando una instalación real.
     */
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder'): static
    {
        parent::seed($class);

        $this->markSetupCompleted();

        return $this;
    }

    private function writeSetupFlag(string $value): void
    {
        if (! Schema::hasTable('system_configs')) {
            return;
        }

        DB::table('system_configs')->updateOrInsert(
            ['config_key' => 'is_setup_completed'],
            [
                'config_value' => $value,
                'value_type' => 'bool',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Simula una sesión autenticada completa.
     *
     * `actingAs()` de Laravel no pasa por `LoginController`, por lo que no
     * guarda el fingerprint SEC-05 que `VerifySession` exige en cada petición.
     * Este helper lo siembra para que las pruebas existentes representen una
     * sesión real sin desactivar la verificación en producción.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User) {
            $this->session([
                UserAccessFingerprint::SESSION_KEY => UserAccessFingerprint::for($user),
            ]);
        }

        return $this;
    }
}
