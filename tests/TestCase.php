<?php

namespace Tests;

use App\Models\User;
use App\Support\UserAccessFingerprint;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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
