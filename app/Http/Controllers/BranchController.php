<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración global de sucursales (BRANCH-001 · CORE-05).
 *
 * Todas las escrituras ocurren en una transacción junto con su evento de
 * auditoría. Si la auditoría falla, la operación completa se revierte.
 */
class BranchController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $branches = Branch::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Branch $branch): array => $this->serialize($branch))
            ->values();

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'active_count' => Branch::query()->where('is_active', 1)->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Branches/Form', [
            'branch' => null,
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $data = $this->attributesForCreate($request);

        try {
            DB::transaction(function () use ($data): void {
                $this->assertWithinBranchLimit((bool) $data['is_active']);

                $branch = Branch::create($data);

                $this->auditLogger->log(
                    'branch.created',
                    $branch,
                    null,
                    $this->auditValues($branch),
                );
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueCodeViolation($exception)) {
                throw ValidationException::withMessages([
                    'code' => 'Ya existe una sucursal con ese código.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('branches.index')
            ->with('success', 'Sucursal creada correctamente.');
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('Branches/Form', [
            'branch' => $this->serialize($branch),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $this->normalizeSettings($request->validated());

        // El código es inmutable (prefijo de numeración RN-74). El FormRequest
        // rechaza un intento de cambio; aquí además se ignora defensivamente.
        // `is_active` solo cambia por `activate`/`deactivate`, que verifican el
        // límite de plan y auditan el evento correcto; se ignora aquí también.
        unset($data['code'], $data['is_active']);

        DB::transaction(function () use ($branch, $data): void {
            $oldValues = $this->auditValues($branch);

            $branch->fill($data);
            $branch->save();

            $this->auditLogger->log(
                'branch.updated',
                $branch,
                $oldValues,
                $this->auditValues($branch),
            );
        });

        return redirect()
            ->route('branches.index')
            ->with('success', 'Sucursal actualizada correctamente.');
    }

    public function deactivate(Branch $branch): RedirectResponse
    {
        if (! $branch->is_active) {
            return redirect()
                ->route('branches.index')
                ->with('warning', 'La sucursal ya estaba desactivada.');
        }

        DB::transaction(function () use ($branch): void {
            $branch->is_active = false;
            $branch->save();

            $this->auditLogger->log(
                'branch.deactivated',
                $branch,
                ['is_active' => true],
                ['is_active' => false],
            );
        });

        return redirect()
            ->route('branches.index')
            ->with('success', 'Sucursal desactivada correctamente.');
    }

    public function activate(Branch $branch): RedirectResponse
    {
        if ($branch->is_active) {
            return redirect()
                ->route('branches.index')
                ->with('warning', 'La sucursal ya estaba activa.');
        }

        DB::transaction(function () use ($branch): void {
            $this->assertWithinBranchLimit(true);

            $branch->is_active = true;
            $branch->save();

            $this->auditLogger->log(
                'branch.activated',
                $branch,
                ['is_active' => false],
                ['is_active' => true],
            );
        });

        return redirect()
            ->route('branches.index')
            ->with('success', 'Sucursal activada correctamente.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        DB::transaction(function () use ($branch): void {
            $oldValues = $this->auditValues($branch);

            $branch->delete();

            $this->auditLogger->log(
                'branch.deleted',
                $branch,
                $oldValues,
                ['deleted_at' => optional($branch->deleted_at)->toDateTimeString()],
            );
        });

        return redirect()
            ->route('branches.index')
            ->with('success', 'Sucursal eliminada lógicamente. Su código sigue reservado.');
    }

    /**
     * Verifica el límite mínimo de sucursales activas de `license_states`.
     * Debe ejecutarse dentro de una transacción; bloquea la fila de licencia.
     */
    private function assertWithinBranchLimit(bool $newBranchWillBeActive): void
    {
        if (! $newBranchWillBeActive) {
            return;
        }

        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($license === null) {
            return;
        }

        $maxBranches = (int) $license->max_branches;

        if ($maxBranches <= 0) {
            return;
        }

        $activeBranches = Branch::query()->where('is_active', 1)->count();

        if ($activeBranches >= $maxBranches) {
            throw ValidationException::withMessages([
                'max_branches' => "El plan permite un máximo de {$maxBranches} sucursales activas.",
            ]);
        }
    }

    private function attributesForCreate(BranchRequest $request): array
    {
        $data = $this->normalizeSettings($request->validated());

        $data['is_active'] = $request->boolean('is_active', true);
        $data['requires_cash_control'] = $request->boolean('requires_cash_control', true);
        $data['sales_flow_mode'] = $data['sales_flow_mode'] ?? null;
        $data['seller_code_policy'] = $data['seller_code_policy'] ?? null;
        $data['allow_negative_stock'] = $data['allow_negative_stock'] ?? null;
        $data['settings'] = $data['settings'] ?? null;

        return $data;
    }

    /**
     * Convierte el JSON textual de `settings` al valor que espera el cast
     * `array` del modelo, preservando `{}` como objeto vacío.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeSettings(array $data): array
    {
        if (! array_key_exists('settings', $data)) {
            return $data;
        }

        $settings = $data['settings'];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);

            if (is_array($decoded)) {
                $data['settings'] = $decoded === [] ? (object) [] : $decoded;
            }

            return $data;
        }

        // Un cliente JSON puede enviar `settings: []` como arreglo real; se
        // almacena como objeto vacío `{}` y no como lista.
        if (is_array($settings) && $settings === []) {
            $data['settings'] = (object) [];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Branch $branch): array
    {
        return $branch->only([
            'code', 'name', 'type', 'address', 'commune', 'city', 'region',
            'phone', 'is_active', 'requires_cash_control', 'sales_flow_mode',
            'seller_code_policy', 'allow_negative_stock', 'settings',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
            'type' => $branch->type,
            'address' => $branch->address,
            'commune' => $branch->commune,
            'city' => $branch->city,
            'region' => $branch->region,
            'phone' => $branch->phone,
            'is_active' => (bool) $branch->is_active,
            'requires_cash_control' => (bool) $branch->requires_cash_control,
            'sales_flow_mode' => $branch->sales_flow_mode,
            'seller_code_policy' => $branch->seller_code_policy,
            'allow_negative_stock' => $branch->allow_negative_stock === null
                ? null
                : (bool) $branch->allow_negative_stock,
            'settings' => $branch->settings,
        ];
    }

    private function isUniqueCodeViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if (! in_array($sqlState, ['23000', '23505'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'branches_code_unique')
            || str_contains($message, 'branches.code')
            || ($driverCode === 1062 && str_contains($message, 'branches'));
    }
}
