<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserBranchAssignmentRequest;
use App\Http\Requests\UserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\UserAccessManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración global de usuarios (USERS-001 · CORE-03).
 *
 * Todas las escrituras y sus eventos de auditoría ocurren en la misma
 * transacción. La autorización real se aplica en las rutas con
 * `can.permission:users.manage`.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UserAccessManager $accessManager,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::query()->with([
            'globalRole',
            'defaultBranch',
            'branchAssignments.role',
            'branchAssignments.branch',
        ]);

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';

            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }

        // PRV-06/CORE-10: registrar la lectura ANTES de entregar los datos.
        // Si la auditoría falla, la excepción impide la respuesta.
        $this->auditLogger->recordRead('user.list_viewed', 'users');

        $users = $query
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serialize($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'user' => null,
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $assignments = $this->assignmentRows($data['assignments'] ?? []);

        try {
            DB::transaction(function () use ($data, $assignments): void {
                $this->accessManager->assertCanCreateOrActivate();

                $attributes = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'global_role_id' => $data['global_role_id'] ?? null,
                    'default_branch_id' => $data['default_branch_id'] ?? null,
                    'max_discount_percent' => $data['max_discount_percent'] ?? null,
                    'max_return_amount' => $data['max_return_amount'] ?? null,
                    'is_active' => true,
                    'must_change_password' => true,
                    'password_changed_at' => now(),
                ];

                if (($data['pin'] ?? null) !== null) {
                    $attributes['pin_hash'] = Hash::make((string) $data['pin']);
                    $attributes['pin_failed_attempts'] = 0;
                    $attributes['pin_locked_until'] = null;
                }

                $user = User::create($attributes);

                // PRV-06/CORE-10: se audita el alta PII sin copiar nombre ni
                // correo; solo se dejan los nombres de los campos afectados.
                $newValues = $this->auditValues($user);
                $newValues['personal_data_fields'] = ['name', 'email'];

                $this->auditLogger->record(
                    'user.created',
                    'users',
                    (int) $user->id,
                    null,
                    null,
                    $newValues,
                    'users',
                    true,
                    true,
                );

                $this->replaceAssignments($user, $assignments);

                if (($data['pin'] ?? null) !== null) {
                    $this->auditLogger->record(
                        'user.pin_reset',
                        'users',
                        (int) $user->id,
                        null,
                        null,
                        null,
                        'users',
                        true,
                    );
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowEmailViolation($exception);
        }

        $redirect = redirect()
            ->route('users.index')
            ->with('success', 'Usuario creado correctamente.');

        if (($data['global_role_id'] ?? null) === null && $assignments === []) {
            $redirect->with('warning', 'El usuario no tiene sucursales asignadas y no tendrá acceso operativo hasta que se le asigne una.');
        }

        return $redirect;
    }

    public function edit(User $user): Response
    {
        $user->load([
            'globalRole',
            'defaultBranch',
            'branchAssignments.role',
            'branchAssignments.branch',
        ]);

        $this->auditLogger->recordRead('user.record_viewed', 'users', (int) $user->id);

        return Inertia::render('Users/Form', [
            'user' => $this->serialize($user),
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $hasAssignmentsInput = array_key_exists('assignments', $data);

        $user->load(['globalRole', 'branchAssignments.role', 'branchAssignments.branch']);

        $globalRoleId = array_key_exists('global_role_id', $data)
            ? ($data['global_role_id'] === null ? null : (int) $data['global_role_id'])
            : ($user->global_role_id === null ? null : (int) $user->global_role_id);

        $previousGlobalRoleId = $user->global_role_id === null ? null : (int) $user->global_role_id;
        // `global_role_id` solo para cambios de rol global; conversiones y
        // reemplazos de asignaciones usan la clave neutra `access` (MENOR-5).
        $accessErrorKey = $previousGlobalRoleId !== null
            && $globalRoleId !== null
            && $previousGlobalRoleId !== $globalRoleId
                ? 'global_role_id'
                : 'access';

        $currentRows = $this->currentAssignmentRows($user);

        if ($globalRoleId !== null) {
            // Global: sin filas en branch_user.
            $proposedRows = [];
        } elseif ($hasAssignmentsInput) {
            $proposedRows = $this->assignmentRows($data['assignments'] ?? []);
        } else {
            $proposedRows = $currentRows;
        }

        try {
            DB::transaction(function () use ($data, $user, $actor, $globalRoleId, $proposedRows, $hasAssignmentsInput, $accessErrorKey): void {
                $oldValues = $this->auditValues($user);
                $originalName = (string) $user->name;
                $originalEmail = (string) $user->email;
                $oldRows = $this->currentAssignmentRows($user);
                $oldProvidesManage = $this->accessManager->hasUsersManage($user);
                $newProvidesManage = $this->accessManager->accessProvidesUsersManage($globalRoleId, $proposedRows);

                // MENOR-1: la sucursal inicial efectiva (informada o persistida)
                // debe pertenecer a las asignaciones propuestas del usuario por
                // sucursal. Se valida dentro de la transacción antes de escribir.
                $effectiveDefaultBranchId = array_key_exists('default_branch_id', $data)
                    ? ($data['default_branch_id'] === null ? null : (int) $data['default_branch_id'])
                    : ($user->default_branch_id === null ? null : (int) $user->default_branch_id);

                if (
                    $globalRoleId === null
                    && $effectiveDefaultBranchId !== null
                    && ! array_key_exists($effectiveDefaultBranchId, $proposedRows)
                ) {
                    throw ValidationException::withMessages([
                        'default_branch_id' => 'La sucursal inicial efectiva debe estar entre las sucursales asignadas al usuario por sucursal.',
                    ]);
                }

                $this->accessManager->assertAccessChangeKeepsAdmin($user, $actor, $newProvidesManage, $accessErrorKey);

                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->global_role_id = $globalRoleId;
                $user->default_branch_id = $effectiveDefaultBranchId;

                if (array_key_exists('max_discount_percent', $data)) {
                    $user->max_discount_percent = $data['max_discount_percent'];
                }

                if (array_key_exists('max_return_amount', $data)) {
                    $user->max_return_amount = $data['max_return_amount'];
                }

                $passwordChanged = ($data['password'] ?? null) !== null;
                $pinChanged = false;

                if ($passwordChanged) {
                    $user->password = (string) $data['password'];
                    $user->must_change_password = true;
                    $user->password_changed_at = now();
                    $user->failed_login_attempts = 0;
                    $user->locked_until = null;
                }

                if (($data['pin_clear'] ?? false)) {
                    $user->pin_hash = null;
                    $user->pin_failed_attempts = 0;
                    $user->pin_locked_until = null;
                    $pinChanged = true;
                } elseif (($data['pin'] ?? null) !== null) {
                    $user->pin_hash = Hash::make((string) $data['pin']);
                    $user->pin_failed_attempts = 0;
                    $user->pin_locked_until = null;
                    $pinChanged = true;
                }

                $user->save();

                // PRV-06/CORE-10: el flag PII se decide comparando en memoria
                // los valores originales con los nuevos, antes de sanitizar.
                $personalDataChanges = $this->changedPersonalDataFields(
                    $originalName,
                    $originalEmail,
                    $user,
                );

                if ($globalRoleId !== null) {
                    // Al pasar a global se eliminan las asignaciones.
                    $this->replaceAssignments($user, [], audit: true);
                } elseif ($hasAssignmentsInput || $proposedRows !== $oldRows) {
                    $this->replaceAssignments($user, $proposedRows);
                }

                $newValues = $this->auditValues($user);

                if ($personalDataChanges !== []) {
                    $newValues['personal_data_fields'] = $personalDataChanges;
                }

                $this->auditLogger->record(
                    'user.updated',
                    'users',
                    (int) $user->id,
                    null,
                    $oldValues,
                    $newValues,
                    'users',
                    false,
                    $personalDataChanges !== [],
                );

                if (
                    (int) ($oldValues['global_role_id'] ?? 0) !== (int) ($newValues['global_role_id'] ?? 0)
                    || $oldRows !== $proposedRows
                ) {
                    $this->auditLogger->record(
                        'user.role_changed',
                        'users',
                        (int) $user->id,
                        null,
                        [
                            'global_role_id' => $oldValues['global_role_id'],
                            'assignments' => $this->serializeAssignmentRows($oldRows),
                        ],
                        [
                            'global_role_id' => $newValues['global_role_id'],
                            'assignments' => $this->serializeAssignmentRows($proposedRows),
                        ],
                        'users',
                        true,
                    );
                }

                if ($oldProvidesManage && ! $newProvidesManage) {
                    $this->auditLogger->record(
                        'user.access_revoked',
                        'users',
                        (int) $user->id,
                        null,
                        null,
                        null,
                        'users',
                        true,
                    );
                }

                if ($passwordChanged) {
                    $this->auditLogger->record(
                        'user.password_reset',
                        'users',
                        (int) $user->id,
                        null,
                        null,
                        null,
                        'users',
                        true,
                    );
                }

                if ($pinChanged) {
                    $this->auditLogger->record(
                        'user.pin_reset',
                        'users',
                        (int) $user->id,
                        null,
                        null,
                        null,
                        'users',
                        true,
                    );
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowEmailViolation($exception);
        }

        $redirect = redirect()
            ->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');

        if ($globalRoleId === null && $proposedRows === []) {
            $redirect->with('warning', 'El usuario no tiene sucursales asignadas y no tendrá acceso operativo hasta que se le asigne una.');
        }

        return $redirect;
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        if ($user->is_active) {
            return redirect()
                ->route('users.index')
                ->with('warning', 'El usuario ya estaba activo.');
        }

        DB::transaction(function () use ($user): void {
            $this->accessManager->assertCanCreateOrActivate();

            $user->is_active = true;
            $user->save();

            $this->auditLogger->record(
                'user.activated',
                'users',
                (int) $user->id,
                null,
                ['is_active' => false],
                ['is_active' => true],
                'users',
                true,
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario activado correctamente.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ((int) $actor->id === (int) $user->id) {
            abort(403, 'No puede desactivar su propia cuenta.');
        }

        if (! $user->is_active) {
            return redirect()
                ->route('users.index')
                ->with('warning', 'El usuario ya estaba desactivado.');
        }

        DB::transaction(function () use ($user, $actor): void {
            $this->accessManager->assertCanDeactivateOrDelete($user, $actor);

            $user->is_active = false;
            $user->save();

            $this->auditLogger->record(
                'user.deactivated',
                'users',
                (int) $user->id,
                null,
                ['is_active' => true],
                ['is_active' => false],
                'users',
                true,
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario desactivado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ((int) $actor->id === (int) $user->id) {
            abort(403, 'No puede eliminar su propia cuenta.');
        }

        DB::transaction(function () use ($user, $actor): void {
            $this->accessManager->assertCanDeactivateOrDelete($user, $actor);

            $oldValues = $this->auditValues($user);

            // Antes de eliminar se deja inactivo para invalidar la sesión en su
            // siguiente petición (el fingerprint cambia).
            if ($user->is_active) {
                $user->is_active = false;
                $user->save();
            }

            $user->delete();

            $this->auditLogger->record(
                'user.deleted',
                'users',
                (int) $user->id,
                null,
                $oldValues,
                [
                    'is_active' => false,
                    'deleted_at' => optional($user->deleted_at)->toDateTimeString(),
                    // Sin valores PII: solo se deja constancia de los campos
                    // personales que la baja deja de exponer.
                    'personal_data_fields' => ['name', 'email'],
                ],
                'users',
                true,
                true,
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario eliminado lógicamente. Su correo sigue reservado.');
    }

    /**
     * @param  array<int, array{branch_id: int|string, role_id: int|string}>  $assignments
     * @return array<int, int> branch_id => role_id, ordenado por sucursal
     */
    private function assignmentRows(array $assignments): array
    {
        $rows = [];

        foreach ($assignments as $assignment) {
            $rows[(int) $assignment['branch_id']] = (int) $assignment['role_id'];
        }

        ksort($rows);

        return $rows;
    }

    /**
     * @return array<int, int>
     */
    private function currentAssignmentRows(User $user): array
    {
        return DB::table('branch_user')
            ->where('user_id', $user->id)
            ->orderBy('branch_id')
            ->pluck('role_id', 'branch_id')
            ->map(fn ($roleId): int => (int) $roleId)
            ->all();
    }

    /**
     * Reemplaza las asignaciones del usuario y audita los cambios de acceso.
     *
     * @param  array<int, int>  $rows
     */
    private function replaceAssignments(User $user, array $rows, bool $audit = true): void
    {
        $oldRows = $this->currentAssignmentRows($user);

        DB::table('branch_user')->where('user_id', $user->id)->delete();

        if ($rows !== []) {
            $insert = [];

            foreach ($rows as $branchId => $roleId) {
                $insert[] = [
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'role_id' => $roleId,
                ];
            }

            DB::table('branch_user')->insert($insert);
        }

        if (! $audit) {
            return;
        }

        foreach ($oldRows as $branchId => $roleId) {
            if (! array_key_exists($branchId, $rows) || $rows[$branchId] !== $roleId) {
                $this->auditLogger->record(
                    'user.branch_unassigned',
                    'users',
                    (int) $user->id,
                    (int) $branchId,
                    ['branch_id' => (int) $branchId, 'role_id' => (int) $roleId],
                    null,
                    'users',
                    true,
                );
            }
        }

        foreach ($rows as $branchId => $roleId) {
            if (! array_key_exists($branchId, $oldRows) || $oldRows[$branchId] !== $roleId) {
                $this->auditLogger->record(
                    'user.branch_assigned',
                    'users',
                    (int) $user->id,
                    (int) $branchId,
                    null,
                    ['branch_id' => (int) $branchId, 'role_id' => (int) $roleId],
                    'users',
                    true,
                );
            }
        }
    }

    /**
     * @param  array<int, int>  $rows
     * @return list<array{branch_id: int, role_id: int}>
     */
    private function serializeAssignmentRows(array $rows): array
    {
        $serialized = [];

        foreach ($rows as $branchId => $roleId) {
            $serialized[] = [
                'branch_id' => (int) $branchId,
                'role_id' => (int) $roleId,
            ];
        }

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        return [
            'global_roles' => Role::query()
                ->where('is_system', 1)
                ->orderBy('authority_level')
                ->get(['id', 'code', 'name'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                ])
                ->values()
                ->all(),
            'branch_roles' => Role::query()
                ->whereIn('code', UserBranchAssignmentRequest::BRANCH_ROLE_CODES)
                ->orderBy('authority_level')
                ->get(['id', 'code', 'name'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                ])
                ->values()
                ->all(),
            'branches' => Branch::query()
                ->where('is_active', 1)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $branch->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Serializa un usuario sin contraseña, PIN ni hashes.
     *
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        $user->loadMissing([
            'globalRole',
            'defaultBranch',
            'branchAssignments.role',
            'branchAssignments.branch',
        ]);

        $sortedAssignments = $user->branchAssignments
            ->sortBy(fn ($assignment): string => (string) $assignment->branch?->code)
            ->values();

        $assignments = $sortedAssignments
            ->map(fn ($assignment): array => [
                'branch_id' => (int) $assignment->branch_id,
                'branch_code' => $assignment->branch?->code,
                'branch_name' => $assignment->branch?->name,
                'role_id' => (int) $assignment->role_id,
                'role_code' => $assignment->role?->code,
                'role_name' => $assignment->role?->name,
            ])
            ->all();

        $primaryRole = $user->globalRole ?? $sortedAssignments->first()?->role;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'access_type' => $user->global_role_id !== null ? 'global' : 'branch',
            'global_role_id' => $user->global_role_id,
            'global_role' => $user->globalRole === null ? null : [
                'id' => $user->globalRole->id,
                'code' => $user->globalRole->code,
                'name' => $user->globalRole->name,
            ],
            'primary_role' => $primaryRole === null ? null : [
                'id' => $primaryRole->id,
                'code' => $primaryRole->code,
                'name' => $primaryRole->name,
            ],
            'default_branch_id' => $user->default_branch_id,
            'default_branch' => $user->defaultBranch === null ? null : [
                'id' => $user->defaultBranch->id,
                'code' => $user->defaultBranch->code,
                'name' => $user->defaultBranch->name,
            ],
            'assignments' => $assignments,
            'pin_configured' => $user->pin_hash !== null,
            'max_discount_percent' => $user->max_discount_percent === null
                ? null
                : (float) $user->max_discount_percent,
            'max_return_amount' => $user->max_return_amount === null
                ? null
                : (float) $user->max_return_amount,
        ];
    }

    /**
     * Instantánea no PII para `audit_logs` (PRV-06/CORE-10).
     *
     * Nunca incluye nombre ni correo: solo metadatos administrativos y de
     * acceso que no identifican a la persona.
     *
     * @return array<string, mixed>
     */
    private function auditValues(User $user): array
    {
        return [
            'global_role_id' => $user->global_role_id,
            'default_branch_id' => $user->default_branch_id,
            'is_active' => (bool) $user->is_active,
            'max_discount_percent' => $user->max_discount_percent === null
                ? null
                : (float) $user->max_discount_percent,
            'max_return_amount' => $user->max_return_amount === null
                ? null
                : (float) $user->max_return_amount,
        ];
    }

    /**
     * Nombres (no valores) de los campos PII modificados en la edición.
     *
     * Compara en memoria los valores originales con los del modelo ya
     * guardado, de modo que el flag PII no dependa de las cargas sanitizadas.
     *
     * @return list<string>
     */
    private function changedPersonalDataFields(string $originalName, string $originalEmail, User $user): array
    {
        $changed = [];

        if ($originalName !== (string) $user->name) {
            $changed[] = 'name';
        }

        if ($originalEmail !== (string) $user->email) {
            $changed[] = 'email';
        }

        return $changed;
    }

    private function rethrowEmailViolation(QueryException $exception): never
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        if (
            in_array($sqlState, ['23000', '23505'], true)
            && (
                str_contains($message, 'users_email_unique')
                || str_contains($message, 'users.email')
                || $driverCode === 1062
            )
        ) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe un usuario con ese correo electrónico.',
            ]);
        }

        throw $exception;
    }
}
