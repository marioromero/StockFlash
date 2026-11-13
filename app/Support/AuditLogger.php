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
 *
 * `log()` conserva la firma de BRANCH-001. `record()` es la capacidad genérica
 * para auditar `users` y `branches` con flags de evento de seguridad y de
 * acceso a datos personales.
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
        $this->record(
            $event,
            'branches',
            (int) $branch->getKey(),
            (int) $branch->getKey(),
            $old,
            $new,
            $moduleCode,
        );
    }

    /**
     * Registro genérico de auditoría.
     *
     * `branch_id` es nullable: la administración de usuarios es global.
     */
    public function record(
        string $event,
        string $auditableType,
        ?int $auditableId = null,
        ?int $branchId = null,
        ?array $old = null,
        ?array $new = null,
        string $moduleCode = 'core',
        bool $isSecurityEvent = false,
        bool $isPersonalDataAccess = false,
    ): void {
        $request = request();

        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'branch_id' => $branchId,
            'module_code' => $moduleCode,
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $this->encode($old),
            'new_values' => $this->encode($new),
            'is_personal_data_access' => $isPersonalDataAccess ? 1 : 0,
            'is_security_event' => $isSecurityEvent ? 1 : 0,
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncate((string) $request?->userAgent(), 255),
            // audit_logs es inmutable: solo created_at (DB-001 QA fix 000020).
            'created_at' => now(),
        ]);
    }

    /**
     * Evento de lectura de datos personales (PRV-06 / CORE-10).
     *
     * No copia nombres, correos ni listas: solo el tipo y, para la ficha, el
     * ID consultado. Debe ejecutarse antes de entregar los datos.
     */
    public function recordRead(
        string $event,
        string $auditableType,
        ?int $auditableId = null,
        string $moduleCode = 'users',
    ): void {
        $this->record(
            $event,
            $auditableType,
            $auditableId,
            null,
            null,
            null,
            $moduleCode,
            false,
            true,
        );
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
