<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de la carga inicial por CSV (INVCSV-001).
 *
 * Solo se acepta el archivo. La sucursal viene de `{branch}`, el actor de la
 * sesión y el tipo/origen/saldos se resuelven exclusivamente en servidor; los
 * campos adicionales del cliente se ignoran.
 */
class InitialStockImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv,txt', 'max:512'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Debe seleccionar un archivo CSV.',
            'file.file' => 'El archivo cargado no es válido.',
            'file.mimes' => 'El archivo debe ser CSV o TXT válido.',
            'file.extensions' => 'La extensión del archivo debe ser .csv o .txt.',
            'file.max' => 'El archivo no puede superar 512 KB.',
        ];
    }
}
