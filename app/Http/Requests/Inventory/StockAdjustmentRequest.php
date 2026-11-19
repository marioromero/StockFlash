<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist del ajuste manual de existencias (INVWRITE-001).
 *
 * Campos permitidos: variante, cantidad con signo, motivo, nota, token de
 * idempotencia y, solo cuando el umbral lo exige, autorizador y PIN. La
 * sucursal, el actor, el tipo/origen del movimiento y los saldos nunca se
 * aceptan desde el cliente.
 */
class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['variant_id', 'quantity', 'reason_code_id', 'request_token', 'authorizer_id', 'pin'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['note', 'authorizer_id', 'pin'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'string', 'max:20', 'regex:/^-?\d{1,9}(?:\.\d{1,3})?$/'],
            'reason_code_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'request_token' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,64}$/'],
            'authorizer_id' => ['nullable', 'integer', 'min:1'],
            'pin' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'variant_id.required' => 'Debe seleccionar una variante.',
            'variant_id.integer' => 'La variante seleccionada no es válida.',
            'variant_id.min' => 'La variante seleccionada no es válida.',
            'quantity.required' => 'La cantidad del ajuste es obligatoria.',
            'quantity.string' => 'La cantidad del ajuste debe enviarse como texto decimal.',
            'quantity.max' => 'La cantidad del ajuste excede el máximo permitido.',
            'quantity.regex' => 'La cantidad del ajuste debe tener como máximo 9 enteros y 3 decimales.',
            'reason_code_id.required' => 'Debe seleccionar un motivo para el ajuste.',
            'reason_code_id.integer' => 'El motivo seleccionado no es válido.',
            'reason_code_id.min' => 'El motivo seleccionado no es válido.',
            'note.max' => 'La nota no puede superar 255 caracteres.',
            'request_token.required' => 'La solicitud no incluye un token; recargue la página e intente nuevamente.',
            'request_token.regex' => 'El token de la solicitud no es válido; recargue la página e intente nuevamente.',
            'authorizer_id.integer' => 'El autorizador seleccionado no es válido.',
            'authorizer_id.min' => 'El autorizador seleccionado no es válido.',
            'pin.max' => 'El PIN ingresado no es válido.',
        ];
    }
}
