<?php

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Autorización técnica del instalador con token de arranque.
 *
 * El token se valida además con comparación de tiempo constante en el
 * servicio. Nunca se guarda en old input ni en flash.
 */
class InstallBootstrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bootstrap_token' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'bootstrap_token.required' => 'Debe ingresar el token de arranque del instalador.',
            'bootstrap_token.string' => 'El token de arranque no es válido.',
            'bootstrap_token.max' => 'El token de arranque no es válido.',
        ];
    }
}
