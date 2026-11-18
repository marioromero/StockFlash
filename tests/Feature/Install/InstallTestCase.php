<?php

namespace Tests\Feature\Install;

use App\Support\Install\Contracts\InstallConnectionTester;
use App\Support\Install\Contracts\InstallDatabaseConfigurator;
use App\Support\Install\Contracts\InstallMigrator;
use App\Support\Install\Contracts\InstallPreflightChecker;
use App\Support\Install\Contracts\InstallSecretWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Install\Support\FakeConnectionTester;
use Tests\Feature\Install\Support\FakeDatabaseConfigurator;
use Tests\Feature\Install\Support\FakeMigrator;
use Tests\Feature\Install\Support\FakePreflightChecker;
use Tests\Feature\Install\Support\FakeSecretWriter;
use Tests\TestCase;

/**
 * Base de pruebas del instalador técnico (INSTALL-001 · CORE-17).
 *
 * Usa SQLite :memory: y dobles de conexión, migrador, preflight, escritor de
 * secretos y configurador. Nunca toca `.env`, una BD remota ni cron.
 */
abstract class InstallTestCase extends TestCase
{
    use RefreshDatabase;

    protected const BOOTSTRAP_TOKEN = 'test-bootstrap-token-0123456789abcdef0123456789abcdef';

    protected FakePreflightChecker $preflight;

    protected FakeConnectionTester $connection;

    protected FakeMigrator $migrator;

    protected FakeSecretWriter $secretWriter;

    protected FakeDatabaseConfigurator $configurator;

    protected function setUp(): void
    {
        parent::setUp();

        // El instalador técnico no marca `is_setup_completed`; el asistente
        // posterior debe seguir disponible para recibir el aviso de cron.
        $this->markSetupIncomplete();

        $installCachePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sf-install-cache-'.Str::uuid();

        config([
            'install.enabled' => true,
            'install.bootstrap_token' => self::BOOTSTRAP_TOKEN,
            'install.env_path' => null,
            'install.app_version' => '9.9.9',
            'install.release_channel' => 'stable',
            'install.lock_seconds' => 60,
            'install.rate_limit' => 5,
            'install.rate_decay_seconds' => 60,
            'install.session.driver' => 'array',
            'install.session.files' => storage_path('framework/testing/install-sessions'),
            'install.cache.store' => 'install',
            'install.cache.driver' => 'file',
            'install.cache.path' => $installCachePath,
            'install.cache.lock_path' => $installCachePath,
            'cache.stores.install' => [
                'driver' => 'file',
                'path' => $installCachePath,
                'lock_path' => $installCachePath,
            ],
        ]);

        $this->preflight = new FakePreflightChecker;
        $this->connection = new FakeConnectionTester;
        $this->migrator = new FakeMigrator;
        $this->secretWriter = new FakeSecretWriter;
        $this->configurator = new FakeDatabaseConfigurator;

        $this->app->instance(InstallPreflightChecker::class, $this->preflight);
        $this->app->instance(InstallConnectionTester::class, $this->connection);
        $this->app->instance(InstallMigrator::class, $this->migrator);
        $this->app->instance(InstallSecretWriter::class, $this->secretWriter);
        $this->app->instance(InstallDatabaseConfigurator::class, $this->configurator);
    }

    protected function authorizeBootstrap(): void
    {
        $this->post('/install/bootstrap', [
            'bootstrap_token' => self::BOOTSTRAP_TOKEN,
        ])->assertRedirect(route('install.show'));
    }

    protected function getFlowToken(): string
    {
        $this->authorizeBootstrap();

        return $this->flowTokenFromResponse($this->get('/install'));
    }

    protected function flowTokenFromResponse(TestResponse $response): string
    {
        $response->assertOk();

        $token = null;
        $response->assertInertia(function (Assert $page) use (&$token): void {
            $props = $page->toArray()['props'] ?? [];
            $token = $props['flow_token'] ?? null;
        });

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validDatabasePayload(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'mariadb',
            'host' => 'db.prueba.test',
            'port' => 3306,
            'database' => 'stockflash_prueba',
            'username' => 'sf_prueba',
            'db_password' => 'Clave-Ficticia-123!',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validRunPayload(string $token, array $overrides = []): array
    {
        return array_merge($this->validDatabasePayload(), [
            'install_token' => $token,
            'confirm_destination' => true,
            'confirmation_phrase' => 'INSTALAR',
        ], $overrides);
    }

    protected function passPreflight(): string
    {
        $token = $this->getFlowToken();

        $this->post('/install/preflight', [
            'install_token' => $token,
        ])->assertRedirect(route('install.show'));

        return $token;
    }

    protected function passConnection(string $token): void
    {
        $this->post('/install/connection', array_merge(
            ['install_token' => $token],
            $this->validDatabasePayload(),
        ))->assertRedirect(route('install.show'));
    }

    protected function makeInstalled(): void
    {
        $now = now();

        DB::table('installation_info')->insert([
            'installation_id' => (string) Str::uuid(),
            'app_version' => '1.0.0',
            'schema_version' => '2026_10_16_000020',
            'release_channel' => 'stable',
            'installed_at' => $now,
            'last_update_at' => null,
            'last_update_status' => 'none',
            'recovery_package_exported_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function makeExistingUser(): void
    {
        DB::table('users')->insert([
            'name' => 'Usuario Existente',
            'email' => 'existente@prueba.test',
            'password' => bcrypt('ContrasenaSegura123!'),
            'is_active' => 1,
            'must_change_password' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeExistingBranch(string $code = 'SUC-EXISTENTE'): void
    {
        DB::table('branches')->insert([
            'code' => $code,
            'name' => 'Sucursal Existente',
            'type' => 'store',
            'is_active' => 1,
            'requires_cash_control' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function installationInfoCount(): int
    {
        return DB::table('installation_info')->count();
    }

    protected function usersCount(): int
    {
        return DB::table('users')->count();
    }

    protected function branchesCount(): int
    {
        return DB::table('branches')->count();
    }

    protected function sessionJson(): string
    {
        return (string) json_encode(session()->all());
    }
}
