<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Inventory\StockTransferReceiveRequest;
use App\Http\Requests\Inventory\StockTransferStoreRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Inventory\InventoryModuleGate;
use App\Support\Inventory\InventoryWriteGuard;
use App\Support\Inventory\StockTransferException;
use App\Support\Inventory\StockTransferQueryService;
use App\Support\Inventory\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Traspasos entre sucursales (INVTRANSFER-001 · INV-06/RN-26).
 *
 * La sucursal de alcance sale solo de `{branch}` y `branch.access`; el actor
 * sale de la sesión. Lectura con `inventory.view`; crear, solicitar, enviar,
 * cancelar y recibir con `inventory.adjust`. El módulo deshabilitado responde
 * 403. La licencia en solo lectura permite listar/ver, pero el guard del
 * servicio bloquea las transiciones con 422. Nunca se acepta número, estado,
 * usuario, fecha, costo ni sucursal desde el cliente.
 */
class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferQueryService $query,
        private readonly StockTransferService $writes,
        private readonly InventoryModuleGate $modules,
        private readonly InventoryWriteGuard $guard,
    ) {}

    public function index(Request $request, string $branch): Response
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);

        $statusInput = $request->query('status');
        $status = is_string($statusInput) && in_array($statusInput, StockTransferQueryService::STATUSES, true)
            ? $statusInput
            : null;
        $numberInput = $request->query('number', '');
        $number = is_string($numberInput) ? mb_substr(trim($numberInput), 0, 30) : '';
        $perPageInput = $request->query('per_page', 25);
        $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 25;

        $transfers = $this->query->paginate((int) $branchModel->id, $status, $number, $perPage);
        $canAdjust = CheckPermission::allows($user, 'inventory.adjust', (int) $branchModel->id);

        return Inertia::render('Inventory/Transfers', [
            'branch' => $this->branchPayload($branchModel),
            'transfers' => [
                'data' => $transfers->items(),
                'links' => $transfers->linkCollection()->values()->all(),
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'from' => $transfers->firstItem(),
                'to' => $transfers->lastItem(),
                'total' => $transfers->total(),
                'per_page' => $transfers->perPage(),
            ],
            'filters' => [
                'status' => $status,
                'number' => $number,
                'per_page' => $transfers->perPage(),
            ],
            'statuses' => $this->statusOptions(),
            'destination_branches' => $canAdjust ? $this->destinationBranches((int) $branchModel->id) : [],
            'can_view_inventory' => CheckPermission::allows($user, 'inventory.view', (int) $branchModel->id),
            'can_adjust' => $canAdjust,
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function store(StockTransferStoreRequest $request, string $branch): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);
        $validated = $request->validated();

        try {
            $transferId = $this->writes->createDraft(
                (int) $branchModel->id,
                $validated['to_branch_id'],
                (int) $user->id,
                $validated['lines'],
                $validated['notes'] ?? null,
            );
        } catch (StockTransferException $exception) {
            $this->fail($exception);
        }

        return redirect()
            ->route('inventory.transfers.show', [
                'branch' => (int) $branchModel->id,
                'transfer' => $transferId,
            ])
            ->with('success', 'Se creó el borrador de traspaso. Revise las líneas y solicítelo cuando esté listo.');
    }

    public function show(Request $request, string $branch, string $transfer): Response
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);
        $transferId = $this->resolveTransferId($transfer);

        $detail = $this->query->detail($transferId, (int) $branchModel->id);

        if ($detail === null) {
            abort(403, 'Traspaso no encontrado o fuera del alcance de la sucursal.');
        }

        $canAdjust = CheckPermission::allows($user, 'inventory.adjust', (int) $branchModel->id);
        $readOnly = $this->guard->isReadOnly();
        $canMutate = $canAdjust && ! $readOnly;

        foreach ($detail['actions'] as $action => $allowed) {
            $detail['actions'][$action] = $allowed && $canMutate;
        }

        return Inertia::render('Inventory/TransferShow', [
            'branch' => $this->branchPayload($branchModel),
            'transfer' => $detail,
            'can_view_inventory' => CheckPermission::allows($user, 'inventory.view', (int) $branchModel->id),
            'can_adjust' => $canAdjust,
            'read_only' => $readOnly,
        ]);
    }

    public function request(Request $request, string $branch, string $transfer): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);
        $transferId = $this->resolveTransferId($transfer);

        try {
            $number = $this->writes->request($transferId, (int) $branchModel->id, (int) $user->id);
        } catch (StockTransferException $exception) {
            $this->fail($exception);
        }

        return $this->backToTransfer((int) $branchModel->id, $transferId)
            ->with('success', "Traspaso solicitado con número {$number}.");
    }

    public function dispatch(Request $request, string $branch, string $transfer): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);
        $transferId = $this->resolveTransferId($transfer);

        try {
            $this->writes->dispatch($transferId, (int) $branchModel->id, (int) $user->id);
        } catch (StockTransferException $exception) {
            $this->fail($exception);
        }

        return $this->backToTransfer((int) $branchModel->id, $transferId)
            ->with('success', 'Traspaso enviado: la existencia se descontó solo en la sucursal de origen.');
    }

    public function cancel(Request $request, string $branch, string $transfer): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);
        $transferId = $this->resolveTransferId($transfer);

        try {
            $this->writes->cancel($transferId, (int) $branchModel->id, (int) $user->id);
        } catch (StockTransferException $exception) {
            $this->fail($exception);
        }

        return $this->backToTransfer((int) $branchModel->id, $transferId)
            ->with('success', 'Traspaso cancelado sin movimientos de existencia.');
    }

    public function receive(
        StockTransferReceiveRequest $request,
        string $branch,
        string $transfer,
    ): RedirectResponse {
        $this->abortIfModuleDisabled();
        $branchModel = $this->resolveRouteBranch($branch);
        $user = $this->actor($request);
        $transferId = $this->resolveTransferId($transfer);
        $validated = $request->validated();

        try {
            $this->writes->receive(
                $transferId,
                (int) $branchModel->id,
                (int) $user->id,
                $validated['lines'],
            );
        } catch (StockTransferException $exception) {
            $this->fail($exception);
        }

        return $this->backToTransfer((int) $branchModel->id, $transferId)
            ->with('success', 'Recepción registrada. Las cantidades no recibidas quedan como diferencia pendiente.');
    }

    private function backToTransfer(int $branchId, int $transferId): RedirectResponse
    {
        return redirect()->route('inventory.transfers.show', [
            'branch' => $branchId,
            'transfer' => $transferId,
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'No tiene acceso al inventario.');
        }

        return $user;
    }

    /**
     * Misma semántica que EnsureBranchAccess/Kardex: todo identificador
     * `is_numeric` se resuelve primero como ID y, si no existe, por código.
     * Así un código numericamente ambiguo (p. ej. `1E0`) no cambia el alcance.
     */
    private function resolveRouteBranch(string $identifier): Branch
    {
        $branch = null;

        if (is_numeric($identifier)) {
            $branch = Branch::query()->whereKey((int) $identifier)->first();
        }

        $branch ??= Branch::query()->where('code', $identifier)->first();

        if ($branch === null || ! $branch->is_active) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        return $branch;
    }

    private function resolveTransferId(string $transfer): int
    {
        if (! ctype_digit($transfer)) {
            abort(403, 'Traspaso no encontrado o fuera del alcance de la sucursal.');
        }

        $id = (int) $transfer;

        if ($id <= 0) {
            abort(403, 'Traspaso no encontrado o fuera del alcance de la sucursal.');
        }

        return $id;
    }

    private function abortIfModuleDisabled(): void
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de inventario no está habilitado.');
        }
    }

    /**
     * @return array{id: int, code: string, name: string}
     */
    private function branchPayload(Branch $branch): array
    {
        return [
            'id' => (int) $branch->id,
            'code' => (string) $branch->code,
            'name' => (string) $branch->name,
        ];
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function destinationBranches(int $originBranchId): array
    {
        return Branch::query()
            ->where('is_active', 1)
            ->where('id', '!=', $originBranchId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'code', 'name'])
            ->map(fn (Branch $branch): array => $this->branchPayload($branch))
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Borrador'],
            ['value' => 'requested', 'label' => 'Solicitado'],
            ['value' => 'in_transit', 'label' => 'En tránsito'],
            ['value' => 'received', 'label' => 'Recibido'],
            ['value' => 'partially_received', 'label' => 'Recibido parcialmente'],
            ['value' => 'cancelled', 'label' => 'Cancelado'],
        ];
    }

    private function fail(StockTransferException $exception): never
    {
        throw ValidationException::withMessages([
            $exception->field => $exception->getMessage(),
        ]);
    }
}
