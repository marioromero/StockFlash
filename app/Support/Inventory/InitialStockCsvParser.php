<?php

namespace App\Support\Inventory;

use InvalidArgumentException;

/**
 * Parser acotado del CSV de carga inicial de existencias (INVCSV-001 · INV-05).
 *
 * Formato aprobado:
 * - Encabezado exacto de dos columnas: `sku` y `quantity`/`cantidad`
 *   (sin distinguir mayúsculas; el BOM UTF-8 inicial se ignora).
 * - Separador detectado una sola vez sobre la primera línea: `;` si contiene
 *   punto y coma, en caso contrario `,` (Excel chileno con `;` incluido).
 * - UTF-8 válido con BOM opcional; CRLF o LF.
 * - Campos entre comillas dobles aceptados mediante `fgetcsv`; no se admiten
 *   comillas desbalanceadas ni saltos de línea dentro de un campo.
 * - Límites técnicos de denegación de servicio: 512 KB, 500 filas de datos,
 *   SKU de hasta 100 caracteres y cantidad de hasta 20 caracteres.
 *
 * Nunca se guarda una copia persistente del archivo: solo se lee el temporal
 * cargado por Laravel. Los errores devuelven línea, SKU seguro truncado y un
 * mensaje es-CL sin PII, rutas ni detalles de base de datos.
 */
final class InitialStockCsvParser
{
    public const MAX_FILE_BYTES = 524_288;

    public const MAX_DATA_ROWS = 500;

    public const MAX_SKU_LENGTH = 100;

    public const MAX_QUANTITY_LENGTH = 20;

    private const MAX_ERRORS = 500;

    private const SAFE_SKU_LENGTH = 60;

    /**
     * @return array{
     *     rows: list<array{line: int, sku: string, quantity: string}>,
     *     errors: list<array{line: int, sku: string, message: string}>,
     *     global_errors: list<string>
     * }
     */
    public function parse(string $path): array
    {
        $size = @filesize($path);

        if ($size === false) {
            return $this->failed('No se pudo leer el archivo CSV.');
        }

        if ($size === 0) {
            return $this->failed('El archivo CSV está vacío.');
        }

        if ($size > self::MAX_FILE_BYTES) {
            return $this->failed('El archivo CSV supera el máximo de 512 KB.');
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return $this->failed('No se pudo leer el archivo CSV.');
        }

        /** @var list<array{line: int, sku: string, quantity: string}> $rows */
        $rows = [];
        /** @var list<array{line: int, sku: string, message: string}> $errors */
        $errors = [];
        /** @var list<string> $globalErrors */
        $globalErrors = [];

        try {
            $firstLine = fgets($handle, self::MAX_FILE_BYTES + 1);

            if ($firstLine === false || trim($firstLine) === '') {
                return $this->failed('El archivo CSV está vacío.');
            }

            if (! mb_check_encoding($firstLine, 'UTF-8')) {
                return $this->failed('El archivo no está codificado en UTF-8 válido.');
            }

            $delimiter = str_contains($firstLine, ';') ? ';' : ',';

            if (rewind($handle) === false) {
                return $this->failed('No se pudo leer el archivo CSV.');
            }

            $physicalLine = 1;
            $isHeader = true;
            $dataRows = 0;

            while (true) {
                $offset = ftell($handle);

                if ($offset === false) {
                    $globalErrors[] = 'No se pudo leer el archivo CSV completo.';
                    break;
                }

                $record = fgetcsv($handle, 0, $delimiter, '"', '');

                if ($record === false) {
                    break;
                }

                $newOffset = ftell($handle);

                if ($newOffset === false || $newOffset <= $offset) {
                    $globalErrors[] = 'No se pudo leer el archivo CSV completo.';
                    break;
                }

                if (fseek($handle, $offset) !== 0) {
                    $globalErrors[] = 'No se pudo leer el archivo CSV completo.';
                    break;
                }

                $raw = fread($handle, $newOffset - $offset);

                if ($raw === false) {
                    $globalErrors[] = 'No se pudo leer el archivo CSV completo.';
                    break;
                }

                if (fseek($handle, $newOffset) !== 0) {
                    $globalErrors[] = 'No se pudo leer el archivo CSV completo.';
                    break;
                }

                $line = $physicalLine;
                $lineBreaks = preg_match_all("/\r\n|\n|\r/", $raw);
                $physicalLine = $line + max(1, (int) $lineBreaks);

                if (! mb_check_encoding($raw, 'UTF-8')) {
                    $globalErrors[] = 'El archivo no está codificado en UTF-8 válido.';
                    break;
                }

                $body = (string) preg_replace('/(?:\r\n|\n|\r)$/', '', $raw);
                $quoteCount = substr_count($body, '"');

                if ($isHeader) {
                    $isHeader = false;

                    if ($quoteCount % 2 !== 0 || strpbrk($body, "\r\n") !== false) {
                        return $this->failed('La fila de encabezado no tiene un formato CSV válido.');
                    }

                    if (count($record) !== 2) {
                        return $this->failed('El encabezado debe tener exactamente dos columnas: sku y quantity/cantidad.');
                    }

                    $firstHeader = (string) $record[0];

                    if (str_starts_with($firstHeader, "\xEF\xBB\xBF")) {
                        $firstHeader = substr($firstHeader, 3);
                    }

                    $headers = [
                        mb_strtolower(trim($firstHeader)),
                        mb_strtolower(trim((string) $record[1])),
                    ];

                    if ($headers[0] !== 'sku') {
                        return $this->failed('La primera columna del encabezado debe llamarse sku.');
                    }

                    if (! in_array($headers[1], ['quantity', 'cantidad'], true)) {
                        return $this->failed('La segunda columna del encabezado debe llamarse quantity o cantidad.');
                    }

                    continue;
                }

                $dataRows++;

                if ($dataRows > self::MAX_DATA_ROWS) {
                    $globalErrors[] = 'El archivo supera el máximo de 500 filas de datos.';
                    break;
                }

                if (count($errors) >= self::MAX_ERRORS) {
                    $globalErrors[] = 'El archivo contiene demasiados errores; corrija el archivo y vuelva a intentar.';
                    break;
                }

                if (count($record) === 1 && ($record[0] === null || trim((string) $record[0]) === '')) {
                    $errors[] = $this->error($line, '', 'La fila está vacía; elimine las líneas en blanco.');
                    continue;
                }

                if ($quoteCount % 2 !== 0) {
                    $errors[] = $this->error($line, '', 'La fila tiene comillas mal formadas.');
                    continue;
                }

                if (strpbrk($body, "\r\n") !== false) {
                    $errors[] = $this->error($line, '', 'No se admiten saltos de línea dentro de un campo entre comillas.');
                    continue;
                }

                if (count($record) !== 2) {
                    $errors[] = $this->error($line, '', 'La fila debe tener exactamente dos columnas: sku y cantidad.');
                    continue;
                }

                $sku = trim((string) $record[0]);
                $quantity = trim((string) $record[1]);

                if ($sku === '' && $quantity === '') {
                    $errors[] = $this->error($line, '', 'La fila está vacía; elimine las líneas en blanco.');
                    continue;
                }

                if ($sku === '') {
                    $errors[] = $this->error($line, '', 'El SKU es obligatorio en cada fila.');
                    continue;
                }

                $skuError = $this->validateSku($sku);

                if ($skuError !== null) {
                    $errors[] = $this->error($line, $sku, $skuError);
                    continue;
                }

                $quantityError = $this->validateQuantity($quantity);

                if ($quantityError !== null) {
                    $errors[] = $this->error($line, $sku, $quantityError);
                    continue;
                }

                $rows[] = [
                    'line' => $line,
                    'sku' => $sku,
                    'quantity' => InventoryDecimal::formatQuantity(InventoryDecimal::quantity($quantity)),
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($globalErrors === [] && $rows === [] && $errors === []) {
            $globalErrors[] = 'El archivo no contiene filas de datos.';
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'global_errors' => $globalErrors,
        ];
    }

    /**
     * SKU seguro para mensajes: sin caracteres de control y truncado.
     */
    public static function safeSku(?string $sku): string
    {
        $value = (string) $sku;
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
        $value = trim($value);

        return mb_substr($value, 0, self::SAFE_SKU_LENGTH);
    }

    private function validateSku(string $sku): ?string
    {
        if (mb_strlen($sku) > self::MAX_SKU_LENGTH) {
            return 'El SKU no puede superar 100 caracteres.';
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $sku) === 1) {
            return 'El SKU contiene caracteres de control no permitidos.';
        }

        if (preg_match('/^[=+\-@]/u', $sku) === 1) {
            return 'El SKU no puede comenzar con =, +, - ni @.';
        }

        return null;
    }

    private function validateQuantity(string $quantity): ?string
    {
        if (mb_strlen($quantity) > self::MAX_QUANTITY_LENGTH) {
            return 'La cantidad no puede superar 20 caracteres.';
        }

        if (preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $quantity) !== 1) {
            return 'La cantidad debe ser un decimal positivo con hasta 9 enteros y 3 decimales, sin comas ni exponentes.';
        }

        try {
            $scaled = InventoryDecimal::quantity($quantity);
        } catch (InvalidArgumentException) {
            return 'La cantidad excede el rango permitido.';
        }

        if ($scaled <= 0) {
            return 'La cantidad debe ser mayor que cero.';
        }

        return null;
    }

    /**
     * @return array{line: int, sku: string, message: string}
     */
    private function error(int $line, string $sku, string $message): array
    {
        return [
            'line' => $line,
            'sku' => self::safeSku($sku),
            'message' => $message,
        ];
    }

    /**
     * @return array{rows: list<array{line: int, sku: string, quantity: string}>, errors: list<array{line: int, sku: string, message: string}>, global_errors: list<string>}
     */
    private function failed(string $message): array
    {
        return [
            'rows' => [],
            'errors' => [],
            'global_errors' => [$message],
        ];
    }
}
