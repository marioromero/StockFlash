<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de filtros GET del reporte Kardex (INVKARDEX-001).
 *
 * Solo se aceptan búsqueda, variante, rango de fechas, tamaño de página y
 * cursor. La sucursal, el scope, los costos y cualquier otro parámetro de
 * query no forman parte de esta allowlist y no se usan para ampliar la
 * consulta. La validación falla antes de ejecutar el reporte.
 */
class KardexFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['q']) && is_string($data['q'])) {
            $data['q'] = trim($data['q']);
        }

        foreach (['q', 'variant_id', 'from', 'to', 'per_page', 'cursor'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to' => ['nullable', 'date_format:Y-m-d', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar 100 caracteres.',
            'variant_id.integer' => 'La variante debe ser un identificador numérico.',
            'variant_id.min' => 'La variante debe ser un identificador válido.',
            'from.date_format' => 'La fecha desde debe tener el formato AAAA-MM-DD.',
            'from.regex' => 'La fecha desde debe tener el formato AAAA-MM-DD.',
            'to.date_format' => 'La fecha hasta debe tener el formato AAAA-MM-DD.',
            'to.regex' => 'La fecha hasta debe tener el formato AAAA-MM-DD.',
            'to.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.min' => 'El tamaño de página debe ser al menos 1.',
            'per_page.max' => 'El tamaño de página no puede superar 100.',
            'cursor.max' => 'El cursor de paginación no es válido.',
        ];
    }
}
