<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowlist de filtros GET del panel de reposición (INVREORDER-001).
 *
 * Solo se aceptan búsqueda, filtro crítico y tamaño de página. La sucursal, el
 * alcance y cualquier otro parámetro de query no amplían la consulta.
 */
class ReorderFilterRequest extends FormRequest
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

        foreach (['q', 'critical', 'per_page'] as $field) {
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
            'critical' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar 100 caracteres.',
            'critical.boolean' => 'El filtro de stock crítico no es válido.',
            'per_page.integer' => 'El tamaño de página debe ser un número entero.',
            'per_page.min' => 'El tamaño de página debe ser al menos 1.',
            'per_page.max' => 'El tamaño de página no puede superar 100.',
        ];
    }
}
