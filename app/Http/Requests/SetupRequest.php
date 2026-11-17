<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Setup\SetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación del asistente de primer inicio (SETUP-001 · CORE-02).
 *
 * Allowlist estricta: solo las 9 claves CFG-002, las 7 claves CFG-003, los
 * campos de la sucursal inicial, los campos del Admin Central y el token de
 * flujo. Cualquier clave extra (incluidas `is_setup_completed`, `is_demo`,
 * ids, roles o licencias) produce 422 sin escritura.
 *
 * Las reglas replican los límites ya aprobados de `BusinessSettingsRequest`,
 * `GeneralSettingsRequest`, `BranchRequest` y `UserRequest`. La prueba de
 * paridad de `SetupValidationTest` comprueba que no divergen.
 */
class SetupRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const BUSINESS_KEYS = [
        'business_name',
        'fiscal_id',
        'fiscal_name',
        'commercial_activity',
        'fiscal_address',
        'fiscal_commune',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
    ];

    /**
     * @var list<string>
     */
    private const GENERAL_KEYS = [
        'country',
        'currency_code',
        'currency_decimals',
        'timezone',
        'locale',
        'prices_include_tax',
        'cash_rounding_step',
    ];

    /**
     * @var list<string>
     */
    private const BRANCH_KEYS = [
        'branch_code',
        'branch_name',
        'branch_type',
        'branch_address',
        'branch_commune',
        'branch_city',
        'branch_region',
        'branch_phone',
    ];

    /**
     * @var list<string>
     */
    private const ADMIN_KEYS = [
        'admin_name',
        'admin_email',
        'admin_password',
        'admin_password_confirmation',
    ];

    public function authorize(): bool
    {
        // La ruta pública se protege por estado de instalación, token de flujo
        // y transacción; no existe autorización por usuario en este paso.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (self::BUSINESS_KEYS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (is_string($data[$field])) {
                $trimmed = trim($data[$field]);
                $data[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        foreach (['country', 'currency_code', 'timezone', 'locale', 'branch_type'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['country', 'currency_code'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        if (array_key_exists('branch_code', $data) && is_string($data['branch_code'])) {
            $data['branch_code'] = strtoupper(trim($data['branch_code']));
        }

        if (array_key_exists('branch_name', $data) && is_string($data['branch_name'])) {
            $data['branch_name'] = trim($data['branch_name']);
        }

        foreach (['branch_address', 'branch_commune', 'branch_city', 'branch_region', 'branch_phone'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $trimmed = trim($data[$field]);
                $data[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (array_key_exists('admin_name', $data) && is_string($data['admin_name'])) {
            $data['admin_name'] = trim($data['admin_name']);
        }

        if (array_key_exists('admin_email', $data) && is_string($data['admin_email'])) {
            $data['admin_email'] = mb_strtolower(trim($data['admin_email']));
        }

        if (array_key_exists('setup_token', $data) && is_string($data['setup_token'])) {
            $data['setup_token'] = trim($data['setup_token']);
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            // CFG-002 · identidad del comercio y responsable (opcionales).
            'business_name' => ['nullable', 'string', 'max:150'],
            'fiscal_id' => ['nullable', 'string', 'max:50'],
            'fiscal_name' => ['nullable', 'string', 'max:150'],
            'commercial_activity' => ['nullable', 'string', 'max:255'],
            'fiscal_address' => ['nullable', 'string', 'max:255'],
            'fiscal_commune' => ['nullable', 'string', 'max:100'],
            'responsible_name' => ['nullable', 'string', 'max:150'],
            'responsible_email' => ['nullable', 'email', 'max:255'],
            'responsible_phone' => ['nullable', 'string', 'max:50'],

            // CFG-003 · preferencias generales (obligatorias).
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'currency_code' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'currency_decimals' => ['required', 'integer', 'between:0,4'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'regex:/^[a-z]{2}-[A-Z]{2}$/'],
            'prices_include_tax' => ['required', 'boolean'],
            'cash_rounding_step' => ['required', 'integer', 'min:1'],

            // Sucursal inicial. La unicidad solo se exige cuando el asistente
            // puede escribir: si ya está completado o la instancia no es
            // virgen, el controlador responde 404/409 sin revalidar contra
            // registros que ya existen.
            'branch_code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                ...($this->canWriteSetup()
                    ? [Rule::unique('branches', 'code')]
                    : []),
            ],
            'branch_name' => ['required', 'string', 'max:150'],
            'branch_type' => ['required', 'string', Rule::in(['store', 'warehouse', 'online'])],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_commune' => ['nullable', 'string', 'max:100'],
            'branch_city' => ['nullable', 'string', 'max:100'],
            'branch_region' => ['nullable', 'string', 'max:100'],
            'branch_phone' => ['nullable', 'string', 'max:30'],

            // Admin Central inicial.
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => [
                'required',
                'string',
                'email',
                'max:150',
                ...($this->canWriteSetup() ? [$this->emailUniqueRule()] : []),
            ],
            'admin_password' => ['required', 'string', 'min:15', 'max:64', 'confirmed'],
            'admin_password_confirmation' => ['required', 'string'],

            // Token del flujo; se verifica aparte para responder 419.
            'setup_token' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [
                ...self::BUSINESS_KEYS,
                ...self::GENERAL_KEYS,
                ...self::BRANCH_KEYS,
                ...self::ADMIN_KEYS,
                'setup_token',
            ];

            $extra = array_diff(
                array_keys($this->all()),
                $allowed,
                ['_token', '_method'],
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
            'business_name.string' => 'El nombre del comercio debe ser texto.',
            'business_name.max' => 'El nombre del comercio no puede superar los 150 caracteres.',
            'fiscal_id.string' => 'El RUT/ID tributario debe ser texto.',
            'fiscal_id.max' => 'El RUT/ID tributario no puede superar los 50 caracteres.',
            'fiscal_name.string' => 'La razón social debe ser texto.',
            'fiscal_name.max' => 'La razón social no puede superar los 150 caracteres.',
            'commercial_activity.string' => 'El giro o actividad comercial debe ser texto.',
            'commercial_activity.max' => 'El giro o actividad comercial no puede superar los 255 caracteres.',
            'fiscal_address.string' => 'La dirección tributaria debe ser texto.',
            'fiscal_address.max' => 'La dirección tributaria no puede superar los 255 caracteres.',
            'fiscal_commune.string' => 'La comuna debe ser texto.',
            'fiscal_commune.max' => 'La comuna no puede superar los 100 caracteres.',
            'responsible_name.string' => 'El nombre del responsable debe ser texto.',
            'responsible_name.max' => 'El nombre del responsable no puede superar los 150 caracteres.',
            'responsible_email.email' => 'El correo del responsable no es válido.',
            'responsible_email.max' => 'El correo del responsable no puede superar los 255 caracteres.',
            'responsible_phone.string' => 'El teléfono del responsable debe ser texto.',
            'responsible_phone.max' => 'El teléfono del responsable no puede superar los 50 caracteres.',

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

            'branch_code.required' => 'El código de sucursal es obligatorio.',
            'branch_code.string' => 'El código de sucursal debe ser texto.',
            'branch_code.max' => 'El código de sucursal no puede superar los 30 caracteres.',
            'branch_code.regex' => 'El código solo puede contener letras mayúsculas, números y guiones.',
            'branch_code.unique' => 'Ya existe una sucursal con ese código.',
            'branch_name.required' => 'El nombre de la sucursal es obligatorio.',
            'branch_name.string' => 'El nombre de la sucursal debe ser texto.',
            'branch_name.max' => 'El nombre de la sucursal no puede superar los 150 caracteres.',
            'branch_type.required' => 'El tipo de sucursal es obligatorio.',
            'branch_type.in' => 'El tipo de sucursal debe ser local, bodega u online.',
            'branch_address.max' => 'La dirección no puede superar los 255 caracteres.',
            'branch_commune.max' => 'La comuna no puede superar los 100 caracteres.',
            'branch_city.max' => 'La ciudad no puede superar los 100 caracteres.',
            'branch_region.max' => 'La región no puede superar los 100 caracteres.',
            'branch_phone.max' => 'El teléfono no puede superar los 30 caracteres.',

            'admin_name.required' => 'El nombre del administrador es obligatorio.',
            'admin_name.string' => 'El nombre del administrador debe ser texto.',
            'admin_name.max' => 'El nombre del administrador no puede superar los 150 caracteres.',
            'admin_email.required' => 'El correo del administrador es obligatorio.',
            'admin_email.string' => 'El correo del administrador debe ser texto.',
            'admin_email.email' => 'El correo del administrador no es válido.',
            'admin_email.max' => 'El correo del administrador no puede superar los 150 caracteres.',
            'admin_password.required' => 'La contraseña del administrador es obligatoria.',
            'admin_password.string' => 'La contraseña del administrador debe ser texto.',
            'admin_password.min' => 'La contraseña debe tener al menos 15 caracteres.',
            'admin_password.max' => 'La contraseña no puede superar los 64 caracteres.',
            'admin_password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'admin_password_confirmation.required' => 'Debe confirmar la contraseña del administrador.',
            'setup_token.string' => 'El token de instalación no es válido.',

            'payload' => 'El formulario contiene campos no permitidos.',
        ];
    }

    /**
     * Solo se validan unicidades cuando el asistente aún puede completar la
     * instalación. Si ya está completada o no es virgen, el controlador debe
     * responder 404/409 sin convertir el intento en un 422 de validación.
     */
    private function canWriteSetup(): bool
    {
        $setup = app(SetupService::class);

        return $setup->schemaReady()
            && ! $setup->isCompleted()
            && $setup->isPristine();
    }

    private function emailUniqueRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $exists = User::query()
                ->withTrashed()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($value)])
                ->exists();

            if ($exists) {
                $fail('Ya existe un usuario con ese correo electrónico.');
            }
        };
    }
}
