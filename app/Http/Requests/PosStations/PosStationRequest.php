<?php

namespace App\Http\Requests\PosStations;

use App\Models\PosStation;
use App\Support\PosStations\PosStationCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist de campos de `pos_stations` (CSH-02).
 *
 * Solo valida la forma. La autorización real ocurre en la ruta (módulo POS,
 * `settings.manage` y alcance por sucursal) y las reglas de negocio
 * (sucursal permitida, unicidad, rotación de token y auditoría) en el servicio
 * transaccional.
 *
 * `code` se valida y normaliza solo al crear: es inmutable después. Campos
 * como `id`, `is_active`, `device_token_hash`, `last_seen_at`, `deleted_at` o
 * `created_at` no se consideran aunque el cliente los envíe.
 */
class PosStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['code', 'name', 'printer_profile'] as $field) {
            if (! array_key_exists($field, $data) || ! is_string($data[$field])) {
                continue;
            }

            $data[$field] = trim($data[$field]);
        }

        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = PosStationCode::normalize($data['code']);
        }

        if (array_key_exists('printer_profile', $data) && $data['printer_profile'] === '') {
            $data['printer_profile'] = null;
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $isUpdate = $this->route('station') !== null;

        $branchRules = [
            'integer',
            'min:1',
            Rule::exists('branches', 'id')->where(
                fn ($query) => $query->where('is_active', 1)->whereNull('deleted_at')
            ),
        ];

        $rules = [
            'name' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:100']
                : ['required', 'string', 'max:100'],
            'branch_id' => $isUpdate
                ? ['sometimes', 'required', ...$branchRules]
                : ['required', ...$branchRules],
            'printer_profile' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(PosStation::PRINTER_PROFILES),
            ],
        ];

        if (! $isUpdate) {
            $rules['code'] = [
                'required',
                'string',
                'max:30',
                'regex:'.PosStationCode::PATTERN,
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la estación es obligatorio.',
            'code.max' => 'El código no puede superar los 30 caracteres.',
            'code.regex' => 'El código debe usar solo letras, números, punto, guion o guion bajo.',
            'name.required' => 'El nombre de la estación es obligatorio.',
            'name.max' => 'El nombre no puede superar los 100 caracteres.',
            'branch_id.required' => 'Seleccione una sucursal activa.',
            'branch_id.integer' => 'La sucursal seleccionada no es válida.',
            'branch_id.min' => 'La sucursal seleccionada no es válida.',
            'branch_id.exists' => 'La sucursal debe existir y estar activa.',
            'printer_profile.string' => 'El perfil de impresora no es válido.',
            'printer_profile.in' => 'El perfil de impresora no es válido.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        if (array_key_exists('code', $validated)) {
            $validated['code'] = PosStationCode::normalize((string) $validated['code']);
        }

        if (array_key_exists('branch_id', $validated)) {
            $validated['branch_id'] = (int) $validated['branch_id'];
        }

        return $key === null ? $validated : ($validated[$key] ?? $default);
    }
}
