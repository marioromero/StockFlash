<?php

namespace App\Http\Requests\Catalog;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Allowlist exacta de `brands`. El slug se genera solo al crear.
 */
class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $brand = $this->route('brand');
        $creating = ! $brand instanceof Brand;

        foreach (['name', 'slug', 'description', 'logo_url'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['description', 'logo_url'] as $field) {
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
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'string',
                'max:170',
                Rule::unique('brands', 'slug')->ignore($brand instanceof Brand ? $brand->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $brand = $this->route('brand');

            if (! $brand instanceof Brand && trim((string) $this->input('slug')) === '') {
                $validator->errors()->add('slug', 'No se pudo generar un slug válido a partir del nombre.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la marca es obligatorio.',
            'name.max' => 'El nombre de la marca no puede superar los 150 caracteres.',
            'slug.max' => 'El slug no puede superar los 170 caracteres.',
            'slug.unique' => 'Ya existe una marca con ese slug (incluidas las eliminadas).',
            'description.max' => 'La descripción no puede superar los 255 caracteres.',
            'logo_url.max' => 'La URL del logo no puede superar los 500 caracteres.',
            'is_active.boolean' => 'El indicador de activo no es válido.',
        ];
    }
}
