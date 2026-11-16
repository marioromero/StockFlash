<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación técnica de las preferencias generales (CFG-003).
 *
 * Solo se aceptan las siete claves contractuales de Parte 2 §3.1. PUT es
 * completo: omitir un campo o enviar campos extra produce 422 sin escritura.
 * No se imponen restricciones comerciales de país/moneda ni se reinterpreta
 * el producto; únicamente se validan formatos y rangos.
 */
class GeneralSettingsRequest extends FormRequest
{
    /**
     * Claves administradas por esta pantalla.
     *
     * @var list<string>
     */
    private const KEYS = [
        'country',
        'currency_code',
        'currency_decimals',
        'timezone',
        'locale',
        'prices_include_tax',
        'cash_rounding_step',
    ];

    public function authorize(): bool
    {
        // La autorización real se aplica server-side en las rutas con
        // `can.permission:settings.manage`.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['country', 'currency_code', 'timezone', 'locale'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['country', 'currency_code'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'currency_code' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'currency_decimals' => ['required', 'integer', 'between:0,4'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'regex:/^[a-z]{2}-[A-Z]{2}$/'],
            'prices_include_tax' => ['required', 'boolean'],
            'cash_rounding_step' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $extra = array_diff(
                array_keys($this->all()),
                self::KEYS,
                ['_method', '_token'],
            );

            if ($extra !== []) {
                $validator->errors()->add(
                    'payload',
                    'El formulario contiene campos no permitidos.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'country.required' => 'El país es obligatorio.',
            'country.regex' => 'El país debe ser un código de dos letras.',
            'currency_code.required' => 'La moneda es obligatoria.',
            'currency_code.regex' => 'La moneda debe ser un código de tres letras.',
            'currency_decimals.required' => 'Los decimales de moneda son obligatorios.',
            'currency_decimals.integer' => 'Los decimales de moneda deben ser un número entero.',
            'currency_decimals.between' => 'Los decimales de moneda deben estar entre 0 y 4.',
            'timezone.required' => 'La zona horaria es obligatoria.',
            'timezone.timezone' => 'La zona horaria no es un identificador válido.',
            'locale.required' => 'El idioma es obligatorio.',
            'locale.regex' => 'El idioma debe tener el formato es-CL.',
            'prices_include_tax.required' => 'Debe indicar si los precios incluyen impuesto.',
            'prices_include_tax.boolean' => 'El valor de precios con impuesto no es válido.',
            'cash_rounding_step.required' => 'El paso de redondeo es obligatorio.',
            'cash_rounding_step.integer' => 'El paso de redondeo debe ser un número entero.',
            'cash_rounding_step.min' => 'El paso de redondeo debe ser al menos 1.',
        ];
    }
}
