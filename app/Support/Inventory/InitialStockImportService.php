<?php

namespace App\Support\Inventory;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Vista previa y commit atómico del CSV de carga inicial (INVCSV-001 · INV-05).
 *
 * El commit NUNCA confía en la vista previa enviada por el cliente: vuelve a
 * leer los bytes del archivo recién subido y revalida contra el estado actual
 * de la base. Además, toda la importación corre en una única transacción
 * externa: licencia, filas de `current_stock` bloqueadas en orden ascendente,
 * revalidación de elegibilidad/historial bajo lock y, por cada SKU, el camino
 * autoritativo `StockWriteService::loadInitial` → `StockLedger` (tipo
 * `initial`, actor de sesión). Cualquier error revierte todo el lote y sus
 * auditorías. Nunca se escribe directamente `current_stock`/`stock_movements`
 * ni se usa `adjustment`.
 */
final class InitialStockImportService
{
    public function __construct(
        private readonly InitialStockCsvParser $parser,
        private readonly StockWriteService $writes,
        private readonly InventoryWriteGuard $guard,
    ) {}

    /**
     * @return array{
     *     rows: list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string}>,
     *     totals: array{valid: int, invalid: int, total_quantity: ?string},
     *     global_errors: list<string>,
     *     has_errors: bool
     * }
     */
    public function preview(int $branchId, UploadedFile $file): array
    {
        return $this->analyse($branchId, $file)['preview'];
    }

    /**
     * @return array{count: int, total: string}
     */
    public function commit(int $branchId, UploadedFile $file, int $actorUserId): array
    {
        $analysis = $this->analyse($branchId, $file);
        $preview = $analysis['preview'];
        $items = $analysis['items'];

        if ($preview['has_errors'] || $items === []) {
            throw $this->summaryException($preview);
        }

        return DB::transaction(function () use ($branchId, $actorUserId, $items): array {
            // Licencia primero, igual que el ledger; dentro de la transacción
            // externa para que un cambio a solo lectura revierta el lote.
            $this->guard->assertWritable();

            // Orden ascendente por variante: el ledger toma el lock físico de
            // cada (branch_id, variant_id) en este mismo orden determinista.
            // No se escribe directamente current_stock/stock_movements: toda
            // creación y lock ocurre dentro de StockWriteService/StockLedger.
            usort($items, static function (array $left, array $right): int {
                return ($left['variant_id'] <=> $right['variant_id'])
                    ?: ($left['line'] <=> $right['line']);
            });

            $count = 0;
            $total = 0;

            foreach ($items as $item) {
                $this->writes->loadInitial(
                    $branchId,
                    (int) $item['variant_id'],
                    (string) $item['quantity'],
                    $actorUserId,
                );

                $count++;

                try {
                    $total = InventoryDecimal::addQuantity(
                        $total,
                        InventoryDecimal::quantity((string) $item['quantity']),
                    );
                } catch (InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'csv' => 'No se importó nada. La cantidad total del archivo excede el rango permitido.',
                    ]);
                }
            }

            return [
                'count' => $count,
                'total' => InventoryDecimal::formatQuantity($total),
            ];
        }, 3);
    }

    /**
     * @return array{
     *     preview: array{
     *         rows: list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string}>,
     *         totals: array{valid: int, invalid: int, total_quantity: ?string},
     *         global_errors: list<string>,
     *         has_errors: bool
     *     },
     *     items: list<array{line: int, sku: string, variant_id: int, quantity: string}>
     * }
     */
    private function analyse(int $branchId, UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return [
                'preview' => $this->emptyPreview(['No se pudo leer el archivo cargado.']),
                'items' => [],
            ];
        }

        $parsed = $this->parser->parse($path);
        $globalErrors = $parsed['global_errors'];

        if ($globalErrors !== []) {
            return [
                'preview' => $this->emptyPreview($globalErrors),
                'items' => [],
            ];
        }

        /** @var list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string, variant_id: ?int}> $entries */
        $entries = [];

        foreach ($parsed['rows'] as $row) {
            $entries[] = [
                'line' => $row['line'],
                'sku' => $row['sku'],
                'product' => null,
                'variant' => null,
                'quantity' => $row['quantity'],
                'status' => 'ok',
                'message' => null,
                'variant_id' => null,
            ];
        }

        foreach ($parsed['errors'] as $error) {
            $entries[] = [
                'line' => $error['line'],
                'sku' => $error['sku'],
                'product' => null,
                'variant' => null,
                'quantity' => null,
                'status' => 'error',
                'message' => $error['message'],
                'variant_id' => null,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => $left['line'] <=> $right['line']);

        // Duplicados dentro del mismo archivo: la primera aparición sigue
        // siendo evaluable; las siguientes se rechazan.
        $seenSkus = [];
        $duplicateLines = [];

        foreach ($parsed['rows'] as $row) {
            $key = mb_strtolower($row['sku']);

            if (isset($seenSkus[$key])) {
                $duplicateLines[$row['line']] = true;
                $this->markError(
                    $entries,
                    $row['line'],
                    'El SKU está duplicado en el archivo; ya aparece en la línea '.$seenSkus[$key].'.',
                );

                continue;
            }

            $seenSkus[$key] = $row['line'];
        }

        $uniqueSkus = [];

        foreach ($parsed['rows'] as $row) {
            if (! isset($duplicateLines[$row['line']])) {
                $uniqueSkus[mb_strtolower($row['sku'])] = $row['sku'];
            }
        }

        /** @var array<string, list<object>> $variantsBySku */
        $variantsBySku = [];

        if ($uniqueSkus !== []) {
            $variants = DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->whereIn('pv.sku', array_values($uniqueSkus))
                ->get([
                    'pv.id',
                    'pv.sku',
                    'pv.is_active as variant_active',
                    'pv.deleted_at as variant_deleted_at',
                    'p.id as product_id',
                    'p.name as product_name',
                    'p.is_active as product_active',
                    'p.tracks_stock',
                    'p.type as product_type',
                    'p.deleted_at as product_deleted_at',
                ]);

            foreach ($variants as $variant) {
                $variantsBySku[(string) $variant->sku][] = $variant;
            }
        }

        $variantIdByLine = [];

        foreach ($parsed['rows'] as $row) {
            if (isset($duplicateLines[$row['line']])) {
                continue;
            }

            $matches = $variantsBySku[$row['sku']] ?? [];

            if ($matches === []) {
                $this->markError($entries, $row['line'], 'El SKU no existe en el catálogo de variantes.');

                continue;
            }

            if (count($matches) > 1) {
                $this->markError($entries, $row['line'], 'El SKU coincide con más de una variante; corrija el catálogo antes de importar.');

                continue;
            }

            $variant = $matches[0];
            $this->setLabels($entries, $row['line'], (string) $variant->product_name, (string) $variant->sku);

            if (
                $variant->variant_deleted_at !== null
                || $variant->product_deleted_at !== null
                || (int) $variant->variant_active !== 1
                || (int) $variant->product_active !== 1
                || (int) $variant->tracks_stock !== 1
                || ! in_array((string) $variant->product_type, ['simple', 'variable'], true)
            ) {
                $this->markError($entries, $row['line'], 'La variante no está activa, no controla existencias físicas o su producto no admite carga inicial.');

                continue;
            }

            $variantIdByLine[$row['line']] = (int) $variant->id;
        }

        if ($variantIdByLine !== []) {
            $initialized = DB::table('stock_movements')
                ->where('branch_id', $branchId)
                ->whereIn('variant_id', array_values(array_unique($variantIdByLine)))
                ->distinct()
                ->pluck('variant_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $initialized = array_fill_keys($initialized, true);

            foreach ($variantIdByLine as $line => $variantId) {
                if (isset($initialized[$variantId])) {
                    $this->markError(
                        $entries,
                        $line,
                        'La variante ya tiene historial de movimientos en esta sucursal; no admite carga inicial.',
                    );
                }
            }
        }

        $items = [];
        $total = 0;
        $valid = 0;
        $invalid = 0;

        foreach ($entries as $entry) {
            $variantId = $entry['status'] === 'ok'
                ? ($variantIdByLine[$entry['line']] ?? null)
                : null;

            if ($entry['status'] !== 'ok' || $variantId === null) {
                $invalid++;

                continue;
            }

            $valid++;
            $items[] = [
                'line' => $entry['line'],
                'sku' => (string) $entry['sku'],
                'variant_id' => $variantId,
                'quantity' => (string) $entry['quantity'],
            ];

            if ($total === null) {
                continue;
            }

            try {
                $total = InventoryDecimal::addQuantity(
                    $total,
                    InventoryDecimal::quantity((string) $entry['quantity']),
                );
            } catch (InvalidArgumentException) {
                $total = null;
                $globalErrors[] = 'La cantidad total del archivo excede el rango permitido; divida la importación en archivos más pequeños.';
            }
        }

        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'line' => $entry['line'],
                'sku' => $entry['sku'],
                'product' => $entry['product'],
                'variant' => $entry['variant'],
                'quantity' => $entry['status'] === 'ok' ? $entry['quantity'] : null,
                'status' => $entry['status'],
                'message' => $entry['message'],
            ];
        }

        return [
            'preview' => [
                'rows' => $rows,
                'totals' => [
                    'valid' => $valid,
                    'invalid' => $invalid,
                    'total_quantity' => $total === null ? null : InventoryDecimal::formatQuantity($total),
                ],
                'global_errors' => $globalErrors,
                'has_errors' => $globalErrors !== [] || $invalid > 0 || $valid === 0,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string, variant_id: ?int}>  $entries
     */
    private function markError(array &$entries, int $line, string $message): void
    {
        foreach ($entries as $index => $entry) {
            if ($entry['line'] === $line) {
                $entries[$index]['status'] = 'error';
                $entries[$index]['message'] = $message;
                $entries[$index]['quantity'] = null;
                $entries[$index]['variant_id'] = null;

                return;
            }
        }

        $entries[] = [
            'line' => $line,
            'sku' => '',
            'product' => null,
            'variant' => null,
            'quantity' => null,
            'status' => 'error',
            'message' => $message,
            'variant_id' => null,
        ];
    }

    /**
     * @param  list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string, variant_id: ?int}>  $entries
     */
    private function setLabels(array &$entries, int $line, string $product, string $variant): void
    {
        foreach ($entries as $index => $entry) {
            if ($entry['line'] === $line) {
                $entries[$index]['product'] = mb_substr($product, 0, 255);
                $entries[$index]['variant'] = mb_substr($variant, 0, 100);

                return;
            }
        }
    }

    /**
     * @param  list<string>  $globalErrors
     * @return array{
     *     rows: list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string}>,
     *     totals: array{valid: int, invalid: int, total_quantity: ?string},
     *     global_errors: list<string>,
     *     has_errors: bool
     * }
     */
    private function emptyPreview(array $globalErrors): array
    {
        return [
            'rows' => [],
            'totals' => [
                'valid' => 0,
                'invalid' => 0,
                'total_quantity' => '0.000',
            ],
            'global_errors' => array_values($globalErrors),
            'has_errors' => true,
        ];
    }

    /**
     * @param  array{rows: list<array{line: int, sku: string, product: ?string, variant: ?string, quantity: ?string, status: string, message: ?string}>, totals: array{valid: int, invalid: int, total_quantity: ?string}, global_errors: list<string>, has_errors: bool}  $preview
     */
    private function summaryException(array $preview): ValidationException
    {
        $parts = [];

        foreach (array_slice($preview['global_errors'], 0, 2) as $message) {
            $parts[] = $message;
        }

        $rowErrors = array_values(array_filter(
            $preview['rows'],
            static fn (array $row): bool => $row['status'] === 'error',
        ));

        foreach (array_slice($rowErrors, 0, 5) as $row) {
            $parts[] = 'Línea '.$row['line'].': '.$row['message'];
        }

        if ($parts === []) {
            $parts[] = 'El archivo no contiene filas válidas para importar.';
        }

        return ValidationException::withMessages([
            'csv' => 'No se importó nada. '.implode(' ', $parts),
        ]);
    }
}
