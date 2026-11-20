<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de recepción de traspaso (INVTRANSFER-001).
 *
 * Una línea declarada por cada ítem, con cantidad no negativa DECIMAL(12,3).
 * El traspaso, la sucursal destino y el actor se resuelven en servidor; no se
 * aceptan estado, usuario, fecha, costo ni sucursal desde el cliente.
 */
class StockTransferReceiveRequest extends FormRequest
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

                if (isset($line['received_qty']) && is_string($line['received_qty'])) {
                    $line['received_qty'] = trim($line['received_qty']);
                }

                $data['lines'][$index] = $line;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*' => ['array:item_id,received_qty'],
            'lines.*.item_id' => ['required', 'integer', 'min:1'],
            'lines.*.received_qty' => ['required', 'string', 'max:20', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Debe declarar las cantidades recibidas.',
            'lines.array' => 'Las líneas de recepción no son válidas.',
            'lines.min' => 'Debe declarar al menos una línea de recepción.',
            'lines.max' => 'La recepción no puede superar 100 líneas.',
            'lines.*.array' => 'Cada línea solo puede incluir el ítem y la cantidad recibida.',
            'lines.*.item_id.required' => 'Cada línea debe identificar el ítem del traspaso.',
            'lines.*.item_id.integer' => 'El ítem de la línea no es válido.',
            'lines.*.item_id.min' => 'El ítem de la línea no es válido.',
            'lines.*.received_qty.required' => 'Cada línea debe incluir la cantidad recibida.',
            'lines.*.received_qty.string' => 'La cantidad recibida debe enviarse como texto decimal.',
            'lines.*.received_qty.max' => 'La cantidad recibida excede el máximo permitido.',
            'lines.*.received_qty.regex' => 'La cantidad recibida debe ser no negativa, con hasta 9 enteros y 3 decimales.',
        ];
    }
}
