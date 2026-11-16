<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de los ajustes del comprobante interno (CFG-001).
 *
 * Solo se aceptan las tres claves contractuales. Cualquier campo extra del
 * payload se ignora: el controlador persiste únicamente `validated()`.
 */
class ReceiptSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real se aplica server-side en las rutas con
        // `can.permission:settings.manage`.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['receipt_header', 'receipt_footer'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (is_string($data[$field])) {
                $trimmed = trim($data[$field]);
                $data[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (isset($data['receipt_paper']) && is_string($data['receipt_paper'])) {
            $data['receipt_paper'] = trim($data['receipt_paper']);
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'receipt_header' => ['nullable', 'string', 'max:1000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'receipt_paper' => ['required', 'string', 'in:58mm,80mm,A4'],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_header.string' => 'El encabezado debe ser texto.',
            'receipt_header.max' => 'El encabezado no puede superar los 1000 caracteres.',
            'receipt_footer.string' => 'El pie debe ser texto.',
            'receipt_footer.max' => 'El pie no puede superar los 1000 caracteres.',
            'receipt_paper.required' => 'El tamaño de papel es obligatorio.',
            'receipt_paper.string' => 'El tamaño de papel no es válido.',
            'receipt_paper.in' => 'El tamaño de papel debe ser 58mm, 80mm o A4.',
        ];
    }
}
