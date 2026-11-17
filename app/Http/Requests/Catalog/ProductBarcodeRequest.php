<?php

namespace App\Http\Requests\Catalog;

use App\Models\ProductBarcode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist de código de barras. La unicidad global de `barcode` se valida en
 * el Request y, ante carreras, la violación UNIQUE de base se mapea a 422.
 */
class ProductBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['barcode']) && is_string($data['barcode'])) {
            $data['barcode'] = trim($data['barcode']);
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $barcode = $this->route('barcode');
        $barcodeId = $barcode instanceof ProductBarcode ? (int) $barcode->id : null;

        return [
            'barcode' => [
                'required',
                'string',
                'max:64',
                Rule::unique('product_barcodes', 'barcode')->ignore($barcodeId),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['ean13', 'ean8', 'upc', 'code128', 'internal', 'other']),
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.required' => 'El código de barras es obligatorio.',
            'barcode.max' => 'El código de barras no puede superar los 64 caracteres.',
            'barcode.unique' => 'Ese código de barras ya está registrado en el catálogo.',
            'type.in' => 'El tipo de código de barras no es válido.',
            'is_primary.boolean' => 'El indicador de código primario no es válido.',
        ];
    }
}
