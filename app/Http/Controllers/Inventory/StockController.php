<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Models\Branch;
use App\Models\User;
use App\Support\Inventory\InventoryModuleGate;
use App\Support\Inventory\InventoryWriteGuard;
use App\Support\Inventory\StockQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta de inventario por sucursal (INVCORE-001 · INV-03).
 *
 * Solo lectura en esta ola. La sucursal se resuelve en servidor y el middleware
 * `branch.access` valida pertenencia; `can.permission:inventory.view` valida el
 * permiso en el ámbito de la ruta. La habilitacion técnica del módulo es una
 * comprobación adicional y fail-closed.
 */
class StockController extends Controller
{
    public function __construct(
        private readonly StockQueryService $query,
        private readonly InventoryModuleGate $modules,
        private readonly InventoryWriteGuard $guard,
    ) {}

    public function entry(Request $request): RedirectResponse
    {
        $this->abortIfModuleDisabled();

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'No tiene acceso al inventario.');
        }

        $branch = $this->resolveAccessibleBranch($user);

        if ($branch === null) {
            abort(403, 'No tiene una sucursal activa disponible para consultar inventario.');
        }

        return redirect()->route('inventory.stock.index', ['branch' => $branch->id]);
    }

    public function index(Request $request, string $branch): Response
    {
        $this->abortIfModuleDisabled();

        $branchModel = $this->resolveRouteBranch($branch);

        if ($branchModel === null) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 25);
        $stock = $this->query->paginate((int) $branchModel->id, $search, $perPage);
        $user = $request->user();

        return Inertia::render('Inventory/Stock', [
            'branch' => [
                'id' => (int) $branchModel->id,
                'code' => (string) $branchModel->code,
                'name' => (string) $branchModel->name,
            ],
            'stock' => [
                'data' => $stock->items(),
                'links' => $stock->linkCollection()->values()->all(),
                'current_page' => $stock->currentPage(),
                'last_page' => $stock->lastPage(),
                'from' => $stock->firstItem(),
                'to' => $stock->lastItem(),
                'total' => $stock->total(),
            ],
            'filters' => ['q' => mb_substr($search, 0, 100)],
            'can_view_inventory' => $user instanceof User
                && CheckPermission::allows($user, 'inventory.view', $branchModel->id),
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    private function abortIfModuleDisabled(): void
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de inventario no está habilitado.');
        }
    }

    private function resolveRouteBranch(string $identifier): ?Branch
    {
        $branch = null;

        if (ctype_digit($identifier)) {
            $branch = Branch::query()->whereKey((int) $identifier)->first();
        }

        $branch ??= Branch::query()->where('code', $identifier)->first();

        if ($branch === null || ! $branch->is_active) {
            return null;
        }

        return $branch;
    }

    private function resolveAccessibleBranch(User $user): ?Branch
    {
        if ($user->isGlobal()) {
            if ($user->default_branch_id !== null) {
                $default = Branch::query()
                    ->whereKey($user->default_branch_id)
                    ->where('is_active', 1)
                    ->first();

                if ($default !== null) {
                    return $default;
                }
            }

            return Branch::query()->where('is_active', 1)->orderBy('id')->first();
        }

        $branchIds = $user->branchAssignments()
            ->orderBy('branch_id')
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($user->default_branch_id !== null) {
            array_unshift($branchIds, (int) $user->default_branch_id);
            $branchIds = array_values(array_unique($branchIds));
        }

        if ($branchIds === []) {
            return null;
        }

        $active = Branch::query()
            ->whereIn('id', $branchIds)
            ->where('is_active', 1)
            ->get()
            ->keyBy('id');

        foreach ($branchIds as $branchId) {
            if ($active->has($branchId)) {
                return $active->get($branchId);
            }
        }

        return null;
    }
}
