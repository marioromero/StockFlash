<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Customers\CustomerGroupRequest;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Customers\CustomerPresenter;
use App\Support\Customers\CustomerWriteGuard;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Grupos de clientes (Parte 2 §6.1 · CUS-02).
 *
 * Catálogo común a la instalación: no usa `branch.access`. Lectura con
 * `customers.view`; escritura con `customers.manage`. La licencia suspendida
 * fuera de gracia bloquea escrituras con 422. No se borra ninguna fila: el
 * estado se alterna con activar/desactivar.
 */
class CustomerGroupController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CustomerPresenter $presenter,
        private readonly CustomerWriteGuard $guard,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Customers/Groups', $this->listProps($request));
    }

    public function show(Request $request, string $group): Response
    {
        $model = $this->resolveGroup($group);

        return Inertia::render('Customers/Groups', array_merge(
            $this->listProps($request),
            ['selected_group' => $this->presenter->groupRow($model)],
        ));
    }

    public function store(CustomerGroupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $group = DB::transaction(function () use ($validated): CustomerGroup {
            $this->guard->assertWritable();

            $code = (string) $validated['code'];

            if (CustomerGroup::withTrashed()->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'Ya existe un grupo de clientes con ese código.',
                ]);
            }

            try {
                $group = CustomerGroup::create([
                    'code' => $code,
                    'name' => (string) $validated['name'],
                    'default_discount_percent' => $validated['default_discount_percent'] ?? '0.00',
                    'max_discount_percent' => $validated['max_discount_percent'] ?? null,
                    'is_active' => true,
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'code' => 'Ya existe un grupo de clientes con ese código.',
                ]);
            }

            $this->auditLogger->record(
                'customers.groups.created',
                'customer_groups',
                (int) $group->id,
                null,
                null,
                $this->auditValues($group),
                'customers',
            );

            return $group;
        });

        return redirect()
            ->route('customers.groups.index')
            ->with('success', 'Grupo de clientes creado.');
    }

    public function update(CustomerGroupRequest $request, string $group): RedirectResponse
    {
        $model = $this->resolveGroup($group);
        $validated = $request->validated();

        DB::transaction(function () use ($model, $validated): void {
            $this->guard->assertWritable();

            $data = [];

            foreach (['code', 'name'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $data[$field] = (string) $validated[$field];
                }
            }

            if (array_key_exists('default_discount_percent', $validated)) {
                $data['default_discount_percent'] = $validated['default_discount_percent'] ?? '0.00';
            }

            if (array_key_exists('max_discount_percent', $validated)) {
                $data['max_discount_percent'] = $validated['max_discount_percent'];
            }

            if (isset($data['code']) && CustomerGroup::withTrashed()
                ->where('code', $data['code'])
                ->whereKeyNot($model->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'Ya existe un grupo de clientes con ese código.',
                ]);
            }

            try {
                $model->fill($data)->save();
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'code' => 'Ya existe un grupo de clientes con ese código.',
                ]);
            }

            $this->auditLogger->record(
                'customers.groups.updated',
                'customer_groups',
                (int) $model->id,
                null,
                null,
                $this->auditValues($model, array_keys($data)),
                'customers',
            );
        });

        return redirect()
            ->route('customers.groups.index')
            ->with('success', 'Grupo de clientes actualizado.');
    }

    public function activate(string $group): RedirectResponse
    {
        return $this->changeStatus($group, true);
    }

    public function deactivate(string $group): RedirectResponse
    {
        return $this->changeStatus($group, false);
    }

    private function changeStatus(string $group, bool $active): RedirectResponse
    {
        $model = $this->resolveGroup($group);

        DB::transaction(function () use ($model, $active): void {
            $this->guard->assertWritable();

            $model->is_active = $active;
            $model->save();

            $this->auditLogger->record(
                $active ? 'customers.groups.activated' : 'customers.groups.deactivated',
                'customer_groups',
                (int) $model->id,
                null,
                null,
                ['id' => (int) $model->id, 'is_active' => $active],
                'customers',
            );
        });

        return redirect()
            ->route('customers.groups.index')
            ->with('success', $active ? 'Grupo activado.' : 'Grupo desactivado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function listProps(Request $request): array
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $perPage = $this->perPage($request);
        $user = $request->user();

        $query = CustomerGroup::query();

        if ($search !== '') {
            $pattern = '%'.$this->escapeLike($search).'%';
            $query->whereRaw("(code LIKE ? ESCAPE '!' OR name LIKE ? ESCAPE '!')", [$pattern, $pattern]);
        }

        $groups = $query
            ->orderBy('code')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'groups' => [
                'data' => collect($groups->items())
                    ->map(fn (CustomerGroup $group): array => $this->presenter->groupRow($group))
                    ->values()
                    ->all(),
                'links' => $groups->linkCollection()->values()->all(),
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'from' => $groups->firstItem(),
                'to' => $groups->lastItem(),
                'total' => $groups->total(),
                'per_page' => $groups->perPage(),
            ],
            'filters' => ['q' => $search],
            'can_manage' => $user instanceof User && CheckPermission::allows($user, 'customers.manage'),
            'read_only' => $this->guard->isReadOnly(),
        ];
    }

    private function resolveGroup(string $identifier): CustomerGroup
    {
        if (! ctype_digit($identifier)) {
            abort(404, 'Grupo de clientes no encontrado.');
        }

        return CustomerGroup::query()->findOrFail((int) $identifier);
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
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    private function auditValues(CustomerGroup $group, ?array $fields = null): array
    {
        $values = [
            'id' => (int) $group->id,
            'default_discount_percent' => $this->presenter->decimal($group->default_discount_percent),
            'max_discount_percent' => $this->presenter->decimal($group->max_discount_percent),
            'is_active' => (bool) $group->is_active,
        ];

        if ($fields !== null) {
            $values['fields'] = $fields;
        }

        return $values;
    }
}
