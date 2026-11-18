<?php

namespace Tests\Feature\Install;

use App\Support\Install\Contracts\InstallPreflightChecker;
use App\Support\Install\DefaultInstallPreflightChecker;
use App\Support\Install\InstallService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * M-05: si las tiendas file del instalador no se pueden crear, la respuesta
 * debe ser controlada (200 con preflight fallido / 404 deshabilitado), nunca 500.
 */
class InstallStorageFailureTest extends InstallTestCase
{
    public function test_storage_no_escribible_no_produce_500_y_el_preflight_falla(): void
    {
        $blocker = $this->createFileBlocker();

        config([
            'install.session.driver' => 'file',
            'install.session.files' => $blocker.DIRECTORY_SEPARATOR.'sessions',
            'install.cache.driver' => 'file',
            'install.cache.path' => $blocker.DIRECTORY_SEPARATOR.'cache',
            'install.cache.lock_path' => $blocker.DIRECTORY_SEPARATOR.'cache',
        ]);

        try {
            $this->app->instance(InstallPreflightChecker::class, new DefaultInstallPreflightChecker);

            $this->get('/install')->assertOk()->assertInertia(
                fn (Assert $page) => $page
                    ->component('Install/Wizard')
                    ->where('storage_warning', true)
            );

            $this->post('/install/bootstrap', [
                'bootstrap_token' => self::BOOTSTRAP_TOKEN,
            ])->assertRedirect(route('install.show'));

            $token = $this->flowTokenFromResponse($this->get('/install'));

            $this->from('/install')->post('/install/preflight', [
                'install_token' => $token,
            ])->assertRedirect('/install')->assertSessionHasErrors('preflight');

            $stored = session(InstallService::SESSION_PREFLIGHT);
            $this->assertIsArray($stored);
            $writable = collect($stored['checks'])->firstWhere('id', 'writable');
            $this->assertIsArray($writable);
            $this->assertSame('fail', $writable['status']);
            $this->assertStringContainsString('tiendas temporales', $writable['detail']);
        } finally {
            config(['install.enabled' => true]);
            @unlink($blocker);
        }
    }

    public function test_instalador_deshabilitado_sigue_dando_404_con_storage_roto(): void
    {
        $blocker = $this->createFileBlocker();

        config([
            'install.enabled' => false,
            'install.session.driver' => 'file',
            'install.session.files' => $blocker.DIRECTORY_SEPARATOR.'sessions',
            'install.cache.driver' => 'file',
            'install.cache.path' => $blocker.DIRECTORY_SEPARATOR.'cache',
            'install.cache.lock_path' => $blocker.DIRECTORY_SEPARATOR.'cache',
        ]);

        try {
            $this->get('/install')->assertNotFound();
            $this->post('/install/bootstrap', [
                'bootstrap_token' => self::BOOTSTRAP_TOKEN,
            ])->assertNotFound();
        } finally {
            config(['install.enabled' => true]);
            @unlink($blocker);
        }
    }

    private function createFileBlocker(): string
    {
        $path = storage_path('framework/testing/install-storage-blocker-'.Str::uuid());

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, 'no es un directorio');

        return $path;
    }
}
