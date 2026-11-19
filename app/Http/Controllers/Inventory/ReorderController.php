<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Inventory\ReorderFilterRequest;
use App\Http\Requests\Inventory\ReorderSettingsRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Inventory\InventoryModuleGate;
use App\Support\Inventory\InventoryWriteGuard;
use App\Support\Inventory\ReorderQueryService;
use App\Support\Inventory\ReorderSettingsException;
use App\Support\Inventory\ReorderSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reposición y stock crítico por sucursal (INVREORDER-001 · INV-07).
 *
 * Lectura con `inventory.view`; escritura solo de `reorder_point`,
 * `reorder_qty` y `max_stock` con `inventory.adjust`. La sucursal se resuelve
 * en servidor desde `{branch}` con la MISMA semántica que `EnsureBranchAccess`
 * (`is_numeric` -> ID, si no código) y el middleware `branch.access` valida la
 * pertenencia. El actor sale de la sesión. La configuración no altera
 * `current_stock` ni `stock_movements`.
 */
class ReorderController extends Controller
{
    public function __construct(
        private readonly ReorderQueryService $query,
        private readonly ReorderSettingsService $writes,
        private readonly InventoryModuleGate $modules,
        private readonly InventoryWriteGuard $guard,
    ) {}

    public function index(ReorderFilterRequest $request, string $branch): Response
    {
        $this->abortIfModuleDisabled();

        $branchModel = $this->resolveRouteBranch($branch);

        if ($branchModel === null) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        $data = $request->validated();
        $search = mb_substr(trim((string) ($data['q'] ?? '')), 0, 100);
        $critical = filter_var($data['critical'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 25;
        $branchId = (int) $branchModel->id;

        $rows = $this->query->paginate($branchId, $search, $critical, $perPage);
        $criticalCount = $this->query->criticalCount($branchId);
        $user = $request->user();

        return Inertia::render('Inventory/Reorder', [
            'branch' => [
                'id' => $branchId,
                'code' => (string) $branchModel->code,
                'name' => (string) $branchModel->name,
            ],
            'rows' => [
                'data' => $rows->items(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
                'total' => $rows->total(),
                'previous_page_url' => $rows->previousPageUrl(),
                'next_page_url' => $rows->nextPageUrl(),
            ],
            'filters' => [
                'q' => $search,
                'critical' => $critical,
                'per_page' => $perPage,
            ],
            'critical_count' => $criticalCount,
            'can_adjust' => $user instanceof User
                && CheckPermission::allows($user, 'inventory.adjust', $branchId),
            'can_view_inventory' => $user instanceof User
                && CheckPermission::allows($user, 'inventory.view', $branchId),
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function update(
        ReorderSettingsRequest $request,
        string $branch,
        string $variant,
    ): RedirectResponse {
        $this->abortIfModuleDisabled();

        $branchModel = $this->resolveRouteBranch($branch);

        if ($branchModel === null) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'No tiene acceso al inventario.');
        }

        $variantId = $this->variantIdentifier($variant);
        $data = $request->validated();

        try {
            $this->writes->update(
                (int) $branchModel->id,
                $variantId,
                $data['reorder_point'],
                $data['reorder_qty'] ?? null,
                $data['max_stock'] ?? null,
                (int) $user->id,
            );
        } catch (ReorderSettingsException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Se guardó la configuración de reposición de la variante.',
        );
    }

    private function abortIfModuleDisabled(): void
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de inventario no está habilitado.');
        }
    }

    /**
     * Resuelve `{branch}` con la MISMA semántica que `EnsureBranchAccess`:
     * todo identificador `is_numeric` se interpreta primero como ID (con
     * coerción numérica); si no existe, se busca por código. Así un código
     * legal numéricamente ambiguo (p. ej. `1E0`) no puede cambiar el alcance
     * que el middleware ya autorizó.
     */
    private function resolveRouteBranch(string $identifier): ?Branch
    {
        $branch = null;

        if (is_numeric($identifier)) {
            $branch = Branch::query()->whereKey((int) $identifier)->first();
        }

        $branch ??= Branch::query()->where('code', $identifier)->first();

        if ($branch === null || ! $branch->is_active) {
            return null;
        }

        return $branch;
    }

    private function variantIdentifier(string $variant): int
    {
        $id = filter_var($variant, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            abort(404, 'Variante no encontrada.');
        }

        return (int) $id;
    }
}
