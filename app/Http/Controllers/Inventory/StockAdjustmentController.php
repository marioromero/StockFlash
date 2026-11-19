<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Requests\Inventory\StockInitialRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Authorization\AuthorizationDeniedException;
use App\Support\Inventory\InventoryModuleGate;
use App\Support\Inventory\StockWriteException;
use App\Support\Inventory\StockWriteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Escritura manual de existencias (INVWRITE-001 · INV-04/INV-05).
 *
 * Solo dos operaciones aprobadas: carga inicial única y ajuste con motivo.
 * La sucursal se resuelve desde `{branch}` y `branch.access`; el actor desde la
 * sesión. Nunca se aceptan `branch_id`, `user_id`, `on_hand`, `cost`, `type` ni
 * `source` desde el cliente. El módulo deshabilitado responde 403 y la licencia
 * en solo lectura se propaga como 422 desde el guard del ledger.
 */
class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockWriteService $writes,
        private readonly InventoryModuleGate $modules,
    ) {}

    public function storeInitial(StockInitialRequest $request, string $branch): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveBranch($branch);
        $user = $this->actor($request);

        $validated = $request->validated();

        try {
            $this->writes->loadInitial(
                (int) $branchModel->id,
                (int) $validated['variant_id'],
                $validated['quantity'],
                (int) $user->id,
            );
        } catch (StockWriteException $exception) {
            $this->fail($exception);
        }

        return back()->with('success', 'Se registró la carga inicial de existencias de la variante.');
    }

    public function storeAdjustment(StockAdjustmentRequest $request, string $branch): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveBranch($branch);
        $user = $this->actor($request);

        $validated = $request->validated();

        try {
            $this->writes->adjust(
                (int) $branchModel->id,
                (int) $validated['variant_id'],
                $validated['quantity'],
                $validated['reason_code_id'],
                $validated['note'] ?? null,
                (string) $validated['request_token'],
                (int) $user->id,
                isset($validated['authorizer_id']) ? (int) $validated['authorizer_id'] : null,
                $validated['pin'] ?? null,
            );
        } catch (StockWriteException $exception) {
            $this->fail($exception);
        } catch (AuthorizationDeniedException $exception) {
            throw ValidationException::withMessages(['pin' => $exception->getMessage()]);
        }

        return back()->with('success', 'Se registró el ajuste de existencias de la variante.');
    }

    private function actor(StockInitialRequest|StockAdjustmentRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'No tiene acceso al inventario.');
        }

        return $user;
    }

    private function resolveBranch(string $identifier): Branch
    {
        $branch = null;

        if (ctype_digit($identifier)) {
            $branch = Branch::query()->whereKey((int) $identifier)->first();
        }

        $branch ??= Branch::query()->where('code', $identifier)->first();

        if ($branch === null || ! $branch->is_active) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        return $branch;
    }

    private function abortIfModuleDisabled(): void
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de inventario no está habilitado.');
        }
    }

    private function fail(StockWriteException $exception): never
    {
        throw ValidationException::withMessages([
            $exception->field => $exception->getMessage(),
        ]);
    }
}
