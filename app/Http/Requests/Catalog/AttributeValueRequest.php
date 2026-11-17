<?php

namespace App\Http\Requests\Catalog;

use App\Models\AttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist exacta de `attribute_values`. Unicidad compuesta por
 * (`attribute_id`, `value`), ignorando el registro editado.
 */
class AttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['value']) && is_string($data['value'])) {
            $data['value'] = trim($data['value']);
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $attributeValue = $this->route('attributeValue');
        $attributeId = $this->input('attribute_id');

        return [
            'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'value' => [
                'required',
                'string',
                'max:100',
                Rule::unique('attribute_values', 'value')
                    ->where(fn ($query) => $query->where('attribute_id', $attributeId))
                    ->ignore($attributeValue instanceof AttributeValue ? $attributeValue->id : null),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'attribute_id.required' => 'El atributo es obligatorio.',
            'attribute_id.exists' => 'El atributo seleccionado no existe.',
            'value.required' => 'El valor del atributo es obligatorio.',
            'value.max' => 'El valor del atributo no puede superar los 100 caracteres.',
            'value.unique' => 'Ese valor ya existe para el atributo seleccionado.',
        ];
    }
}
