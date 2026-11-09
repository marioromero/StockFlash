<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registro mínimo de auditoría (Parte 2 §4.19).
 *
 * Escribe en `audit_logs` sin dependencias externas. Filtra claves sensibles
 * (contraseñas, PIN, tokens, secretos) antes de serializar los valores.
 *
 * Debe invocarse SIEMPRE dentro de la transacción de la operación auditada:
 * si esta escritura falla, la operación completa debe revertirse.
 */
class AuditLogger
{
    public function log(
        string $event,
        Branch $branch,
        ?array $old = null,
        ?array $new = null,
        string $moduleCode = 'core',
    ): void {
        $request = request();

        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'branch_id' => $branch->id,
            'module_code' => $moduleCode,
            'event' => $event,
            'auditable_type' => 'branches',
            'auditable_id' => $branch->id,
            'old_values' => $this->encode($old),
            'new_values' => $this->encode($new),
            'is_personal_data_access' => 0,
            'is_security_event' => 0,
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncate((string) $request?->userAgent(), 255),
            // audit_logs es inmutable: solo created_at (DB-001 QA fix 000020).
            'created_at' => now(),
        ]);
    }

    private function encode(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        return json_encode(
            SensitiveKeyFilter::filter($values),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function truncate(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }
}
