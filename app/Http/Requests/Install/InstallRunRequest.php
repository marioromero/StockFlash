<?php

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Confirmación explícita e irreversible del destino antes de instalar.
 *
 * Exige:
 * - token de flujo de la sesión (se verifica aparte con hash_equals),
 * - casilla de confirmación,
 * - frase escrita `INSTALAR`,
 * - mismos datos de BD que ya se probaron (se comparan contra el destino).
 */
class InstallRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['driver', 'host', 'database', 'username'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (isset($data['confirmation_phrase']) && is_string($data['confirmation_phrase'])) {
            $data['confirmation_phrase'] = strtoupper(trim($data['confirmation_phrase']));
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'install_token' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', Rule::in(['mariadb', 'mysql'])],
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_$-]+$/'],
            'username' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._@-]+$/'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'confirm_destination' => ['required', 'accepted'],
            'confirmation_phrase' => ['required', 'string', Rule::in(['INSTALAR'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $password = $this->input('db_password');

            if (is_string($password) && preg_match('/[\r\n\0]/', $password)) {
                $validator->errors()->add('db_password', 'La contraseña contiene caracteres no permitidos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'confirm_destination.accepted' => 'Debe confirmar explícitamente el destino de la instalación.',
            'confirm_destination.required' => 'Debe confirmar explícitamente el destino de la instalación.',
            'confirmation_phrase.required' => 'Debe escribir INSTALAR para confirmar.',
            'confirmation_phrase.in' => 'La frase de confirmación debe ser INSTALAR.',
            'install_token.required' => 'La sesión del instalador expiró.',
        ];
    }
}
