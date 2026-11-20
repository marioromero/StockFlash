<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Inventory\InitialStockImportRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Inventory\InitialStockCsvParser;
use App\Support\Inventory\InitialStockImportService;
use App\Support\Inventory\InventoryModuleGate;
use App\Support\Inventory\InventoryWriteGuard;
use App\Support\Inventory\StockWriteException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Carga inicial de existencias desde CSV (INVCSV-001 · INV-05).
 *
 * La sucursal se resuelve solo desde `{branch}` (misma semántica que
 * `EnsureBranchAccess`: `is_numeric` → ID primero, si no código exacto), el
 * actor desde la sesión y el archivo desde el upload temporal. La vista previa
 * y el commit reparsean y revalidan los bytes; el commit es atómico. No se
 * aceptan `branch_id`, `user_id`, tipo, origen, saldos ni variantes desde el
 * cliente. Módulo deshabilitado o sucursal inválida responden 403; licencia en
 * solo lectura responde 422 y nunca se escribe.
 */
class InitialStockImportController extends Controller
{
    public function __construct(
        private readonly InitialStockImportService $imports,
        private readonly InventoryModuleGate $modules,
        private readonly InventoryWriteGuard $guard,
    ) {}

    public function index(Request $request, string $branch): Response
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->branchOrAbort($branch);

        return Inertia::render('Inventory/Import', $this->pageProps($request, $branchModel, null));
    }

    public function template(string $branch): StreamedResponse
    {
        $this->abortIfModuleDisabled();
        $this->branchOrAbort($branch);

        return response()->streamDownload(function (): void {
            // BOM UTF-8 para que Excel chileno reconozca acentos y el
            // separador `;` sin pasos manuales. No hay archivo persistente.
            echo "\xEF\xBB\xBF";
            echo "sku;cantidad\r\n";
        }, 'plantilla-stock-inicial.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(InitialStockImportRequest $request, string $branch): Response
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->branchOrAbort($branch);
        $this->abortIfReadOnly();

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Debe seleccionar un archivo CSV.',
            ]);
        }

        $preview = $this->imports->preview((int) $branchModel->id, $file);

        return Inertia::render('Inventory/Import', $this->pageProps($request, $branchModel, $preview));
    }

    public function commit(InitialStockImportRequest $request, string $branch): RedirectResponse
    {
        $this->abortIfModuleDisabled();
        $branchModel = $this->branchOrAbort($branch);
        $user = $this->actor($request);
        $this->abortIfReadOnly();

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Debe seleccionar un archivo CSV.',
            ]);
        }

        try {
            $result = $this->imports->commit(
                (int) $branchModel->id,
                $file,
                (int) $user->id,
            );
        } catch (StockWriteException $exception) {
            throw ValidationException::withMessages([
                'csv' => 'No se importó nada. '.$exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('inventory.stock.index', ['branch' => (int) $branchModel->id])
            ->with(
                'success',
                "Se importaron {$result['count']} SKU con un total de {$result['total']} unidades como carga inicial.",
            );
    }

    /**
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, Branch $branch, ?array $preview): array
    {
        $user = $request->user();

        return [
            'branch' => [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
            ],
            'preview' => $preview,
            'can_adjust' => $user instanceof User
                && CheckPermission::allows($user, 'inventory.adjust', $branch->id),
            'can_view_inventory' => $user instanceof User
                && CheckPermission::allows($user, 'inventory.view', $branch->id),
            'read_only' => $this->guard->isReadOnly(),
            'limits' => [
                'max_rows' => InitialStockCsvParser::MAX_DATA_ROWS,
                'max_bytes' => InitialStockCsvParser::MAX_FILE_BYTES,
                'max_sku_length' => InitialStockCsvParser::MAX_SKU_LENGTH,
                'max_quantity_length' => InitialStockCsvParser::MAX_QUANTITY_LENGTH,
            ],
            'template_url' => route('inventory.import.template', ['branch' => (int) $branch->id]),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'No tiene acceso al inventario.');
        }

        return $user;
    }

    private function abortIfModuleDisabled(): void
    {
        if (! $this->modules->isEnabled()) {
            abort(403, 'El módulo de inventario no está habilitado.');
        }
    }

    private function abortIfReadOnly(): void
    {
        if ($this->guard->isReadOnly()) {
            throw ValidationException::withMessages([
                'read_only' => 'La licencia está suspendida fuera del período de gracia; el inventario está en modo solo lectura.',
            ]);
        }
    }

    private function branchOrAbort(string $identifier): Branch
    {
        $branch = $this->resolveRouteBranch($identifier);

        if ($branch === null) {
            abort(403, 'Sucursal no encontrada o inactiva.');
        }

        return $branch;
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
}
