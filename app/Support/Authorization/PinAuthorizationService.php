<?php

namespace App\Support\Authorization;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Servicio interno de autorización mediante PIN (CORE-11 / SEC-12 / RN-04–05).
 *
 * No expone rutas, endpoints, controladores ni interfaz. Solo resuelve:
 * política de autorización, verificación del PIN y persistencia de una fila
 * append-only en `authorization_overrides` con auditoría atómica.
 *
 * Convención de rechazo: toda negativa lanza `AuthorizationDeniedException`
 * con un código estable y un mensaje es-CL que nunca contiene el PIN, su hash
 * ni datos de intentos restantes.
 *
 * El solicitante SIEMPRE proviene de la sesión autenticada (`Auth::id()`);
 * nunca se recibe como parámetro.
 *
 * El servicio administra su propia transacción. No debe envolverse en una
 * transacción externa que revierta la solicitud: los intentos fallidos y su
 * auditoría deben persistir aunque el PIN sea rechazado.
 */
final class PinAuthorizationService
{
    /** Tipos contractuales de Parte 2 §4.18. */
    public const TYPES = [
        'discount_over_cap',
        'price_override',
        'below_cost',
        'void_sale',
        'void_item',
        'return',
        'no_sale',
        'cash_movement',
        'stock_adjustment',
        'cash_difference',
        'other',
    ];

    private const MODULE_CODE = 'override';

    private const DEFAULT_PIN_LENGTH = 4;

    private const DEFAULT_PIN_MAX_ATTEMPTS = 5;

    private const DEFAULT_OVERRIDE_MIN_AUTHORITY = 50;

    private const LOCK_MINUTES = 30;

    private const MAX_TEXT_VALUE_LENGTH = 100;

    private const MAX_REASON_LENGTH = 255;

    private const MAX_REFERENCE_TYPE_LENGTH = 50;

    public function __construct(
        private ?AuditLogger $auditLogger = null,
    ) {}

    /**
     * Autoriza una acción sensible y devuelve la fila creada.
     *
     * @return array{authorization_override_id: int}
     *
     * @throws AuthorizationDeniedException
     */
    public function authorize(
        string $type,
        int $branchId,
        User|int $authorizer,
        ?string $pin,
        ?int $posStationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?int $reasonCodeId = null,
        ?string $reason = null,
    ): array {
        $safeType = mb_substr($type, 0, self::MAX_REFERENCE_TYPE_LENGTH);

        if (! in_array($type, self::TYPES, true)) {
            $this->deny('invalid_type', 'El tipo de autorización no es válido.', $branchId, null, null, $safeType);
        }

        $this->assertContextLengths(
            $referenceType,
            $oldValue,
            $newValue,
            $reason,
            $branchId,
            $safeType,
        );

        $requesterId = Auth::id();
        $requesterId = $requesterId === null ? null : (int) $requesterId;

        if ($requesterId === null) {
            $this->deny(
                'requester_unauthenticated',
                'No hay una sesión autenticada para solicitar la autorización.',
                $branchId,
                null,
                null,
                $safeType,
            );
        }

        $this->assertPinFormat($pin, $branchId, $requesterId, $safeType);

        $authorizerId = $authorizer instanceof User
            ? (int) $authorizer->getKey()
            : (int) $authorizer;

        $this->assertRequesterAccess($requesterId, $branchId, $safeType);
        $this->assertActiveBranch($branchId, $requesterId, $safeType);
        $this->assertAuthorizerPolicy($authorizerId, $branchId, $requesterId, $safeType);
        $this->assertPosStation($posStationId, $branchId, $requesterId, $safeType);
        $this->assertReasonCode($reasonCodeId, $reason, $branchId, $requesterId, $safeType);

        $outcome = DB::transaction(function () use (
            $type,
            $branchId,
            $authorizerId,
            $pin,
            $requesterId,
            $posStationId,
            $referenceType,
            $referenceId,
            $oldValue,
            $newValue,
            $reasonCodeId,
            $reason,
        ): array {
            $locked = DB::table('users')
                ->where('id', $authorizerId)
                ->lockForUpdate()
                ->first([
                    'id',
                    'is_active',
                    'deleted_at',
                    'pin_hash',
                    'pin_failed_attempts',
                    'pin_locked_until',
                ]);

            if ($locked === null || $locked->deleted_at !== null) {
                $this->auditDenial('authorizer_not_found', $branchId, $requesterId, $authorizerId, $type);

                return ['denied' => 'authorizer_not_found'];
            }

            if ((int) $locked->is_active !== 1) {
                $this->auditDenial('authorizer_inactive', $branchId, $requesterId, $authorizerId, $type);

                return ['denied' => 'authorizer_inactive'];
            }

            $lockedUntil = $locked->pin_locked_until;

            if ($lockedUntil !== null && Carbon::parse($lockedUntil)->isFuture()) {
                $this->auditDenial('pin_locked', $branchId, $requesterId, $authorizerId, $type);

                return ['denied' => 'pin_locked'];
            }

            $hash = $locked->pin_hash;

            if (! is_string($hash) || $hash === '') {
                $this->auditDenial('pin_not_configured', $branchId, $requesterId, $authorizerId, $type);

                return ['denied' => 'pin_not_configured'];
            }

            if (! Hash::check((string) $pin, $hash)) {
                $attempts = (int) $locked->pin_failed_attempts + 1;
                $maxAttempts = $this->configInt('pin_max_attempts', self::DEFAULT_PIN_MAX_ATTEMPTS);
                $lockedNow = $attempts >= $maxAttempts;

                $updates = [
                    'pin_failed_attempts' => $attempts,
                    'updated_at' => now(),
                ];

                if ($lockedNow) {
                    $updates['pin_locked_until'] = now()->addMinutes(self::LOCK_MINUTES);
                }

                DB::table('users')->where('id', $authorizerId)->update($updates);

                $this->auditPinFailure($branchId, $requesterId, $authorizerId, $type, $attempts);

                if ($lockedNow) {
                    $this->auditPinLockout($branchId, $requesterId, $authorizerId, $type);
                }

                // El commit ocurre al retornar; la excepción se lanza fuera de
                // la transacción para que intentos y auditoría persistan.
                return $lockedNow
                    ? ['denied' => 'pin_locked']
                    : ['denied' => 'pin_invalid'];
            }

            DB::table('users')->where('id', $authorizerId)->update([
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
                'updated_at' => now(),
            ]);

            $authorizationOverrideId = (int) DB::table('authorization_overrides')->insertGetId([
                'type' => $type,
                'requested_by_user_id' => $requesterId,
                'authorized_by_user_id' => $authorizerId,
                'branch_id' => $branchId,
                'pos_station_id' => $posStationId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason_code_id' => $reasonCodeId,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $this->auditApproval(
                $authorizationOverrideId,
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
                $posStationId,
                $referenceType,
                $referenceId,
                $oldValue,
                $newValue,
                $reasonCodeId,
                $reason,
            );

            return ['authorization_override_id' => $authorizationOverrideId];
        });

        if (isset($outcome['denied'])) {
            throw new AuthorizationDeniedException(
                (string) $outcome['denied'],
                $this->denialMessage((string) $outcome['denied']),
            );
        }

        return ['authorization_override_id' => (int) $outcome['authorization_override_id']];
    }

    private function assertPinFormat(
        ?string $pin,
        int $branchId,
        int $requesterId,
        string $type,
    ): void {
        if ($pin === null || $pin === '') {
            $this->deny('pin_missing', 'Debe ingresar el PIN del autorizador.', $branchId, $requesterId, null, $type);
        }

        $pinLength = $this->configInt('pin_length', self::DEFAULT_PIN_LENGTH);

        if (preg_match('/^[0-9]+$/', $pin) !== 1 || strlen($pin) !== $pinLength) {
            $this->deny(
                'pin_format_invalid',
                "El PIN debe tener exactamente {$pinLength} dígitos.",
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }
    }

    private function assertRequesterAccess(int $requesterId, int $branchId, string $type): void
    {
        $requester = DB::table('users')
            ->where('id', $requesterId)
            ->whereNull('deleted_at')
            ->first(['id', 'is_active', 'global_role_id']);

        if ($requester === null || (int) $requester->is_active !== 1) {
            $this->deny(
                'requester_inactive',
                'El usuario solicitante no está activo.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }

        $hasAccess = $requester->global_role_id !== null
            || DB::table('branch_user')
                ->where('user_id', $requesterId)
                ->where('branch_id', $branchId)
                ->exists();

        if (! $hasAccess) {
            $this->deny(
                'branch_access_denied',
                'El usuario solicitante no tiene acceso a la sucursal indicada.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }
    }

    private function assertActiveBranch(int $branchId, int $requesterId, string $type): void
    {
        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->whereNull('deleted_at')
            ->first(['id', 'is_active']);

        if ($branch === null) {
            $this->deny(
                'branch_not_found',
                'La sucursal indicada no existe.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }

        if ((int) $branch->is_active !== 1) {
            $this->deny(
                'branch_inactive',
                'La sucursal indicada no está activa.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }
    }

    private function assertAuthorizerPolicy(
        int $authorizerId,
        int $branchId,
        int $requesterId,
        string $type,
    ): void {
        $authorizer = DB::table('users')
            ->where('id', $authorizerId)
            ->first(['id', 'global_role_id', 'is_active', 'deleted_at']);

        if ($authorizer === null || $authorizer->deleted_at !== null) {
            $this->deny(
                'authorizer_not_found',
                'El autorizador indicado no existe.',
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
            );
        }

        if ((int) $authorizer->is_active !== 1) {
            $this->deny(
                'authorizer_inactive',
                'El autorizador indicado no está activo.',
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
            );
        }

        $roleId = $authorizer->global_role_id;

        if ($roleId === null) {
            $roleId = DB::table('branch_user')
                ->where('user_id', $authorizerId)
                ->where('branch_id', $branchId)
                ->value('role_id');
        }

        if ($roleId === null) {
            $this->deny(
                'authorizer_role_missing',
                'El autorizador no tiene un rol aplicable en la sucursal indicada.',
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
            );
        }

        $authorityLevel = DB::table('roles')
            ->where('id', (int) $roleId)
            ->value('authority_level');

        if ($authorityLevel === null) {
            $this->deny(
                'authorizer_role_missing',
                'El autorizador no tiene un rol aplicable en la sucursal indicada.',
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
            );
        }

        $hasPermission = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', (int) $roleId)
            ->where('permissions.code', 'override.authorize')
            ->exists();

        if (! $hasPermission) {
            $this->deny(
                'authorizer_permission_denied',
                'El autorizador no tiene permiso para autorizar excepciones.',
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
            );
        }

        $minimumAuthority = $this->configInt(
            'override_min_authority',
            self::DEFAULT_OVERRIDE_MIN_AUTHORITY,
        );

        if ((int) $authorityLevel < $minimumAuthority) {
            $this->deny(
                'authorizer_authority_insufficient',
                'El autorizador no tiene autoridad suficiente para esta operación.',
                $branchId,
                $requesterId,
                $authorizerId,
                $type,
            );
        }
    }

    private function assertPosStation(?int $posStationId, int $branchId, int $requesterId, string $type): void
    {
        if ($posStationId === null) {
            return;
        }

        $exists = DB::table('pos_stations')
            ->where('id', $posStationId)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $this->deny(
                'pos_station_invalid',
                'La estación de venta no existe o no pertenece a la sucursal indicada.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }
    }

    private function assertReasonCode(
        ?int $reasonCodeId,
        ?string $reason,
        int $branchId,
        int $requesterId,
        string $type,
    ): void {
        if ($reasonCodeId === null) {
            return;
        }

        $reasonCode = DB::table('reason_codes')
            ->where('id', $reasonCodeId)
            ->first(['scope', 'is_active', 'requires_note']);

        if (
            $reasonCode === null
            || (int) $reasonCode->is_active !== 1
            || $reasonCode->scope !== 'override'
        ) {
            $this->deny(
                'reason_code_invalid',
                'El motivo indicado no es válido para una autorización.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }

        if ((int) $reasonCode->requires_note === 1 && trim((string) $reason) === '') {
            $this->deny(
                'reason_note_required',
                'El motivo seleccionado exige una nota explicativa.',
                $branchId,
                $requesterId,
                null,
                $type,
            );
        }
    }

    private function assertContextLengths(
        ?string $referenceType,
        ?string $oldValue,
        ?string $newValue,
        ?string $reason,
        int $branchId,
        string $type,
    ): void {
        $tooLong = ($referenceType !== null && mb_strlen($referenceType) > self::MAX_REFERENCE_TYPE_LENGTH)
            || ($oldValue !== null && mb_strlen($oldValue) > self::MAX_TEXT_VALUE_LENGTH)
            || ($newValue !== null && mb_strlen($newValue) > self::MAX_TEXT_VALUE_LENGTH)
            || ($reason !== null && mb_strlen($reason) > self::MAX_REASON_LENGTH);

        if ($tooLong) {
            $this->deny(
                'invalid_context',
                'El contexto de la autorización excede la longitud permitida.',
                $branchId,
                null,
                null,
                $type,
            );
        }
    }

    private function auditApproval(
        int $authorizationOverrideId,
        int $branchId,
        int $requesterId,
        int $authorizerId,
        string $type,
        ?int $posStationId,
        ?string $referenceType,
        ?int $referenceId,
        ?string $oldValue,
        ?string $newValue,
        ?int $reasonCodeId,
        ?string $reason,
    ): void {
        $this->auditLogger()->record(
            'authorization_override.approved',
            'authorization_overrides',
            $authorizationOverrideId,
            $branchId,
            null,
            [
                'override_type' => $type,
                'requested_by_user_id' => $requesterId,
                'authorized_by_user_id' => $authorizerId,
                'pos_station_id' => $posStationId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason_code_id' => $reasonCodeId,
                'reason' => $reason,
            ],
            self::MODULE_CODE,
            // is_security_event=1
            true,
        );
    }

    private function auditPinFailure(
        int $branchId,
        int $requesterId,
        int $authorizerId,
        string $type,
        int $attempts,
    ): void {
        $this->auditLogger()->record(
            'authorization_override.pin_failed',
            'users',
            $authorizerId,
            $branchId,
            null,
            [
                'reason' => 'pin_invalid',
                'override_type' => $type,
                'requester_user_id' => $requesterId,
                'authorizer_user_id' => $authorizerId,
                'failed_attempts' => $attempts,
            ],
            self::MODULE_CODE,
            // is_security_event=1
            true,
        );
    }

    private function auditPinLockout(
        int $branchId,
        int $requesterId,
        int $authorizerId,
        string $type,
    ): void {
        $this->auditLogger()->record(
            'authorization_override.pin_locked',
            'users',
            $authorizerId,
            $branchId,
            null,
            [
                'reason' => 'pin_locked',
                'override_type' => $type,
                'requester_user_id' => $requesterId,
                'authorizer_user_id' => $authorizerId,
                'lock_minutes' => self::LOCK_MINUTES,
            ],
            self::MODULE_CODE,
            // is_security_event=1
            true,
        );
    }

    private function auditDenial(
        string $reason,
        int $branchId,
        ?int $requesterId,
        ?int $authorizerId,
        string $type,
    ): void {
        $this->auditLogger()->record(
            'authorization_override.denied',
            'users',
            $authorizerId ?? $requesterId,
            $branchId,
            null,
            [
                'reason' => $reason,
                'override_type' => mb_substr($type, 0, self::MAX_REFERENCE_TYPE_LENGTH),
                'requester_user_id' => $requesterId,
                'authorizer_user_id' => $authorizerId,
            ],
            self::MODULE_CODE,
            // is_security_event=1
            true,
        );
    }

    private function deny(
        string $reason,
        string $message,
        int $branchId,
        ?int $requesterId,
        ?int $authorizerId,
        string $type,
    ): never {
        $this->auditDenial($reason, $branchId, $requesterId, $authorizerId, $type);

        throw new AuthorizationDeniedException($reason, $message);
    }

    private function denialMessage(string $reason): string
    {
        return match ($reason) {
            'authorizer_not_found' => 'El autorizador indicado no existe.',
            'authorizer_inactive' => 'El autorizador indicado no está activo.',
            'pin_locked' => 'El PIN del autorizador está bloqueado temporalmente.',
            default => 'No fue posible autorizar la operación.',
        };
    }

    private function configInt(string $key, int $fallback): int
    {
        $value = DB::table('system_configs')
            ->where('config_key', $key)
            ->value('config_value');

        if ($value === null || $value === '') {
            return $fallback;
        }

        $int = (int) $value;

        return $int > 0 ? $int : $fallback;
    }

    private function auditLogger(): AuditLogger
    {
        return $this->auditLogger ??= app(AuditLogger::class);
    }
}
