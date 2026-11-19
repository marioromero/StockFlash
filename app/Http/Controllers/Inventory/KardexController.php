<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Inventory\KardexFilterRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Inventory\InventoryModuleGate;
use App\Support\Inventory\InventoryWriteGuard;
use App\Support\Inventory\KardexQueryService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reporte Kardex fisico por sucursal (INVKARDEX-001 · INV-08).
 *
 * Solo lectura. La sucursal se resuelve en servidor desde `{branch}` (id o
 * codigo) y debe existir y estar activa; nunca se acepta `branch_id` desde el
 * query. El middleware `branch.access` valida la pertenencia y
 * `can.permission:inventory.view` la autorizacion. El reporte no escribe,
 * no recalcula saldos y no expone costos ni datos personales.
 */
class KardexController extends Controller
{
    public function __construct(
        private readonly KardexQueryService $query,
        private readonly InventoryModuleGate $modules,
        private readonly InventoryWriteGuard $guard,
    ) {}

    public function index(KardexFilterRequest $request, string $branch): Response
    {
        $this->abortIfModuleDisabled();

        $branchModel = $this->resolveRouteBranch($branch);

        if ($branchModel === null) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        $data = $request->validated();
        $search = mb_substr(trim((string) ($data['q'] ?? '')), 0, 100);
        $perPage = (int) ($data['per_page'] ?? 25);

        $movements = $this->query->paginate(
            (int) $branchModel->id,
            $search,
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            isset($data['from']) ? (string) $data['from'] : null,
            isset($data['to']) ? (string) $data['to'] : null,
            $perPage,
            isset($data['cursor']) ? (string) $data['cursor'] : null,
        );

        $user = $request->user();

        return Inertia::render('Inventory/Kardex', [
            'branch' => [
                'id' => (int) $branchModel->id,
                'code' => (string) $branchModel->code,
                'name' => (string) $branchModel->name,
            ],
            'movements' => [
                'data' => $movements->items(),
                'previous_page_url' => $movements->previousPageUrl(),
                'next_page_url' => $movements->nextPageUrl(),
                'per_page' => $movements->perPage(),
            ],
            'filters' => [
                'q' => $search,
                'variant_id' => isset($data['variant_id']) ? (int) $data['variant_id'] : null,
                'from' => isset($data['from']) ? (string) $data['from'] : null,
                'to' => isset($data['to']) ? (string) $data['to'] : null,
                'per_page' => $perPage,
            ],
            'read_only' => $this->guard->isReadOnly(),
            'can_view_inventory' => $user instanceof User
                && CheckPermission::allows($user, 'inventory.view', $branchModel->id),
        ]);
    }

    private function abortIfModuleDisabled(): void
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de inventario no está habilitado.');
        }
    }

    /**
     * Resuelve `{branch}` con la MISMA semantica que `EnsureBranchAccess`:
     * todo identificador `is_numeric` se interpreta primero como ID (con
     * coercion numerica); si no existe, se busca por codigo. Asi un codigo
     * legal numericamente ambiguo (p. ej. `1E0`) no puede cambiar el alcance
     * ya autorizado por el middleware, porque ambos resuelven igual.
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
}
