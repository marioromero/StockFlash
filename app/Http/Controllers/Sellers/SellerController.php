<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\SellerRequest;
use App\Models\Branch;
use App\Models\Seller;
use App\Models\User;
use App\Support\Sellers\SellerAccessService;
use App\Support\Sellers\SellerCommission;
use App\Support\Sellers\SellerService;
use App\Support\Sellers\SellerWriteGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro de vendedores P0 (Parte 1 §8.9 SEL-01; Parte 2 §6.5; RN-13/RN-14).
 *
 * Lectura y escritura requieren `sellers.manage` en el rol global o en alguna
 * sucursal asignada. El alcance por sucursal se filtra en servidor; nunca se
 * acepta `branch_id` de query/body como elevación de permisos. La fila
 * reservada `NONE` queda oculta y no es editable.
 */
class SellerController extends Controller
{
    public function __construct(
        private readonly SellerAccessService $access,
        private readonly SellerWriteGuard $guard,
        private readonly SellerService $service,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowed = $this->access->allowedBranchIds($user);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $branchId = $this->optionalBranchFilter($request, $allowed);
        $perPage = $this->perPage($request);

        $query = Seller::query()
            ->with([
                'user' => fn ($relation) => $relation->select('id', 'name'),
                'branches' => function ($relation) use ($allowed): void {
                    if ($allowed !== null) {
                        $relation->whereIn('branches.id', $allowed);
                    }
                },
            ])
            ->where('code', '!=', Seller::RESERVED_CODE);

        if ($search !== '') {
            $term = '%'.$this->escapeLike(mb_strtolower($search)).'%';

            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]);
            });
        }

        if ($branchId !== null) {
            $query->whereHas('branches', function ($branches) use ($branchId): void {
                $branches->where('branches.id', $branchId);
            });
        }

        $this->access->scopeVisibleSellers($query, $user);

        $sellers = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);

        return Inertia::render('Sellers/Index', [
            'sellers' => [
                'data' => collect($sellers->items())
                    ->map(fn (Seller $seller): array => $this->listRow($seller))
                    ->values()
                    ->all(),
                'links' => $sellers->linkCollection()->values()->all(),
                'current_page' => $sellers->currentPage(),
                'last_page' => $sellers->lastPage(),
                'from' => $sellers->firstItem(),
                'to' => $sellers->lastItem(),
                'total' => $sellers->total(),
                'per_page' => $sellers->perPage(),
            ],
            'filters' => [
                'q' => $search,
                'branch_id' => $branchId,
            ],
            'branch_options' => $this->access->branchOptions($user),
            'user_options' => $this->access->userOptions($user),
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function show(Request $request, string $seller): Response
    {
        $user = $request->user();
        $model = $this->resolveSeller($seller);

        if (! $this->access->canAccess($model, $user)) {
            abort(404, 'Vendedor no encontrado.');
        }

        $model->load([
            'user' => fn ($relation) => $relation->select('id', 'name'),
        ]);

        return Inertia::render('Sellers/Show', [
            'seller' => $this->detail($model, $user),
            'branch_options' => $this->access->branchOptions($user),
            'user_options' => $this->access->userOptions($user),
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function store(SellerRequest $request): RedirectResponse
    {
        $seller = $this->service->create($request->user(), $request->validated());

        return redirect()
            ->route('sellers.show', ['seller' => $seller->id])
            ->with('success', 'Vendedor registrado.');
    }

    public function update(SellerRequest $request, string $seller): RedirectResponse
    {
        $model = $this->resolveVisibleSeller($request->user(), $seller);

        $this->service->update($request->user(), $model, $request->validated());

        return redirect()
            ->route('sellers.show', ['seller' => $model->id])
            ->with('success', 'Vendedor actualizado.');
    }

    public function activate(Request $request, string $seller): RedirectResponse
    {
        $model = $this->resolveVisibleSeller($request->user(), $seller);

        $this->service->changeStatus($request->user(), $model, true);

        return redirect()
            ->route('sellers.show', ['seller' => $model->id])
            ->with('success', 'Vendedor activado.');
    }

    public function deactivate(Request $request, string $seller): RedirectResponse
    {
        $model = $this->resolveVisibleSeller($request->user(), $seller);

        $this->service->changeStatus($request->user(), $model, false);

        return redirect()
            ->route('sellers.show', ['seller' => $model->id])
            ->with('success', 'Vendedor desactivado.');
    }

    private function resolveVisibleSeller(User $user, string $identifier): Seller
    {
        $seller = $this->resolveSeller($identifier);

        if (! $this->access->canAccess($seller, $user)) {
            abort(404, 'Vendedor no encontrado.');
        }

        return $seller;
    }

    private function resolveSeller(string $identifier): Seller
    {
        if (! ctype_digit($identifier)) {
            abort(404, 'Vendedor no encontrado.');
        }

        $seller = Seller::query()->findOrFail((int) $identifier);

        if ($seller->isReserved()) {
            abort(404, 'Vendedor no encontrado.');
        }

        return $seller;
    }

    private function optionalBranchFilter(Request $request, ?array $allowed): ?int
    {
        $raw = $request->query('branch_id');

        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw) || ! ctype_digit($raw) || (int) $raw < 1) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal del filtro no es válida.',
            ]);
        }

        $branchId = (int) $raw;

        if ($allowed !== null) {
            if (! in_array($branchId, $allowed, true)) {
                throw ValidationException::withMessages([
                    'branch_id' => 'La sucursal del filtro no está dentro de su alcance.',
                ]);
            }

            return $branchId;
        }

        $exists = Branch::query()
            ->whereKey($branchId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal del filtro no existe.',
            ]);
        }

        return $branchId;
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
     * @return array<string, mixed>
     */
    private function listRow(Seller $seller): array
    {
        return [
            'id' => (int) $seller->id,
            'code' => (string) $seller->code,
            'name' => (string) $seller->name,
            'commission_percent' => $this->decimal($seller->commission_percent),
            'is_active' => (bool) $seller->is_active,
            'is_anonymized' => $seller->anonymized_at !== null,
            'user' => $seller->user === null ? null : [
                'id' => (int) $seller->user->id,
                'name' => (string) $seller->user->name,
            ],
            'branches' => $seller->branches
                ->map(fn (Branch $branch): array => [
                    'id' => (int) $branch->id,
                    'code' => (string) $branch->code,
                    'name' => (string) $branch->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Seller $seller, User $user): array
    {
        return [
            'id' => (int) $seller->id,
            'code' => (string) $seller->code,
            'name' => (string) $seller->name,
            'commission_percent' => $this->decimal($seller->commission_percent),
            'is_active' => (bool) $seller->is_active,
            'is_anonymized' => $seller->anonymized_at !== null,
            'user' => $seller->user === null ? null : [
                'id' => (int) $seller->user->id,
                'name' => (string) $seller->user->name,
            ],
            'branches' => $this->access->visibleBranches($seller, $user),
            'has_hidden_assignments' => $this->access->hasHiddenAssignments($seller, $user),
        ];
    }

    private function decimal(mixed $value): string
    {
        return SellerCommission::canonical((string) ($value ?? '0'));
    }
}
