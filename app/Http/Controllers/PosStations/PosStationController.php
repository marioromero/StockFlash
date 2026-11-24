<?php

namespace App\Http\Controllers\PosStations;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosStations\PosStationRequest;
use App\Models\Branch;
use App\Models\PosStation;
use App\Models\User;
use App\Support\PosStations\PosStationAccessService;
use App\Support\PosStations\PosStationResolver;
use App\Support\PosStations\PosStationService;
use App\Support\PosStations\PosStationWriteGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración de estaciones POS (Parte 1 §8.6 POS-02 / §8.7 CSH-02;
 * Parte 2 §4.13).
 *
 * Rutas globales sin `{branch}`: el alcance se calcula en servidor desde
 * `settings.manage` por rol global o por sucursal. Los IDs de sucursal de
 * query/body nunca elevan permisos. El token de dispositivo solo viaja en una
 * cookie HttpOnly generada por una acción POST explícita; nunca se muestra,
 * serializa, registra ni devuelve en props.
 */
class PosStationController extends Controller
{
    private const COOKIE_MINUTES = 525600;

    public function __construct(
        private readonly PosStationAccessService $access,
        private readonly PosStationWriteGuard $guard,
        private readonly PosStationService $service,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowed = $this->access->allowedBranchIds($user);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $branchId = $this->optionalBranchFilter($request, $allowed);
        $perPage = $this->perPage($request);

        $query = PosStation::query()->with([
            'branch' => fn ($relation) => $relation->select('id', 'code', 'name', 'is_active'),
        ]);

        if ($search !== '') {
            $term = '%'.$this->escapeLike(mb_strtolower($search)).'%';

            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]);
            });
        }

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $this->access->scopeVisibleStations($query, $user);

        $stations = $query
            ->orderBy('code')
            ->orderBy('id')
            ->paginate($perPage);

        return Inertia::render('PosStations/Index', [
            'stations' => [
                'data' => collect($stations->items())
                    ->map(fn (PosStation $station): array => $this->listRow($station))
                    ->values()
                    ->all(),
                'links' => $stations->linkCollection()->values()->all(),
                'current_page' => $stations->currentPage(),
                'last_page' => $stations->lastPage(),
                'from' => $stations->firstItem(),
                'to' => $stations->lastItem(),
                'total' => $stations->total(),
                'per_page' => $stations->perPage(),
            ],
            'filters' => [
                'q' => $search,
                'branch_id' => $branchId,
            ],
            'branch_options' => $this->access->branchOptions($user),
            'printer_profiles' => PosStation::PRINTER_PROFILES,
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function show(Request $request, string $station): Response
    {
        $model = $this->resolveVisibleStation($request->user(), $station);

        $model->load([
            'branch' => fn ($relation) => $relation->select('id', 'code', 'name', 'is_active'),
        ]);

        return Inertia::render('PosStations/Show', [
            'station' => $this->detail($model),
            'branch_options' => $this->access->branchOptions($request->user()),
            'printer_profiles' => PosStation::PRINTER_PROFILES,
            'read_only' => $this->guard->isReadOnly(),
        ]);
    }

    public function store(PosStationRequest $request): RedirectResponse
    {
        $station = $this->service->create($request->user(), $request->validated());

        return redirect()
            ->route('settings.pos-stations.show', ['station' => $station->id])
            ->with('success', 'Estación POS registrada.');
    }

    public function update(PosStationRequest $request, string $station): RedirectResponse
    {
        $model = $this->resolveVisibleStation($request->user(), $station);

        $this->service->update($request->user(), $model, $request->validated());

        return redirect()
            ->route('settings.pos-stations.show', ['station' => $model->id])
            ->with('success', 'Estación POS actualizada.');
    }

    public function activate(Request $request, string $station): RedirectResponse
    {
        $model = $this->resolveVisibleStation($request->user(), $station);

        $this->service->changeStatus($request->user(), $model, true);

        return redirect()
            ->route('settings.pos-stations.show', ['station' => $model->id])
            ->with('success', 'Estación POS activada.');
    }

    public function deactivate(Request $request, string $station): RedirectResponse
    {
        $model = $this->resolveVisibleStation($request->user(), $station);

        $this->service->changeStatus($request->user(), $model, false);

        return redirect()
            ->route('settings.pos-stations.show', ['station' => $model->id])
            ->with('success', 'Estación POS desactivada.');
    }

    public function bind(Request $request, string $station): RedirectResponse
    {
        $model = $this->resolveVisibleStation($request->user(), $station);

        $result = $this->service->bind($request->user(), $model);

        return redirect()
            ->route('settings.pos-stations.show', ['station' => $model->id])
            ->with('success', 'Navegador vinculado a la estación.')
            ->withCookie(cookie(
                name: PosStationResolver::COOKIE_NAME,
                value: (string) $result['token'],
                minutes: self::COOKIE_MINUTES,
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'strict',
            ));
    }

    public function revoke(Request $request, string $station): RedirectResponse
    {
        $model = $this->resolveVisibleStation($request->user(), $station);

        $this->service->revoke($request->user(), $model);

        return redirect()
            ->route('settings.pos-stations.show', ['station' => $model->id])
            ->withoutCookie(PosStationResolver::COOKIE_NAME, '/')
            ->with('success', 'Dispositivo revocado; el navegador deberá vincularse de nuevo.');
    }

    private function resolveVisibleStation(User $user, string $identifier): PosStation
    {
        if (! ctype_digit($identifier)) {
            abort(404, 'Estación POS no encontrada.');
        }

        $station = PosStation::query()->findOrFail((int) $identifier);

        if (! $this->access->canAccess($station, $user)) {
            abort(404, 'Estación POS no encontrada.');
        }

        return $station;
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
    private function listRow(PosStation $station): array
    {
        return [
            'id' => (int) $station->id,
            'code' => (string) $station->code,
            'name' => (string) $station->name,
            'printer_profile' => $station->printer_profile,
            'is_active' => (bool) $station->is_active,
            'has_device' => $station->hasBoundDevice(),
            'branch' => $station->branch === null ? null : [
                'id' => (int) $station->branch->id,
                'code' => (string) $station->branch->code,
                'name' => (string) $station->branch->name,
                'is_active' => (bool) $station->branch->is_active,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PosStation $station): array
    {
        return [
            'id' => (int) $station->id,
            'code' => (string) $station->code,
            'name' => (string) $station->name,
            'printer_profile' => $station->printer_profile,
            'is_active' => (bool) $station->is_active,
            'has_device' => $station->hasBoundDevice(),
            'last_seen_at' => $station->last_seen_at === null ? null : $station->last_seen_at->toIso8601String(),
            'branch' => $station->branch === null ? null : [
                'id' => (int) $station->branch->id,
                'code' => (string) $station->branch->code,
                'name' => (string) $station->branch->name,
                'is_active' => (bool) $station->branch->is_active,
            ],
        ];
    }
}
