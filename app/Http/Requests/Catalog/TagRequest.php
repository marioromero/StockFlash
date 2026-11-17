<?php

namespace App\Http\Requests\Catalog;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Allowlist exacta de `tags`. El slug se genera solo al crear.
 */
class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        $tag = $this->route('tag');
        $creating = ! $tag instanceof Tag;

        foreach (['name', 'slug'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($creating && (! array_key_exists('slug', $data) || $data['slug'] === null || $data['slug'] === '')) {
            $data['slug'] = Str::slug((string) ($data['name'] ?? ''));
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('tags', 'slug')->ignore($tag instanceof Tag ? $tag->id : null),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tag = $this->route('tag');

            if (! $tag instanceof Tag && trim((string) $this->input('slug')) === '') {
                $validator->errors()->add('slug', 'No se pudo generar un slug válido a partir del nombre.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la etiqueta es obligatorio.',
            'name.max' => 'El nombre de la etiqueta no puede superar los 100 caracteres.',
            'slug.max' => 'El slug no puede superar los 120 caracteres.',
            'slug.unique' => 'Ya existe una etiqueta con ese slug.',
        ];
    }
}
