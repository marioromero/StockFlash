<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de creación de borrador de traspaso (INVTRANSFER-001).
 *
 * Solo se aceptan destino, notas y líneas SKU+cantidad. La sucursal de origen
 * sale de `{branch}` y `branch.access`; el actor sale de la sesión; estado,
 * número, usuario, fechas, costo y sucursal no se aceptan desde el cliente.
 */
class StockTransferStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['lines']) && is_array($data['lines'])) {
            foreach ($data['lines'] as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                foreach (['sku', 'quantity'] as $field) {
                    if (isset($line[$field]) && is_string($line[$field])) {
                        $line[$field] = trim($line[$field]);
                    }
                }

                $data['lines'][$index] = $line;
            }
        }

        if (array_key_exists('notes', $data) && is_string($data['notes'])) {
            $data['notes'] = trim($data['notes']);

            if ($data['notes'] === '') {
                $data['notes'] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*' => ['array:sku,quantity'],
            'lines.*.sku' => ['required', 'string', 'max:100'],
            'lines.*.quantity' => [
                'required',
                'string',
                'max:20',
                'not_in:0,0.0,0.00,0.000',
                'regex:/^\d{1,9}(?:\.\d{1,3})?$/',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_branch_id.required' => 'Debe seleccionar la sucursal de destino.',
            'to_branch_id.integer' => 'La sucursal de destino no es válida.',
            'to_branch_id.min' => 'La sucursal de destino no es válida.',
            'lines.required' => 'Debe agregar al menos una línea al traspaso.',
            'lines.array' => 'Las líneas del traspaso no son válidas.',
            'lines.min' => 'Debe agregar al menos una línea al traspaso.',
            'lines.max' => 'Un traspaso no puede superar 100 líneas.',
            'lines.*.array' => 'Cada línea solo puede incluir SKU y cantidad.',
            'lines.*.sku.required' => 'Cada línea debe incluir un SKU.',
            'lines.*.sku.string' => 'El SKU de la línea no es válido.',
            'lines.*.sku.max' => 'El SKU no puede superar 100 caracteres.',
            'lines.*.quantity.required' => 'Cada línea debe incluir una cantidad solicitada.',
            'lines.*.quantity.string' => 'La cantidad solicitada debe enviarse como texto decimal.',
            'lines.*.quantity.max' => 'La cantidad solicitada excede el máximo permitido.',
            'lines.*.quantity.not_in' => 'La cantidad solicitada debe ser mayor que cero.',
            'lines.*.quantity.regex' => 'La cantidad solicitada debe ser positiva, con hasta 9 enteros y 3 decimales.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden superar 500 caracteres.',
        ];
    }
}
