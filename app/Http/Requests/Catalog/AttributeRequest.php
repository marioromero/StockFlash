<?php

namespace App\Http\Requests\Catalog;

use App\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist exacta de `attributes`.
 */
class AttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $attribute = $this->route('attribute');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('attributes', 'name')->ignore($attribute instanceof Attribute ? $attribute->id : null),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del atributo es obligatorio.',
            'name.max' => 'El nombre del atributo no puede superar los 100 caracteres.',
            'name.unique' => 'Ya existe un atributo con ese nombre.',
        ];
    }
}
