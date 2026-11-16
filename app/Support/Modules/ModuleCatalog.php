<?php

namespace App\Support\Modules;

/**
 * Catálogo técnico único de módulos (Parte 1 §4.2 · CORE-06 / RN-67–68).
 *
 * Solo describe códigos y dependencias declaradas por el contrato. No concede
 * permisos RBAC, derechos comerciales ni capacidades de negocio: una fila
 * habilitada en `installation_modules` es únicamente estado técnico local.
 *
 * Las dependencias DURAS bloquean habilitar/deshabilitar. Las BLANDAS son
 * informativas y nunca se usan para bloquear. `reports_basic` declara `*` como
 * dependencia blanda informativa ("todos"), igual que el contrato.
 */
final class ModuleCatalog
{
    public const CORE = 'core';

    public const CHANNELS = 'channels';

    public const WOO_SYNC = 'woo_sync';

    /**
     * @var array<string, list<string>>
     */
    public const HARD_DEPENDENCIES = [
        'core' => [],
        'catalog' => ['core'],
        'inventory' => ['catalog'],
        'purchasing' => ['inventory'],
        'sales' => ['catalog'],
        'pos' => ['sales'],
        'cash_control' => ['sales'],
        'customers' => ['core'],
        'sellers' => ['core'],
        'promotions' => ['catalog'],
        'combos' => ['catalog'],
        'price_lists' => ['catalog'],
        'fiscal' => ['sales'],
        'channels' => ['catalog'],
        'woo_sync' => ['channels'],
        'reports_basic' => ['core'],
        'reports_advanced' => ['reports_basic'],
        'audit_log' => ['core'],
        'customer_credit' => ['customers', 'sales'],
        'loyalty' => ['customers', 'sales'],
        'offline' => ['pos'],
        'lots_serials' => ['inventory'],
    ];

    /**
     * @var array<string, list<string>>
     */
    public const SOFT_DEPENDENCIES = [
        'core' => [],
        'catalog' => [],
        'inventory' => [],
        'purchasing' => [],
        'sales' => ['inventory', 'customers', 'sellers', 'cash_control', 'channels'],
        'pos' => ['cash_control', 'customers', 'sellers'],
        'cash_control' => ['pos'],
        'customers' => [],
        'sellers' => ['sales'],
        'promotions' => ['sales'],
        'combos' => ['inventory'],
        'price_lists' => ['customers', 'channels'],
        'fiscal' => ['customers', 'channels'],
        'channels' => ['inventory', 'sales', 'customers'],
        'woo_sync' => [],
        'reports_basic' => ['*'],
        'reports_advanced' => [],
        'audit_log' => [],
        'customer_credit' => [],
        'loyalty' => [],
        'offline' => [],
        'lots_serials' => [],
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::HARD_DEPENDENCIES);
    }

    public static function isKnown(string $code): bool
    {
        return array_key_exists($code, self::HARD_DEPENDENCIES);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function hardDependencies(): array
    {
        return self::HARD_DEPENDENCIES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function softDependencies(): array
    {
        return self::SOFT_DEPENDENCIES;
    }

    /**
     * @return list<string>
     */
    public static function hardDependenciesOf(string $code): array
    {
        return self::HARD_DEPENDENCIES[$code] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function softDependenciesOf(string $code): array
    {
        return self::SOFT_DEPENDENCIES[$code] ?? [];
    }

    /**
     * Catálogo completo con ambas dependencias, apto para inspección/estado.
     *
     * @return array<string, array{hard: list<string>, soft: list<string>}>
     */
    public static function catalog(): array
    {
        $catalog = [];

        foreach (self::codes() as $code) {
            $catalog[$code] = [
                'hard' => self::hardDependenciesOf($code),
                'soft' => self::softDependenciesOf($code),
            ];
        }

        return $catalog;
    }
}
