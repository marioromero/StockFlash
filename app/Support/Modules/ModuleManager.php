<?php

namespace App\Support\Modules;

use App\Support\AuditLogger;
use App\Support\License\LicenseReadOnlyRule;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Motor interno de estado técnico de módulos (CORE-06 · RN-67–68).
 *
 * Solo administra el registro local `installation_modules` y su auditoría.
 * No expone rutas, UI ni permisos: una habilitación técnica NO concede
 * derechos comerciales, RBAC, endpoints ni funcionalidad de negocio.
 *
 * Toda mutación ocurre dentro de una transacción única:
 * 1. licencia local bloqueada primero (`license_states`, orden por `id`);
 * 2. regla de solo lectura reutilizada (`LicenseReadOnlyRule`);
 * 3. filas `installation_modules` bloqueadas con orden estable por
 *    `module_code` (solo códigos del catálogo conocido);
 * 4. actualizaciones de módulos + auditoría atómica. Si la auditoría falla,
 *    la transacción completa se revierte.
 */
final class ModuleManager
{
    public function __construct(private ?AuditLogger $auditLogger = null) {}

    /**
     * Listado del estado técnico de todos los códigos contractuales.
     *
     * @return list<array{
     *     code: string,
     *     is_enabled: bool,
     *     enabled_at: ?string,
     *     disabled_at: ?string,
     *     hard_dependencies: list<string>,
     *     soft_dependencies: list<string>
     * }>
     */
    public function states(): array
    {
        $rows = DB::table('installation_modules')
            ->whereIn('module_code', ModuleCatalog::codes())
            ->get()
            ->keyBy('module_code');

        $states = [];

        foreach (ModuleCatalog::codes() as $code) {
            $states[] = $this->stateFromRow($code, $rows->get($code));
        }

        return $states;
    }

    /**
     * Estado técnico de un código conocido.
     *
     * @return array{
     *     code: string,
     *     is_enabled: bool,
     *     enabled_at: ?string,
     *     disabled_at: ?string,
     *     hard_dependencies: list<string>,
     *     soft_dependencies: list<string>
     * }
     */
    public function state(string $code): array
    {
        $this->assertKnown($code);

        $row = DB::table('installation_modules')
            ->where('module_code', $code)
            ->first();

        return $this->stateFromRow($code, $row);
    }

    /**
     * Habilita un módulo si la licencia local lo incluye y sus dependencias
     * duras están activas. `woo_sync` puede habilitar `channels` en la misma
     * transacción solo si `channels` está licenciado y `catalog` está activo.
     *
     * @return array{code: string, changed_modules: list<string>}
     */
    public function enable(string $code): array
    {
        $this->assertKnown($code);

        // `core` es infraestructura base: siempre disponible y sin fila propia.
        if ($code === ModuleCatalog::CORE) {
            return ['code' => $code, 'changed_modules' => []];
        }

        return DB::transaction(function () use ($code): array {
            $now = now();

            $license = $this->lockLicense(required: true);
            $this->assertNotReadOnly($license);

            $licensed = $this->licensedModules($license);

            if (! $this->isLicensed($code, $licensed)) {
                throw new DomainException(
                    "El módulo {$code} no está incluido en la licencia local; habilitación bloqueada."
                );
            }

            $rows = $this->lockModuleRows();
            $enabled = $this->enabledCodes($rows);

            if (in_array($code, $enabled, true)) {
                return ['code' => $code, 'changed_modules' => []];
            }

            $toEnable = [$code];

            if ($code === ModuleCatalog::WOO_SYNC) {
                if (! $this->isLicensed(ModuleCatalog::CHANNELS, $licensed)) {
                    throw new DomainException(
                        'No se puede habilitar woo_sync: channels no está incluido en la licencia local.'
                    );
                }

                if (! in_array(ModuleCatalog::CHANNELS, $enabled, true)) {
                    $missingChannels = $this->missingHardDependencies(ModuleCatalog::CHANNELS, $enabled);

                    if ($missingChannels !== []) {
                        throw new DomainException(
                            'No se puede habilitar woo_sync: faltan dependencias duras activas para channels: '
                            .implode(', ', $missingChannels).'.'
                        );
                    }

                    $toEnable = [ModuleCatalog::CHANNELS, ModuleCatalog::WOO_SYNC];
                    $enabled[] = ModuleCatalog::CHANNELS;
                }
            }

            $missing = $this->missingHardDependencies($code, $enabled);

            if ($missing !== []) {
                throw new DomainException(
                    "No se puede habilitar {$code}: faltan dependencias duras activas: "
                    .implode(', ', $missing).'.'
                );
            }

            foreach ($toEnable as $module) {
                $row = $rows->get($module);

                if ($row === null) {
                    DB::table('installation_modules')->insert([
                        'module_code' => $module,
                        'is_enabled' => 1,
                        'enabled_at' => $now,
                        'disabled_at' => null,
                        'settings' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                DB::table('installation_modules')
                    ->where('module_code', $module)
                    ->update([
                        'is_enabled' => 1,
                        'enabled_at' => $now,
                        'disabled_at' => null,
                        // `settings` se conserva intacto.
                        'updated_at' => $now,
                    ]);
            }

            $this->audit('core.module_enabled', $code, $toEnable);

            return ['code' => $code, 'changed_modules' => $toEnable];
        });
    }

    /**
     * Deshabilita un módulo solo si ningún módulo habilitado depende duramente
     * de él, incluidos dependientes indirectos. Conserva datos y `settings`.
     *
     * @return array{code: string, changed_modules: list<string>}
     */
    public function disable(string $code): array
    {
        $this->assertKnown($code);

        if ($code === ModuleCatalog::CORE) {
            throw new DomainException(
                'El módulo core es infraestructura base y no se puede desactivar.'
            );
        }

        return DB::transaction(function () use ($code): array {
            $now = now();

            // Deshabilitar no exige inclusión en licencia, pero sí respeta la
            // regla de solo lectura cuando existe licencia suspendida.
            $license = $this->lockLicense(required: false);
            $this->assertNotReadOnly($license);

            $rows = $this->lockModuleRows();
            $enabled = $this->enabledCodes($rows);

            if (! in_array($code, $enabled, true)) {
                return ['code' => $code, 'changed_modules' => []];
            }

            $dependents = $this->enabledDependents($code, $enabled);

            if ($dependents !== []) {
                throw new DomainException(
                    "No se puede desactivar {$code}: módulos habilitados dependen de él: "
                    .implode(', ', $dependents).'.'
                );
            }

            DB::table('installation_modules')
                ->where('module_code', $code)
                ->update([
                    'is_enabled' => 0,
                    'disabled_at' => $now,
                    // `enabled_at` y `settings` se conservan.
                    'updated_at' => $now,
                ]);

            $this->audit('core.module_disabled', $code, [$code]);

            return ['code' => $code, 'changed_modules' => [$code]];
        });
    }

    /**
     * @return array{
     *     code: string,
     *     is_enabled: bool,
     *     enabled_at: ?string,
     *     disabled_at: ?string,
     *     hard_dependencies: list<string>,
     *     soft_dependencies: list<string>
     * }
     */
    private function stateFromRow(string $code, ?object $row): array
    {
        $isCore = $code === ModuleCatalog::CORE;

        return [
            'code' => $code,
            'is_enabled' => $isCore ? true : ((int) ($row->is_enabled ?? 0) === 1),
            'enabled_at' => $row?->enabled_at !== null ? (string) $row->enabled_at : null,
            'disabled_at' => $row?->disabled_at !== null ? (string) $row->disabled_at : null,
            'hard_dependencies' => ModuleCatalog::hardDependenciesOf($code),
            'soft_dependencies' => ModuleCatalog::softDependenciesOf($code),
        ];
    }

    private function assertKnown(string $code): void
    {
        if (! ModuleCatalog::isKnown($code)) {
            throw new DomainException("Módulo desconocido: {$code}.");
        }
    }

    /**
     * Bloquea la fila local de licencia primero (orden estable por `id`).
     *
     * Solo selecciona columnas de decisión: nunca `installation_key`,
     * `signed_payload` ni `signature`.
     */
    private function lockLicense(bool $required): ?object
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['status', 'grace_until', 'modules_enabled']);

        if ($license === null && $required) {
            throw new DomainException(
                'No hay licencia local configurada; habilitación de módulos bloqueada.'
            );
        }

        return $license;
    }

    private function assertNotReadOnly(?object $license): void
    {
        if (LicenseReadOnlyRule::isReadOnly($license)) {
            throw new DomainException(
                'La licencia está suspendida fuera del período de gracia: el sistema está en solo lectura y los cambios de módulos están bloqueados.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function licensedModules(?object $license): array
    {
        if ($license === null) {
            throw new DomainException(
                'No hay licencia local configurada; habilitación de módulos bloqueada.'
            );
        }

        $raw = $license->modules_enabled ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = null;
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new DomainException(
                'La lista local de módulos licenciados es inválida; habilitación bloqueada.'
            );
        }

        foreach ($decoded as $module) {
            if (! is_string($module) || $module === '') {
                throw new DomainException(
                    'La lista local de módulos licenciados es inválida; habilitación bloqueada.'
                );
            }
        }

        /** @var list<string> $decoded */
        return $decoded;
    }

    /**
     * @param  list<string>  $licensed
     */
    private function isLicensed(string $code, array $licensed): bool
    {
        return in_array('*', $licensed, true) || in_array($code, $licensed, true);
    }

    /**
     * Filas relevantes bloqueadas con orden estable por `module_code`.
     *
     * @return Collection<string, object>
     */
    private function lockModuleRows(): Collection
    {
        return DB::table('installation_modules')
            ->whereIn('module_code', ModuleCatalog::codes())
            ->orderBy('module_code')
            ->lockForUpdate()
            ->get()
            ->keyBy('module_code');
    }

    /**
     * @param  Collection<string, object>  $rows
     * @return list<string>
     */
    private function enabledCodes(Collection $rows): array
    {
        $enabled = [ModuleCatalog::CORE];

        foreach (ModuleCatalog::codes() as $code) {
            if ($code === ModuleCatalog::CORE) {
                continue;
            }

            $row = $rows->get($code);

            if ($row !== null && (int) $row->is_enabled === 1) {
                $enabled[] = $code;
            }
        }

        return $enabled;
    }

    /**
     * Cierre transitivo de dependencias duras faltantes para un código.
     *
     * @param  list<string>  $enabled
     * @return list<string>
     */
    private function missingHardDependencies(string $code, array $enabled): array
    {
        $missing = [];
        $visited = [];
        $queue = ModuleCatalog::hardDependenciesOf($code);

        while ($queue !== []) {
            $dependency = array_shift($queue);

            if (isset($visited[$dependency])) {
                continue;
            }

            $visited[$dependency] = true;

            if (! in_array($dependency, $enabled, true)) {
                $missing[$dependency] = true;

                continue;
            }

            foreach (ModuleCatalog::hardDependenciesOf($dependency) as $next) {
                $queue[] = $next;
            }
        }

        return array_keys($missing);
    }

    /**
     * Dependientes habilitados directos e indirectos de un código objetivo.
     *
     * Se recorre el grafo inverso de dependencias duras; solo se informan los
     * módulos que están efectivamente habilitados.
     *
     * @param  list<string>  $enabled
     * @return list<string>
     */
    private function enabledDependents(string $code, array $enabled): array
    {
        $dependents = [];
        $visited = [$code => true];
        $queue = [$code];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach (ModuleCatalog::codes() as $candidate) {
                if (isset($visited[$candidate])) {
                    continue;
                }

                if (! in_array($current, ModuleCatalog::hardDependenciesOf($candidate), true)) {
                    continue;
                }

                $visited[$candidate] = true;

                if (in_array($candidate, $enabled, true)) {
                    $dependents[] = $candidate;
                }

                $queue[] = $candidate;
            }
        }

        sort($dependents);

        return $dependents;
    }

    /**
     * Auditoría atómica: se invoca dentro de la transacción de la operación.
     *
     * @param  list<string>  $changedModules
     */
    private function audit(string $event, string $module, array $changedModules): void
    {
        $this->auditLogger()->record(
            $event,
            'installation_modules',
            null,
            null,
            null,
            [
                'module' => $module,
                'changed_modules' => array_values($changedModules),
                'actor' => Auth::id(),
            ],
            'core',
        );
    }

    private function auditLogger(): AuditLogger
    {
        return $this->auditLogger ??= app(AuditLogger::class);
    }
}
