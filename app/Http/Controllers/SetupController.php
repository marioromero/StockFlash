<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Setup\SetupEnvironmentPolicy;
use App\Support\Setup\SetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Asistente de primer inicio (SETUP-001 · CORE-02).
 *
 * Una instalación virgen con esquema y semillas preparados configura una única
 * sucursal, un único Admin Central y solo las claves aprobadas, todo dentro de
 * una transacción. No migra, no siembra, no toca licencias, fiscal externo ni
 * documentos tributarios. Los secretos y PII nunca se registran ni se exponen.
 */
class SetupController extends Controller
{
    /**
     * Claves CFG-002 administradas por el asistente.
     *
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
     * Claves CFG-003 administradas por el asistente.
     *
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

    public function __construct(
        private readonly SetupService $setup,
        private readonly SetupEnvironmentPolicy $policy,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request): Response|SymfonyResponse|RedirectResponse
    {
        if (! $this->setup->schemaReady()) {
            return $this->schemaNotReadyResponse();
        }

        if ($this->setup->isCompleted()) {
            return redirect()->route('login');
        }

        if (! $this->setup->isPristine()) {
            return Inertia::render('Setup/Unavailable', [
                'reason_code' => 'INSTANCE_NOT_PRISTINE',
                'message' => 'Esta instalación ya contiene información. Por seguridad no es posible reabrir el asistente ni reiniciar datos. Ingrese con su cuenta de administración.',
            ]);
        }

        $policy = $this->policy->evaluateCurrent();

        if (! $policy['allowed']) {
            return Inertia::render('Setup/Unavailable', [
                'reason_code' => $policy['code'],
                'message' => $policy['message'],
            ]);
        }

        $response = Inertia::render('Setup/Wizard', [
            'flow_token' => $this->setup->issueFlowToken($request),
            'defaults' => $this->setup->initialDefaults(),
            'business_fields' => [
                'business_name' => 'Nombre del comercio',
                'fiscal_id' => 'RUT o identificador tributario',
                'fiscal_name' => 'Razón social',
                'commercial_activity' => 'Giro o actividad comercial',
                'fiscal_address' => 'Dirección tributaria',
                'fiscal_commune' => 'Comuna tributaria',
                'responsible_name' => 'Nombre del responsable',
                'responsible_email' => 'Correo del responsable',
                'responsible_phone' => 'Teléfono del responsable',
            ],
            'branch_types' => [
                ['value' => 'store', 'label' => 'Local'],
                ['value' => 'warehouse', 'label' => 'Bodega'],
                ['value' => 'online', 'label' => 'Online'],
            ],
        ])->toResponse($request);

        // O-06: el asistente contiene un token de flujo ligado a la sesión y
        // defaults; no debe quedar en cachés intermedias.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        // Fail-closed: se revalida política y estado antes de cualquier escritura.
        if (! $this->setup->schemaReady()) {
            return redirect()->route('setup.show');
        }

        if ($this->setup->isCompleted()) {
            abort(404);
        }

        if (! $this->setup->isPristine()) {
            abort(409, 'La instalación ya contiene usuarios o sucursales. No es posible reabrir el asistente.');
        }

        $policy = $this->policy->evaluateCurrent();

        if (! $policy['allowed']) {
            abort(423, $policy['message']);
        }

        $this->setup->assertFlowToken($request, $request->input('setup_token'));

        $data = $request->validated();

        $adminId = DB::transaction(function () use ($data): int {
            [ $branchId, $adminId ] = $this->createInitialBranchAndAdmin($data);

            $this->persistApprovedConfigs($data, $adminId);

            // Solo se marca completado al final y dentro de la misma transacción.
            DB::table('system_configs')
                ->where('config_key', 'is_setup_completed')
                ->update([
                    'config_value' => '1',
                    'updated_by_user_id' => $adminId,
                    'updated_at' => now(),
                ]);

            // Auditoría mínima: ids y códigos técnicos; nunca PII ni secretos.
            $this->auditLogger->record(
                'core.setup.completed',
                'system_configs',
                null,
                null,
                null,
                [
                    'branch_id' => $branchId,
                    'admin_user_id' => $adminId,
                    'config_keys_updated' => count(self::BUSINESS_KEYS) + count(self::GENERAL_KEYS),
                ],
                'core',
            );

            return $adminId;
        });

        // Se elige el ingreso explícito: el flujo público no crea una sesión
        // autenticada implícita ni reutiliza el fingerprint de otro usuario.
        $request->session()->forget(SetupService::SESSION_KEY);

        return redirect()
            ->route('login')
            ->with('success', 'Instalación completada. Ingrese con la cuenta Admin Central creada.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: int}
     */
    private function createInitialBranchAndAdmin(array $data): array
    {
        // a. Serializa la finalización releyendo la marca con lock.
        $this->ensureCompletionFlagRow();

        $flag = DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->lockForUpdate()
            ->value('config_value');

        if (in_array(strtolower(trim((string) $flag)), ['1', 'true'], true)) {
            abort(404);
        }

        // b. Rol real de semillas; si falta no se simula ni se migra.
        $roleId = DB::table('roles')
            ->where('code', 'central_admin')
            ->where('is_system', 1)
            ->lockForUpdate()
            ->value('id');

        if ($roleId === null) {
            throw ValidationException::withMessages([
                'setup' => 'No existe el rol de sistema Admin Central. La instalación requiere las semillas aprobadas y no ejecuta migraciones ni siembras automáticas.',
            ]);
        }

        // c. Revalidación dentro de la transacción (serialización lógica).
        if (DB::table('users')->count() > 0 || DB::table('branches')->count() > 0) {
            abort(409, 'La instalación ya contiene usuarios o sucursales. No es posible reabrir el asistente.');
        }

        // d. Sucursal inicial única.
        $branch = Branch::create([
            'code' => $data['branch_code'],
            'name' => $data['branch_name'],
            'type' => $data['branch_type'],
            'address' => $data['branch_address'] ?? null,
            'commune' => $data['branch_commune'] ?? null,
            'city' => $data['branch_city'] ?? null,
            'region' => $data['branch_region'] ?? null,
            'phone' => $data['branch_phone'] ?? null,
            'is_active' => true,
            'requires_cash_control' => true,
            'settings' => null,
        ]);

        $this->auditLogger->record(
            'core.setup.branch_created',
            'branches',
            (int) $branch->getKey(),
            (int) $branch->getKey(),
            null,
            ['type' => $branch->type],
            'core',
        );

        // e. Exactamente una cuenta Admin Central activa, sin PIN ni reseteos.
        $admin = User::create([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'global_role_id' => (int) $roleId,
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $this->auditLogger->record(
            'core.setup.admin_created',
            'users',
            (int) $admin->getKey(),
            (int) $branch->getKey(),
            null,
            ['role_code' => 'central_admin'],
            'core',
            true,
        );

        return [ (int) $branch->getKey(), (int) $admin->getKey() ];
    }

    /**
     * f. Persiste solo las 16 claves aprobadas preservando metadatos.
     *
     * @param  array<string, mixed>  $data
     */
    private function persistApprovedConfigs(array $data, int $adminId): void
    {
        $keys = [...self::BUSINESS_KEYS, ...self::GENERAL_KEYS];

        $existing = DB::table('system_configs')
            ->whereIn('config_key', $keys)
            ->get()
            ->keyBy('config_key');

        foreach ($keys as $key) {
            $value = $this->configValue($key, $data[$key] ?? null);
            $row = $existing->get($key);

            if ($row !== null) {
                DB::table('system_configs')
                    ->where('config_key', $key)
                    ->update([
                        'config_value' => $value,
                        'updated_by_user_id' => $adminId,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('system_configs')->insert([
                'config_key' => $key,
                'config_value' => $value,
                'value_type' => $this->valueType($key),
                'module_code' => null,
                'is_secret' => 0,
                'description' => null,
                'updated_by_user_id' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureCompletionFlagRow(): void
    {
        $exists = DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('system_configs')->insert([
            'config_key' => 'is_setup_completed',
            'config_value' => '0',
            'value_type' => 'bool',
            'module_code' => null,
            'is_secret' => 0,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configValue(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($key === 'prices_include_tax') {
            return $value ? '1' : '0';
        }

        if (in_array($key, ['currency_decimals', 'cash_rounding_step'], true)) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    private function valueType(string $key): string
    {
        if ($key === 'prices_include_tax') {
            return 'bool';
        }

        if (in_array($key, ['currency_decimals', 'cash_rounding_step'], true)) {
            return 'int';
        }

        return 'string';
    }

    private function schemaNotReadyResponse(): SymfonyResponse
    {
        return response(
            'El esquema de la base de datos no está preparado. El asistente no ejecuta migraciones automáticamente; contacte al responsable técnico.',
            503,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
