<?php

namespace Tests\Feature\Install;

use App\Support\Install\InstallService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * B-02: con los drivers por defecto database, el instalador debe operar antes
 * de que exista el esquema usando tiendas dedicadas no dependientes de BD.
 */
class InstallDefaultStoresTest extends InstallTestCase
{
    public function test_install_carga_con_session_y_cache_database_sin_tablas(): void
    {
        $this->useFileInstallStores();
        config(['session.driver' => 'database', 'cache.default' => 'database']);

        $this->assertFalse(Schema::hasTable('sessions'));
        $this->assertFalse(Schema::hasTable('cache'));

        $this->get('/install')->assertOk()->assertInertia(
            fn ($page) => $page->component('Install/Wizard')->where('authorized', false)
        );

        // o-07: la configuración por request se restaura; no se filtra.
        $this->assertSame('database', config('session.driver'));
        $this->assertSame('database', config('cache.default'));
        $this->assertFalse((bool) config('install.runtime_storage_unavailable', false));
        $this->assertSame('file', config('cache.stores.'.config('install.cache.store').'.driver'));
    }

    public function test_rate_limit_usa_tienda_install_sin_tablas_de_cache(): void
    {
        $this->useFileInstallStores();
        config(['session.driver' => 'database', 'cache.default' => 'database']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/install/bootstrap', [
                'bootstrap_token' => 'token-incorrecto-'.$attempt,
            ])->assertRedirect();
        }

        $this->post('/install/bootstrap', [
            'bootstrap_token' => 'token-incorrecto-final',
        ])->assertStatus(429);

        $this->assertFalse(Schema::hasTable('cache'));
    }

    public function test_lock_usa_tienda_install_sin_tablas_de_cache(): void
    {
        $this->useFileInstallStores();
        config(['session.driver' => 'database', 'cache.default' => 'database']);

        $token = $this->passPreflight();
        $this->passConnection($token);

        $store = (string) config('install.cache.store');
        $lock = Cache::store($store)->lock(InstallService::LOCK_KEY, 60);
        $this->assertTrue($lock->get());

        try {
            $this->post('/install/run', $this->validRunPayload($token))->assertStatus(423);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, $this->migrator->migrateCalls);
        $this->assertSame(0, $this->installationInfoCount());
        $this->assertFalse(Schema::hasTable('cache_locks'));
    }

    public function test_install_deshabilitado_responde_404_aunque_la_bd_no_responda(): void
    {
        config(['install.enabled' => false]);

        $this->useBrokenDatabase();

        try {
            $this->get('/install')->assertNotFound();
            $this->post('/install/bootstrap', ['bootstrap_token' => self::BOOTSTRAP_TOKEN])->assertNotFound();
        } finally {
            $this->restoreDatabase();
        }
    }

    public function test_install_habilitado_con_bd_caida_muestra_wizard_controlado_no_500(): void
    {
        $this->useBrokenDatabase();

        try {
            $response = $this->get('/install');

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page
                    ->component('Install/Wizard')
                    ->where('state.code', 'DATABASE_UNAVAILABLE')
            );
        } finally {
            $this->restoreDatabase();
        }
    }

    private function useFileInstallStores(): void
    {
        $suffix = Str::uuid()->toString();
        $base = storage_path('framework/testing/install-'.$suffix);

        config([
            'install.session.driver' => 'file',
            'install.session.files' => $base.'/sessions',
            'install.cache.driver' => 'file',
            'install.cache.path' => $base.'/cache',
            'install.cache.lock_path' => $base.'/cache',
        ]);
    }

    private function useBrokenDatabase(): void
    {
        $this->originalDefaultConnection = config('database.default');

        config([
            'database.connections.install_broken' => [
                'driver' => 'sqlite',
                'database' => storage_path('framework/testing/no-existe-'.Str::uuid().'.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'install_broken',
        ]);

        DB::purge('install_broken');
    }

    private string $originalDefaultConnection = '';

    private function restoreDatabase(): void
    {
        config(['database.default' => $this->originalDefaultConnection]);
        DB::purge('install_broken');
    }
}
