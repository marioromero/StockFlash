<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Customers\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Customers\CustomerPresenter;
use App\Support\Customers\CustomerRut;
use App\Support\Customers\CustomerWriteGuard;
use App\Support\Customers\PrivacyKeyException;
use App\Support\Customers\RutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro de clientes P0 (Parte 1 §8.8 · CUS-01/02/03; RN-58/61/64).
 *
 * Lectura con `customers.view`, mutaciones con `customers.manage` y revelado
 * con `customers.view_sensitive`. Las respuestas por defecto solo contienen
 * máscaras; el RUT, contacto y datos tributarios en claro solo salen por el
 * endpoint de revelado, que audita la lectura.
 */
class CustomerController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CustomerPresenter $presenter,
        private readonly CustomerRut $rut,
        private readonly CustomerWriteGuard $guard,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        // El RUT nunca viaja por query string. Una petición manual/forjada con
        // `rut` se redirige usando solo filtros conocidos, sin leer el valor.
        if ($request->query->has('rut')) {
            $query = [];

            foreach (['q', 'group_id', 'status', 'per_page', 'page'] as $key) {
                if ($request->query->has($key)) {
                    $query[$key] = $request->query->get($key);
                }
            }

            return redirect()->route('customers.index', $query);
        }

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $rawStatus = $request->query('status');

        if ($rawStatus !== null && ! is_string($rawStatus)) {
            throw ValidationException::withMessages([
                'status' => 'El estado debe ser activo, inactivo o bloqueado.',
            ]);
        }

        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : null;

        if ($status !== null && ! in_array($status, ['active', 'inactive', 'blocked'], true)) {
            throw ValidationException::withMessages([
                'status' => 'El estado debe ser activo, inactivo o bloqueado.',
            ]);
        }

        $groupId = $this->optionalGroupId($request);
        $rutHash = $this->sessionRutHash($request);
        $perPage = $this->perPage($request);
        $user = $request->user();

        $query = Customer::query()->with('group');

        if ($search !== '') {
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($search).'%']);
        }

        if ($groupId !== null) {
            $query->where('customer_group_id', $groupId);
        }

        if ($status === 'active') {
            $query->where('is_active', 1)->whereNull('blocked_at');
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0)->whereNull('blocked_at');
        } elseif ($status === 'blocked') {
            $query->whereNotNull('blocked_at');
        }

        if ($rutHash !== null) {
            $query->where('fiscal_id_hash', $rutHash);
        }

        $customers = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);

        return Inertia::render('Customers/Index', [
            'customers' => [
                'data' => collect($customers->items())
                    ->map(fn (Customer $customer): array => $this->presenter->listRow($customer))
                    ->values()
                    ->all(),
                'links' => $customers->linkCollection()->values()->all(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
            ],
            'groups' => $this->groupOptions(),
            'filters' => [
                'q' => $search,
                'group_id' => $groupId,
                'status' => $status,
                // Ni el RUT ni el índice ciego se devuelven a la interfaz:
                // solo se informa que el filtro exacto está activo.
                'rut_filter' => $rutHash !== null,
            ],
            'can_manage' => $user instanceof User && CheckPermission::allows($user, 'customers.manage'),
            'can_view_sensitive' => $user instanceof User && CheckPermission::allows($user, 'customers.view_sensitive'),
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    /**
     * Búsqueda exacta por RUT sin exponerlo en URL, props ni historial.
     *
     * Solo persiste el índice ciego de 64 hex en sesión. Los errores se
     * devuelven por sesión sin reenviar el valor ingresado.
     */
    public function searchByRut(Request $request): RedirectResponse
    {
        $validator = Validator::make(
            $request->only('rut'),
            ['rut' => ['required', 'string', 'max:30']],
            [
                'rut.required' => 'Ingrese un RUT válido.',
                'rut.string' => 'Ingrese un RUT válido.',
                'rut.max' => 'El RUT no puede superar 30 caracteres.',
            ],
        );

        if ($validator->fails()) {
            return redirect()->route('customers.index')->withErrors($validator);
        }

        try {
            $canonical = $this->rut->normalize((string) $validator->validated()['rut']);
            $hash = $this->rut->blindIndex($canonical);
        } catch (RutException|PrivacyKeyException $exception) {
            return redirect()
                ->route('customers.index')
                ->withErrors([$exception->field => $exception->getMessage()]);
        }

        $request->session()->put('customers.rut_hash', $hash);

        return redirect()->route('customers.index');
    }

    public function clearRutSearch(Request $request): RedirectResponse
    {
        $request->session()->forget('customers.rut_hash');

        return redirect()->route('customers.index');
    }

    public function show(Request $request, string $customer): Response
    {
        $model = $this->resolveCustomer($customer);
        $user = $request->user();

        return Inertia::render('Customers/Show', [
            'customer' => $this->presenter->detail($model),
            'groups' => $this->groupOptions(),
            'can_manage' => $user instanceof User && CheckPermission::allows($user, 'customers.manage'),
            'can_view_sensitive' => $user instanceof User && CheckPermission::allows($user, 'customers.view_sensitive'),
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $customer = DB::transaction(function () use ($validated): Customer {
            $this->guard->assertWritable();

            $data = [
                'type' => (string) $validated['type'],
                'name' => (string) $validated['name'],
                'trade_name' => $validated['trade_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'business_activity' => $validated['business_activity'] ?? null,
                'fiscal_address' => $validated['fiscal_address'] ?? null,
                'fiscal_commune' => $validated['fiscal_commune'] ?? null,
                'fiscal_city' => $validated['fiscal_city'] ?? null,
                'customer_group_id' => $validated['customer_group_id'] ?? null,
                'default_discount_percent' => $validated['default_discount_percent'] ?? null,
                'max_discount_percent' => $validated['max_discount_percent'] ?? null,
                // Valores de servidor: el cliente no los controla.
                'marketing_opt_in' => 0,
                'origin' => 'pos',
                'last_activity_at' => null,
                'is_active' => true,
                'blocked_at' => null,
                'anonymized_at' => null,
            ];

            $rutProvided = isset($validated['rut']) && is_string($validated['rut']) && trim($validated['rut']) !== '';

            if ($rutProvided) {
                [$hash, $encrypted] = $this->identity((string) $validated['rut'], null);
                $data['fiscal_id_hash'] = $hash;
                $data['fiscal_id_encrypted'] = $encrypted;
            }

            try {
                $customer = Customer::create($data);
            } catch (QueryException) {
                if ($rutProvided) {
                    throw ValidationException::withMessages([
                        'rut' => 'Ya existe un cliente con ese RUT.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'customer' => 'No fue posible registrar el cliente.',
                ]);
            }

            $this->auditLogger->record(
                'customers.created',
                'customers',
                (int) $customer->id,
                null,
                null,
                $this->auditValues($customer),
                'customers',
            );

            return $customer;
        });

        return redirect()
            ->route('customers.show', ['customer' => $customer->id])
            ->with('success', 'Cliente registrado.');
    }

    public function update(CustomerRequest $request, string $customer): RedirectResponse
    {
        $model = $this->resolveCustomer($customer);
        $validated = $request->validated();

        $this->assertMutable($model);

        DB::transaction(function () use ($model, $validated): void {
            $this->guard->assertWritable();

            $data = [];

            foreach (['type', 'name', 'trade_name'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $data[$field] = $validated[$field];
                }
            }

            if (array_key_exists('customer_group_id', $validated)) {
                $data['customer_group_id'] = $validated['customer_group_id'];
            }

            if (array_key_exists('default_discount_percent', $validated)) {
                $data['default_discount_percent'] = $validated['default_discount_percent'];
            }

            if (array_key_exists('max_discount_percent', $validated)) {
                $data['max_discount_percent'] = $validated['max_discount_percent'];
            }

            // Datos sensibles: en blanco/ausente conserva el valor actual; un
            // valor nuevo reemplaza. No se acepta limpiar desde el formulario.
            foreach (['email', 'phone', 'business_activity', 'fiscal_address', 'fiscal_commune', 'fiscal_city'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                    $data[$field] = $validated[$field];
                }
            }

            $rutProvided = isset($validated['rut']) && is_string($validated['rut']) && trim($validated['rut']) !== '';

            if ($rutProvided) {
                [$hash, $encrypted] = $this->identity((string) $validated['rut'], (int) $model->id);
                $data['fiscal_id_hash'] = $hash;
                $data['fiscal_id_encrypted'] = $encrypted;
            }

            try {
                $model->fill($data)->save();
            } catch (QueryException) {
                if ($rutProvided) {
                    throw ValidationException::withMessages([
                        'rut' => 'Ya existe un cliente con ese RUT.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'customer' => 'No fue posible actualizar el cliente.',
                ]);
            }

            $this->auditLogger->record(
                'customers.updated',
                'customers',
                (int) $model->id,
                null,
                null,
                $this->auditValues($model, array_keys($data)),
                'customers',
            );
        });

        return redirect()
            ->route('customers.show', ['customer' => $model->id])
            ->with('success', 'Cliente actualizado.');
    }

    public function activate(string $customer): RedirectResponse
    {
        return $this->changeStatus($customer, true);
    }

    public function deactivate(string $customer): RedirectResponse
    {
        return $this->changeStatus($customer, false);
    }

    public function reveal(string $customer): JsonResponse
    {
        $model = $this->resolveCustomer($customer);

        $payload = DB::transaction(function () use ($model): array {
            // Lectura de datos personales: solo tipo e ID, sin valores.
            $this->auditLogger->recordRead('customers.revealed', 'customers', (int) $model->id, 'customers');

            try {
                $rut = $this->rut->decrypt($model->fiscal_id_encrypted);
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'rut' => 'No fue posible descifrar el RUT almacenado.',
                ]);
            }

            return [
                'customer_id' => (int) $model->id,
                'rut' => $rut,
                'email' => $model->email,
                'phone' => $model->phone,
                'business_activity' => $model->business_activity,
                'fiscal_address' => $model->fiscal_address,
                'fiscal_commune' => $model->fiscal_commune,
                'fiscal_city' => $model->fiscal_city,
            ];
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function changeStatus(string $customer, bool $active): RedirectResponse
    {
        $model = $this->resolveCustomer($customer);

        $this->assertMutable($model);

        if ($active && $model->blocked_at !== null) {
            throw ValidationException::withMessages([
                'customer' => 'El cliente está bloqueado y no se puede reactivar mientras conserve el bloqueo.',
            ]);
        }

        DB::transaction(function () use ($model, $active): void {
            $this->guard->assertWritable();

            $model->is_active = $active;
            $model->save();

            $this->auditLogger->record(
                $active ? 'customers.activated' : 'customers.deactivated',
                'customers',
                (int) $model->id,
                null,
                null,
                ['id' => (int) $model->id, 'is_active' => $active],
                'customers',
            );
        });

        return redirect()
            ->route('customers.show', ['customer' => $model->id])
            ->with('success', $active ? 'Cliente activado.' : 'Cliente desactivado.');
    }

    private function assertMutable(Customer $customer): void
    {
        if ($customer->anonymized_at !== null) {
            throw ValidationException::withMessages([
                'customer' => 'El cliente está anonimizado y no admite cambios ni cambios de estado.',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function identity(string $raw, ?int $ignoreId): array
    {
        try {
            $canonical = $this->rut->normalize($raw);
            $hash = $this->rut->blindIndex($canonical);
        } catch (RutException|PrivacyKeyException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }

        $duplicate = Customer::withTrashed()
            ->where('fiscal_id_hash', $hash);

        if ($ignoreId !== null) {
            $duplicate->whereKeyNot($ignoreId);
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'rut' => 'Ya existe un cliente con ese RUT.',
            ]);
        }

        return [$hash, $this->rut->encrypt($canonical)];
    }

    private function resolveCustomer(string $identifier): Customer
    {
        if (! ctype_digit($identifier)) {
            abort(404, 'Cliente no encontrado.');
        }

        return Customer::query()->with('group')->findOrFail((int) $identifier);
    }

    private function optionalGroupId(Request $request): ?int
    {
        $raw = $request->query('group_id');

        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw) || ! ctype_digit($raw) || (int) $raw < 1) {
            throw ValidationException::withMessages([
                'group_id' => 'El grupo de clientes del filtro no es válido.',
            ]);
        }

        return (int) $raw;
    }

    private function sessionRutHash(Request $request): ?string
    {
        $hash = $request->session()->get('customers.rut_hash');

        if ($hash === null) {
            return null;
        }

        if (! is_string($hash) || preg_match('/\A[0-9a-f]{64}\z/i', $hash) !== 1) {
            $request->session()->forget('customers.rut_hash');

            return null;
        }

        return strtolower($hash);
    }

    private function perPage(Request $request): int
    {
        $raw = $request->query('per_page');
        $value = is_numeric($raw) ? (int) $raw : 25;

        return max(1, min(100, $value));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function groupOptions(): array
    {
        return CustomerGroup::query()
            ->orderBy('code')
            ->orderBy('id')
            ->get(['id', 'code', 'name'])
            ->map(fn (CustomerGroup $group): array => [
                'id' => (int) $group->id,
                'code' => (string) $group->code,
                'name' => (string) $group->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    private function auditValues(Customer $customer, ?array $fields = null): array
    {
        $values = [
            'id' => (int) $customer->id,
            'customer_group_id' => $customer->customer_group_id !== null
                ? (int) $customer->customer_group_id
                : null,
            'default_discount_percent' => $this->presenter->decimal($customer->default_discount_percent),
            'max_discount_percent' => $this->presenter->decimal($customer->max_discount_percent),
            'is_active' => (bool) $customer->is_active,
            'blocked' => $customer->blocked_at !== null,
            'anonymized' => $customer->anonymized_at !== null,
        ];

        if ($fields !== null) {
            $values['fields'] = $fields;
        }

        return $values;
    }
}
