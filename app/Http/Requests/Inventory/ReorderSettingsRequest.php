<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de configuración de reposición (INVREORDER-001 · INV-07).
 *
 * Solo se aceptan los tres ajustes aprobados. La sucursal, el usuario, los
 * saldos, el costo, `safety_stock` y `location_label` no forman parte de esta
 * allowlist: un campo adicional del cliente se ignora y nunca amplía el
 * alcance. La validación decimal estricta también se repite en el servicio.
 */
class ReorderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['reorder_point', 'reorder_qty', 'max_stock'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['reorder_qty', 'max_stock'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $decimal = ['string', 'max:20', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'];

        return [
            'reorder_point' => array_merge(['required'], $decimal),
            'reorder_qty' => array_merge(['nullable'], $decimal),
            'max_stock' => array_merge(['nullable'], $decimal),
        ];
    }

    public function messages(): array
    {
        return [
            'reorder_point.required' => 'El punto de reposición es obligatorio.',
            'reorder_point.string' => 'El punto de reposición debe enviarse como texto decimal.',
            'reorder_point.regex' => 'El punto de reposición debe ser un decimal no negativo de hasta 9 enteros y 3 decimales.',
            'reorder_qty.string' => 'La cantidad sugerida debe enviarse como texto decimal.',
            'reorder_qty.regex' => 'La cantidad sugerida debe ser un decimal no negativo de hasta 9 enteros y 3 decimales.',
            'max_stock.string' => 'El stock máximo debe enviarse como texto decimal.',
            'max_stock.regex' => 'El stock máximo debe ser un decimal no negativo de hasta 9 enteros y 3 decimales.',
        ];
    }
}
