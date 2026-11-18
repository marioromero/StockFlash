<?php

namespace Tests\Feature\Install;

use App\Support\Install\AtomicEnvSecretWriter;
use App\Support\Install\InstallDatabaseConfig;
use App\Support\Install\InstallSecretGenerator;
use Illuminate\Support\Str;
use RuntimeException;

class InstallSecretsTest extends InstallTestCase
{
    public function test_generador_usa_csprng_longitudes_y_formatos_esperados(): void
    {
        $generator = new InstallSecretGenerator;

        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $first['app_key']);
        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $first['backup_encryption_key']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['blind_index_key']);

        $decodedAppKey = base64_decode(substr($first['app_key'], 7), true);
        $decodedBackupKey = base64_decode(substr($first['backup_encryption_key'], 7), true);
        $this->assertIsString($decodedAppKey);
        $this->assertIsString($decodedBackupKey);
        $this->assertSame(32, strlen($decodedAppKey));
        $this->assertSame(32, strlen($decodedBackupKey));

        $this->assertNotSame($first['app_key'], $first['backup_encryption_key']);
        $this->assertNotSame($first['app_key'], $second['app_key']);
        $this->assertNotSame($first['blind_index_key'], $second['blind_index_key']);
        $this->assertNotSame($first['backup_encryption_key'], $second['backup_encryption_key']);

        $this->assertSame(36, strlen((string) Str::uuid()));
        $this->assertNotSame((string) Str::uuid(), (string) Str::uuid());
    }

    public function test_escritor_atomico_escribe_en_temporal_y_conserva_lineas_existentes(): void
    {
        $directory = $this->temporaryDirectory();
        $path = $directory.DIRECTORY_SEPARATOR.'.env.test';

        file_put_contents($path, "APP_NAME=StockFlash\nDB_HOST=host-antiguo\nDB_URL=mysql://antigua\nDB_SOCKET=/tmp/antiguo.sock\n# comentario\n");

        $database = new InstallDatabaseConfig('mariadb', 'db.temporal.test', 3306, 'sf_test', 'usuario', 'Clave "especial"!');
        $secrets = [
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'blind_index_key' => bin2hex(random_bytes(32)),
            'backup_encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
        ];

        try {
            $result = (new AtomicEnvSecretWriter($path))->persist($database, $secrets);

            $contents = (string) file_get_contents($path);

            $this->assertStringContainsString('APP_NAME=StockFlash', $contents);
            $this->assertStringContainsString('# comentario', $contents);
            $this->assertStringContainsString('APP_KEY="'.$secrets['app_key'].'"', $contents);
            $this->assertStringContainsString('BLIND_INDEX_KEY='.$secrets['blind_index_key'], $contents);
            $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY="'.$secrets['backup_encryption_key'].'"', $contents);
            $this->assertStringContainsString('DB_HOST=db.temporal.test', $contents);
            $this->assertStringContainsString('DB_PORT=3306', $contents);
            $this->assertStringContainsString('DB_DATABASE=sf_test', $contents);
            $this->assertStringContainsString('DB_USERNAME=usuario', $contents);
            $this->assertStringContainsString('DB_PASSWORD="Clave \\"especial\\"!"', $contents);
            $this->assertStringNotContainsString('DB_HOST=host-antiguo', $contents);
            $this->assertStringContainsString('DB_URL='.PHP_EOL, $contents);
            $this->assertStringContainsString('DB_SOCKET='.PHP_EOL, $contents);
            $this->assertStringNotContainsString('mysql://antigua', $contents);
            $this->assertStringNotContainsString('/tmp/antiguo.sock', $contents);
            $this->assertSame([], $result['preserved']);
            $this->assertArrayNotHasKey('lines', $result);
            $this->assertContains('APP_KEY', $result['written']);
            $this->assertContains('BLIND_INDEX_KEY', $result['written']);
            $this->assertContains('BACKUP_ENCRYPTION_KEY', $result['written']);

            $temporaryFiles = array_values(array_filter(
                scandir($directory) ?: [],
                fn (string $file): bool => $file !== '.' && $file !== '..' && $file !== '.env.test',
            ));
            $this->assertSame([], $temporaryFiles);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_escritor_preserva_byte_a_byte_las_claves_secretas_existentes(): void
    {
        $directory = $this->temporaryDirectory();
        $path = $directory.DIRECTORY_SEPARATOR.'.env.test';

        $existing = [
            'APP_KEY' => 'APP_KEY="base64:CLAVE-EXISTENTE-APP"',
            'BLIND_INDEX_KEY' => 'BLIND_INDEX_KEY=clave-existente-blind',
            'BACKUP_ENCRYPTION_KEY' => 'BACKUP_ENCRYPTION_KEY="base64:CLAVE-EXISTENTE-BACKUP"',
        ];

        file_put_contents($path, implode(PHP_EOL, $existing).PHP_EOL.'DB_HOST=host-antiguo'.PHP_EOL);

        $secrets = [
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'blind_index_key' => bin2hex(random_bytes(32)),
            'backup_encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
        ];

        try {
            $result = (new AtomicEnvSecretWriter($path))->persist(
                new InstallDatabaseConfig('mariadb', 'db.nuevo.test', 3306, 'base_nueva', 'usuario', 'ClaveFicticia123!'),
                $secrets,
            );

            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($existing as $line) {
                $this->assertContains($line, $lines);
            }

            $contents = (string) file_get_contents($path);
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $contents);
            }

            $this->assertSame(['APP_KEY', 'BLIND_INDEX_KEY', 'BACKUP_ENCRYPTION_KEY'], $result['preserved']);
            $this->assertContains('DB_HOST', $result['written']);
            $this->assertStringContainsString('DB_HOST=db.nuevo.test', $contents);
            $this->assertStringNotContainsString('DB_HOST=host-antiguo', $contents);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_escritor_escapa_interpolacion_dolar_en_password(): void
    {
        $directory = $this->temporaryDirectory();
        $path = $directory.DIRECTORY_SEPARATOR.'.env.test';

        try {
            (new AtomicEnvSecretWriter($path))->persist(
                new InstallDatabaseConfig('mariadb', 'host.test', 3306, 'base', 'usuario', 'p${SECRETO}$otro'),
                [
                    'app_key' => 'base64:'.base64_encode(random_bytes(32)),
                    'blind_index_key' => bin2hex(random_bytes(32)),
                    'backup_encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
                ],
            );

            $contents = (string) file_get_contents($path);

            $this->assertStringContainsString('DB_PASSWORD="p\${SECRETO}\$otro"', $contents);
            $this->assertStringNotContainsString('DB_PASSWORD="p${SECRETO}$otro"', $contents);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_escritor_considera_vacias_claves_con_comentario_en_la_misma_linea(): void
    {
        $directory = $this->temporaryDirectory();
        $path = $directory.DIRECTORY_SEPARATOR.'.env.test';

        file_put_contents(
            $path,
            "APP_KEY= # vacia\nBLIND_INDEX_KEY=\"\" # vacia\nBACKUP_ENCRYPTION_KEY=existente\n",
        );

        $secrets = [
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'blind_index_key' => bin2hex(random_bytes(32)),
            'backup_encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
        ];

        try {
            $result = (new AtomicEnvSecretWriter($path))->persist(
                new InstallDatabaseConfig('mariadb', 'host.test', 3306, 'base', 'usuario', 'ClaveFicticia123!'),
                $secrets,
            );

            $contents = (string) file_get_contents($path);

            $this->assertStringContainsString('APP_KEY="'.$secrets['app_key'].'"', $contents);
            $this->assertStringContainsString('BLIND_INDEX_KEY='.$secrets['blind_index_key'], $contents);
            $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY=existente', $contents);
            $this->assertSame(['BACKUP_ENCRYPTION_KEY'], $result['preserved']);
            $this->assertContains('APP_KEY', $result['written']);
            $this->assertContains('BLIND_INDEX_KEY', $result['written']);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_escritor_rechaza_valores_con_salto_de_linea(): void
    {
        $directory = $this->temporaryDirectory();
        $path = $directory.DIRECTORY_SEPARATOR.'.env.test';

        try {
            $this->expectException(RuntimeException::class);

            (new AtomicEnvSecretWriter($path))->persist(
                new InstallDatabaseConfig('mariadb', 'host.test', 3306, 'base', 'usuario', "linea1\nlinea2"),
                [
                    'app_key' => 'base64:'.base64_encode(random_bytes(32)),
                    'blind_index_key' => bin2hex(random_bytes(32)),
                    'backup_encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
                ],
            );
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_configuracion_de_bd_redacta_password_en_debug(): void
    {
        $database = new InstallDatabaseConfig('mariadb', 'host.test', 3306, 'base', 'usuario', 'ClaveSecreta123!');

        $debug = $database->__debugInfo();
        $this->assertSame('[redactado]', $debug['password']);
        $this->assertStringNotContainsString('ClaveSecreta123!', (string) json_encode($debug));
    }

    public function test_dont_flash_incluye_token_y_password_de_bd(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString("'bootstrap_token'", $bootstrap);
        $this->assertStringContainsString("'db_password'", $bootstrap);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sf-install-'.Str::uuid();
        mkdir($directory, 0700, true);

        return $directory;
    }
}
