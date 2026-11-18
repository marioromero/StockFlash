<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallPreflightChecker;

/**
 * Verificación previa real del entorno de hosting compartido.
 *
 * No ejecuta comandos de shell ni lee `.env`. Solo comprueba versión de PHP,
 * extensiones, archivos del proyecto y permisos de escritura. Las pruebas
 * inyectan valores falsos mediante el constructor.
 */
final class DefaultInstallPreflightChecker implements InstallPreflightChecker
{
    /**
     * @param  array<string, bool>|null  $extensions  Override de extensiones (nombre => disponible).
     * @param  array<string, bool>|null  $files  Override de archivos (ruta relativa => existe).
     * @param  array<string, bool>|null  $paths  Override de rutas (ruta relativa => escribible).
     */
    public function __construct(
        private ?string $phpVersion = null,
        private ?array $extensions = null,
        private ?array $files = null,
        private ?array $paths = null,
        private ?string $minimumPhpVersion = null,
    ) {}

    public function check(): array
    {
        $checks = [];

        $minimum = $this->minimumPhpVersion ?? (string) config('install.minimum_php_version', '8.3.0');
        $version = $this->phpVersion ?? PHP_VERSION;
        $versionOk = version_compare($version, $minimum, '>=');

        $checks[] = [
            'id' => 'php_version',
            'label' => 'Versión de PHP',
            'status' => $versionOk ? 'ok' : 'fail',
            'detail' => $versionOk
                ? 'PHP '.$version.' cumple el mínimo requerido.'
                : 'Se requiere PHP '.$minimum.' o superior; versión detectada: '.$version.'.',
        ];

        $extensions = $this->extensions ?? $this->defaultExtensions();
        $missing = [];

        foreach ((array) config('install.required_extensions', []) as $extension) {
            $available = $extensions[$extension] ?? extension_loaded($extension);

            if (! $available) {
                $missing[] = $extension;
            }
        }

        $checks[] = [
            'id' => 'extensions',
            'label' => 'Extensiones de PHP',
            'status' => $missing === [] ? 'ok' : 'fail',
            'detail' => $missing === []
                ? 'Todas las extensiones requeridas están disponibles.'
                : 'Faltan extensiones requeridas: '.implode(', ', $missing).'.',
        ];

        $files = $this->files ?? $this->defaultFiles();
        $missingFiles = [];

        foreach ((array) config('install.required_files', []) as $file) {
            $exists = $files[$file] ?? is_file(base_path($file));

            if (! $exists) {
                $missingFiles[] = $file;
            }
        }

        $checks[] = [
            'id' => 'dependencies',
            'label' => 'Dependencias del proyecto',
            'status' => $missingFiles === [] ? 'ok' : 'fail',
            'detail' => $missingFiles === []
                ? 'Los archivos base del proyecto están presentes.'
                : 'Faltan archivos del proyecto: '.implode(', ', $missingFiles).'.',
        ];

        $paths = $this->paths ?? $this->defaultPaths();
        $notWritable = [];

        foreach ((array) config('install.writable_paths', []) as $path) {
            $writable = $paths[$path] ?? is_writable(base_path($path));

            if (! $writable) {
                $notWritable[] = $path;
            }
        }

        if ((bool) config('install.runtime_storage_unavailable', false)) {
            $notWritable[] = 'tiendas temporales del instalador (sesión/caché)';
        }

        $checks[] = [
            'id' => 'writable',
            'label' => 'Permisos de escritura',
            'status' => $notWritable === [] ? 'ok' : 'fail',
            'detail' => $notWritable === []
                ? 'Las rutas de escritura requeridas están disponibles.'
                : 'Sin permiso de escritura en: '.implode(', ', $notWritable).'.',
        ];

        $ok = true;

        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $ok = false;
            }
        }

        return ['ok' => $ok, 'checks' => $checks];
    }

    /**
     * @return array<string, bool>
     */
    private function defaultExtensions(): array
    {
        $values = [];

        foreach ((array) config('install.required_extensions', []) as $extension) {
            $values[$extension] = extension_loaded($extension);
        }

        return $values;
    }

    /**
     * @return array<string, bool>
     */
    private function defaultFiles(): array
    {
        $values = [];

        foreach ((array) config('install.required_files', []) as $file) {
            $values[$file] = is_file(base_path($file));
        }

        return $values;
    }

    /**
     * @return array<string, bool>
     */
    private function defaultPaths(): array
    {
        $values = [];

        foreach ((array) config('install.writable_paths', []) as $path) {
            $values[$path] = is_writable(base_path($path));
        }

        return $values;
    }
}
