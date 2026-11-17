<?php

namespace App\Support\Catalog;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Traduce violaciones UNIQUE reales de SQLite/MariaDB a errores 422 estables.
 *
 * La validación de Form Request cubre el flujo secuencial; este mapeo protege
 * ante carreras entre validación y escritura. Reconoce SQLSTATE 23000/23505,
 * vendor 1062 y los nombres de índice usados por SQLite y MariaDB.
 */
final class CatalogUniqueViolation
{
    public function rethrow(QueryException $exception): never
    {
        if (! $this->isUniqueConstraintViolation($exception)) {
            throw $exception;
        }

        $message = strtolower($exception->getMessage());

        $map = [
            [
                'needles' => ['products_internal_code_unique', 'products.internal_code'],
                'field' => 'internal_code',
                'text' => 'Ya existe un producto con ese código interno (incluidos los eliminados).',
            ],
            [
                'needles' => ['product_variants_sku_unique', 'product_variants.sku'],
                'field' => 'sku',
                'text' => 'Ya existe una variante con ese SKU (incluidas las eliminadas).',
            ],
            [
                'needles' => ['product_barcodes_barcode_unique', 'product_barcodes.barcode'],
                'field' => 'barcode',
                'text' => 'Ese código de barras ya está registrado en el catálogo.',
            ],
            [
                'needles' => ['product_tag_primary', 'product_tag.primary', 'product_tag.product_id'],
                'field' => 'tag_ids',
                'text' => 'La etiqueta ya está asociada al producto.',
            ],
            [
                'needles' => [
                    'product_variant_attribute_values_primary',
                    'product_variant_attribute_values.primary',
                    'product_variant_attribute_values.variant_id',
                ],
                'field' => 'attribute_value_ids',
                'text' => 'El valor de atributo ya está asociado a la variante.',
            ],
        ];

        foreach ($map as $entry) {
            foreach ($entry['needles'] as $needle) {
                if (str_contains($message, $needle)) {
                    throw ValidationException::withMessages([
                        $entry['field'] => $entry['text'],
                    ]);
                }
            }
        }

        throw $exception;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = $exception->errorInfo[1] ?? null;

        if (in_array($sqlState, ['23000', '23505'], true)) {
            return true;
        }

        return (int) $driverCode === 1062;
    }
}
