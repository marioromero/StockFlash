<?php

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Destino de base de datos elegido por el operador.
 *
 * La validación evita inyección en DSN y limita los caracteres aceptados. El
 * password se mantiene fuera de old input/flash mediante `dontFlash`.
 */
class InstallConnectionRequest extends FormRequest
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
            'driver.required' => 'Debe seleccionar el motor de base de datos.',
            'driver.in' => 'El motor de base de datos no está soportado.',
            'host.required' => 'El host de la base de datos es obligatorio.',
            'host.regex' => 'El host contiene caracteres no permitidos.',
            'port.required' => 'El puerto de la base de datos es obligatorio.',
            'port.integer' => 'El puerto debe ser un número entero.',
            'port.between' => 'El puerto debe estar entre 1 y 65535.',
            'database.required' => 'El nombre de la base de datos es obligatorio.',
            'database.regex' => 'El nombre de la base de datos contiene caracteres no permitidos.',
            'database.max' => 'El nombre de la base de datos es demasiado largo.',
            'username.required' => 'El usuario de la base de datos es obligatorio.',
            'username.regex' => 'El usuario contiene caracteres no permitidos.',
            'db_password.string' => 'La contraseña de la base de datos no es válida.',
            'db_password.max' => 'La contraseña de la base de datos es demasiado larga.',
            'install_token.required' => 'La sesión del instalador expiró.',
        ];
    }
}
