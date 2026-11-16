<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de los datos del comercio y contacto responsable (CFG-002).
 *
 * Solo se aceptan las nueve claves contractuales. Cualquier campo extra del
 * payload se ignora: el controlador persiste únicamente `validated()`.
 * No se valida RUT/SII ni se afirma emisión de documentos tributarios.
 */
class BusinessSettingsRequest extends FormRequest
{
    /**
     * Claves de texto administradas por esta pantalla.
     *
     * @var list<string>
     */
    private const TEXT_FIELDS = [
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

    public function authorize(): bool
    {
        // La autorización real se aplica server-side en las rutas con
        // `can.permission:settings.manage`.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (self::TEXT_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (is_string($data[$field])) {
                $trimmed = trim($data[$field]);
                $data[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['nullable', 'string', 'max:150'],
            'fiscal_id' => ['nullable', 'string', 'max:50'],
            'fiscal_name' => ['nullable', 'string', 'max:150'],
            'commercial_activity' => ['nullable', 'string', 'max:255'],
            'fiscal_address' => ['nullable', 'string', 'max:255'],
            'fiscal_commune' => ['nullable', 'string', 'max:100'],
            'responsible_name' => ['nullable', 'string', 'max:150'],
            'responsible_email' => ['nullable', 'email', 'max:255'],
            'responsible_phone' => ['nullable', 'string', 'max:50'],
        ];
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
        ];
    }
}
