<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Allowlist exacta de `categories`, incluida la validación de jerarquía.
 * El slug se genera solo al crear; una edición sin `slug` no lo renombra.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $category = $this->route('category');
        $creating = ! $category instanceof Category;

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['slug']) && is_string($data['slug'])) {
            $data['slug'] = trim($data['slug']);
        }

        foreach (['parent_id', 'max_discount_percent', 'default_tax_rate_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if ($creating && (! array_key_exists('slug', $data) || $data['slug'] === null || $data['slug'] === '')) {
            $data['slug'] = Str::slug((string) ($data['name'] ?? ''));
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'string',
                'max:170',
                Rule::unique('categories', 'slug')->ignore($category instanceof Category ? $category->id : null),
            ],
            'max_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'default_tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'sort_order' => ['sometimes', 'integer', 'between:0,65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = $this->route('category');

            if (! $category instanceof Category && trim((string) $this->input('slug')) === '') {
                $validator->errors()->add('slug', 'No se pudo generar un slug válido a partir del nombre.');

                return;
            }

            if (! $category instanceof Category) {
                return;
            }

            $parentId = $this->input('parent_id');

            if ($parentId === null || $parentId === '') {
                return;
            }

            $parentId = (int) $parentId;
            $currentId = (int) $category->id;

            if ($parentId === $currentId) {
                $validator->errors()->add('parent_id', 'Una categoría no puede ser su propio padre.');

                return;
            }

            // Recorre la cadena de padres (incluidos soft-deleted) para
            // impedir ciclos sin recursión ilimitada.
            $visited = [];
            $cursor = $parentId;

            while ($cursor > 0 && ! in_array($cursor, $visited, true)) {
                if ($cursor === $currentId) {
                    $validator->errors()->add('parent_id', 'La categoría padre seleccionada depende de esta categoría y generaría un ciclo.');

                    return;
                }

                $visited[] = $cursor;
                $cursor = (int) DB::table('categories')->where('id', $cursor)->value('parent_id');
            }
        });
    }

    public function messages(): array
    {
        return [
            'parent_id.integer' => 'La categoría padre no es válida.',
            'parent_id.exists' => 'La categoría padre seleccionada no existe.',
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.max' => 'El nombre de la categoría no puede superar los 150 caracteres.',
            'slug.max' => 'El slug no puede superar los 170 caracteres.',
            'slug.unique' => 'Ya existe una categoría con ese slug (incluidas las eliminadas).',
            'max_discount_percent.numeric' => 'El tope de descuento debe ser numérico.',
            'max_discount_percent.between' => 'El tope de descuento debe estar entre 0 y 100.',
            'default_tax_rate_id.exists' => 'La tasa de impuesto seleccionada no existe.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.between' => 'El orden debe estar entre 0 y 65535.',
            'is_active.boolean' => 'El indicador de activo no es válido.',
        ];
    }
}
