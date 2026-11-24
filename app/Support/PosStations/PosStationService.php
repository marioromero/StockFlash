<?php

namespace App\Support\PosStations;

use App\Models\Branch;
use App\Models\PosStation;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso transaccionales de estaciones POS (CSH-02 / POS-02).
 *
 * Cada escritura (fila `pos_stations`, rotación o revocación de token y
 * auditoría) ocurre en una sola transacción con `PosStationWriteGuard`. La
 * auditoría registra solo ID, sucursal, estado, nombres de campos y flags
 * no secretos: nunca el token ni su hash.
 */
final class PosStationService
{
    private const MODULE = 'pos';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PosStationWriteGuard $guard,
        private readonly PosStationAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): PosStation
    {
        $this->assertManager($actor);

        $code = PosStationCode::normalize((string) ($data['code'] ?? ''));

        if (! PosStationCode::isValid($code)) {
            throw ValidationException::withMessages([
                'code' => 'El código debe tener entre 1 y 30 caracteres y usar solo letras, números, punto, guion o guion bajo.',
            ]);
        }

        $name = $this->name($data['name'] ?? null);
        $branchId = $this->branchId($data['branch_id'] ?? null);
        $printer = $this->printerProfile($data['printer_profile'] ?? null, present: array_key_exists('printer_profile', $data));

        try {
            return DB::transaction(function () use ($actor, $code, $name, $branchId, $printer): PosStation {
                $this->guard->assertWritable();
                $this->assertBranchAuthorized($actor, $branchId);
                $this->assertCodeAvailable($code);

                $station = PosStation::create([
                    'branch_id' => $branchId,
                    'code' => $code,
                    'name' => $name,
                    'printer_profile' => $printer,
                    'is_active' => true,
                    'last_seen_at' => null,
                ]);

                $this->auditLogger->record(
                    'pos_stations.created',
                    'pos_stations',
                    (int) $station->id,
                    $branchId,
                    null,
                    [
                        'id' => (int) $station->id,
                        'branch_id' => $branchId,
                        'is_active' => true,
                        'fields' => ['code', 'name', 'printer_profile', 'branch_id'],
                    ],
                    self::MODULE,
                );

                return $station;
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe una estación POS con ese código.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, PosStation $station, array $data): PosStation
    {
        $this->assertManager($actor);
        $this->assertAccess($actor, $station);

        return DB::transaction(function () use ($actor, $station, $data): PosStation {
            $this->guard->assertWritable();
            $this->assertAccess($actor, $station);

            $currentBranchId = (int) $station->branch_id;
            $branchId = array_key_exists('branch_id', $data)
                ? $this->branchId($data['branch_id'])
                : $currentBranchId;

            $branchChanged = $branchId !== $currentBranchId;

            if ($branchChanged) {
                $this->assertBranchAuthorized($actor, $branchId);
            }

            $name = array_key_exists('name', $data)
                ? $this->name($data['name'])
                : (string) $station->name;

            $printer = array_key_exists('printer_profile', $data)
                ? $this->printerProfile($data['printer_profile'], present: true)
                : ($station->printer_profile !== null ? (string) $station->printer_profile : null);

            $fields = [];

            if ($branchChanged) {
                $fields[] = 'branch_id';
            }

            if ($name !== (string) $station->name) {
                $fields[] = 'name';
            }

            if ($printer !== ($station->printer_profile !== null ? (string) $station->printer_profile : null)) {
                $fields[] = 'printer_profile';
            }

            $tokenInvalidated = $branchChanged && $station->getAttribute('device_token_hash') !== null;

            if ($fields === []) {
                // Un cambio de sucursal sin token no genera escritura.
                return $station;
            }

            $station->branch_id = $branchId;
            $station->name = $name;
            $station->printer_profile = $printer;

            if ($tokenInvalidated) {
                $station->device_token_hash = null;
            }

            $station->save();

            $this->auditLogger->record(
                'pos_stations.updated',
                'pos_stations',
                (int) $station->id,
                $branchId,
                null,
                [
                    'id' => (int) $station->id,
                    'branch_id' => $branchId,
                    'is_active' => (bool) $station->is_active,
                    'fields' => $fields,
                    'token_invalidated' => $tokenInvalidated,
                ],
                self::MODULE,
            );

            return $station;
        });
    }

    public function changeStatus(User $actor, PosStation $station, bool $active): PosStation
    {
        $this->assertManager($actor);
        $this->assertAccess($actor, $station);

        return DB::transaction(function () use ($actor, $station, $active): PosStation {
            $this->guard->assertWritable();
            $this->assertAccess($actor, $station);

            if ((bool) $station->is_active === $active) {
                return $station;
            }

            $tokenInvalidated = ! $active && $station->getAttribute('device_token_hash') !== null;

            $station->is_active = $active;

            if ($tokenInvalidated) {
                $station->device_token_hash = null;
            }

            $station->save();

            $this->auditLogger->record(
                $active ? 'pos_stations.activated' : 'pos_stations.deactivated',
                'pos_stations',
                (int) $station->id,
                (int) $station->branch_id,
                null,
                [
                    'id' => (int) $station->id,
                    'branch_id' => (int) $station->branch_id,
                    'is_active' => $active,
                    'token_invalidated' => $tokenInvalidated,
                ],
                self::MODULE,
            );

            return $station;
        });
    }

    /**
     * Vincula o revincula este navegador a la estación.
     *
     * Devuelve el token en claro solo al llamador inmediato para que lo
     * deposite en la cookie HttpOnly; nunca se persiste ni se audita.
     *
     * @return array{station: PosStation, token: string}
     */
    public function bind(User $actor, PosStation $station): array
    {
        $this->assertManager($actor);
        $this->assertAccess($actor, $station);

        return DB::transaction(function () use ($actor, $station): array {
            $this->guard->assertWritable();
            $this->assertAccess($actor, $station);

            if (! (bool) $station->is_active) {
                throw ValidationException::withMessages([
                    'station' => 'Solo una estación activa puede vincular un dispositivo.',
                ]);
            }

            $branchActive = Branch::query()
                ->whereKey($station->branch_id)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->exists();

            if (! $branchActive) {
                throw ValidationException::withMessages([
                    'station' => 'La sucursal de la estación debe estar activa para vincular un dispositivo.',
                ]);
            }

            $rawToken = PosStationToken::generate();

            // El token previo se invalida al sobrescribir el hash en la misma
            // transacción; nunca se conserva el valor anterior.
            $station->device_token_hash = PosStationToken::hash($rawToken);
            $station->save();

            $this->auditLogger->record(
                'pos_stations.token_bound',
                'pos_stations',
                (int) $station->id,
                (int) $station->branch_id,
                null,
                [
                    'id' => (int) $station->id,
                    'branch_id' => (int) $station->branch_id,
                    'token_rotated' => true,
                ],
                self::MODULE,
            );

            return ['station' => $station, 'token' => $rawToken];
        });
    }

    public function revoke(User $actor, PosStation $station): PosStation
    {
        $this->assertManager($actor);
        $this->assertAccess($actor, $station);

        return DB::transaction(function () use ($actor, $station): PosStation {
            $this->guard->assertWritable();
            $this->assertAccess($actor, $station);

            if ($station->getAttribute('device_token_hash') !== null) {
                $station->device_token_hash = null;
                $station->save();
            }

            $this->auditLogger->record(
                'pos_stations.token_revoked',
                'pos_stations',
                (int) $station->id,
                (int) $station->branch_id,
                null,
                [
                    'id' => (int) $station->id,
                    'branch_id' => (int) $station->branch_id,
                    'token_revoked' => true,
                ],
                self::MODULE,
            );

            return $station;
        });
    }

    private function assertManager(User $actor): void
    {
        if (! $this->access->canManageAnyBranch($actor)) {
            abort(403, 'No tiene permiso para administrar estaciones POS.');
        }
    }

    private function assertAccess(User $actor, PosStation $station): void
    {
        if (! $this->access->canAccess($station, $actor)) {
            abort(404, 'Estación POS no encontrada.');
        }
    }

    private function assertBranchAuthorized(User $actor, int $branchId): void
    {
        $allowed = $this->access->allowedBranchIds($actor);

        if ($allowed !== null && ! in_array($branchId, $allowed, true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Solo puede asignar una sucursal activa donde tenga permiso para administrar.',
            ]);
        }

        $exists = Branch::query()
            ->whereKey($branchId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal debe existir y estar activa.',
            ]);
        }
    }

    private function assertCodeAvailable(string $code): void
    {
        if (PosStation::withTrashed()->whereRaw('UPPER(code) = ?', [$code])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe una estación POS con ese código.',
            ]);
        }
    }

    private function name(mixed $value): string
    {
        $name = trim((string) ($value ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages([
                'name' => 'El nombre de la estación es obligatorio y no puede superar los 100 caracteres.',
            ]);
        }

        return $name;
    }

    private function branchId(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw ValidationException::withMessages([
            'branch_id' => 'Seleccione una sucursal activa.',
        ]);
    }

    private function printerProfile(mixed $value, bool $present): ?string
    {
        if (! $present || $value === null || $value === '') {
            return null;
        }

        $profile = (string) $value;

        if (! in_array($profile, PosStation::PRINTER_PROFILES, true)) {
            throw ValidationException::withMessages([
                'printer_profile' => 'El perfil de impresora no es válido.',
            ]);
        }

        return $profile;
    }
}
