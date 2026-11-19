<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de la carga inicial manual (INVWRITE-001).
 *
 * Solo la variante y la cantidad provienen del cliente. La sucursal, el actor,
 * el tipo de movimiento, el origen y los saldos se resuelven en servidor.
 */
class StockInitialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['variant_id', 'quantity'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'string', 'max:20', 'regex:/^-?\d{1,9}(?:\.\d{1,3})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'variant_id.required' => 'Debe seleccionar una variante.',
            'variant_id.integer' => 'La variante seleccionada no es válida.',
            'variant_id.min' => 'La variante seleccionada no es válida.',
            'quantity.required' => 'La cantidad inicial es obligatoria.',
            'quantity.string' => 'La cantidad inicial debe enviarse como texto decimal.',
            'quantity.max' => 'La cantidad inicial excede el máximo permitido.',
            'quantity.regex' => 'La cantidad inicial debe tener como máximo 9 enteros y 3 decimales.',
        ];
    }
}
