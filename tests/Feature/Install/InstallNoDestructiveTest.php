<?php

namespace Tests\Feature\Install;

class InstallNoDestructiveTest extends InstallTestCase
{
    public function test_codigo_del_instalador_no_contiene_operaciones_destructivas_ni_shell(): void
    {
        $files = array_merge(
            [base_path('app/Http/Controllers/InstallController.php')],
            glob(base_path('app/Http/Requests/Install/*.php')) ?: [],
            glob(base_path('app/Support/Install/*.php')) ?: [],
            glob(base_path('app/Support/Install/Contracts/*.php')) ?: [],
        );

        $this->assertNotEmpty($files);

        $contents = '';

        foreach ($files as $file) {
            $contents .= "\n".(string) file_get_contents($file);
        }

        $forbidden = [
            'migrate:fresh',
            'db:wipe',
            'db:reset',
            '->truncate(',
            '->delete(',
            'dropIfExists',
            'Schema::drop',
            'shell_exec',
            'proc_open',
            'passthru',
            'system(',
            'exec(',
        ];

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString($needle, $contents, "Operación prohibida detectada: {$needle}");
        }

        // Los únicos borrados permitidos son los temporales del escritor atómico.
        $this->assertStringContainsString('migrate', $contents);
        $this->assertStringContainsString('db:seed', $contents);
        $this->assertStringContainsString('random_bytes', $contents);
        $this->assertStringContainsString('hash_equals', $contents);
        $this->assertStringContainsString('Cache::store', $contents);
        $this->assertStringContainsString('->lock(', $contents);
        $this->assertStringContainsString('Str::uuid', $contents);
    }

    public function test_rutas_no_contienen_endpoints_get_de_migracion(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));

        foreach ([
            "Route::get('/install/run'",
            "Route::match(['get', 'post'], '/install/run'",
            "Route::any('/install/run'",
            "Route::get('/install/preflight'",
            "Route::get('/install/connection'",
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $routes);
        }

        $this->assertStringContainsString("Route::post('/install/run'", $routes);
        $this->assertStringContainsString("->name('install.run')", $routes);
    }

    public function test_el_instalador_no_crea_usuarios_ni_sucursales(): void
    {
        $token = $this->passPreflight();
        $this->passConnection($token);

        $this->post('/install/run', $this->validRunPayload($token))->assertRedirect(route('setup.show'));

        $this->assertSame(1, $this->installationInfoCount());
        $this->assertSame(0, $this->usersCount());
        $this->assertSame(0, $this->branchesCount());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('roles')->where('code', 'central_admin')->count());
    }
}
