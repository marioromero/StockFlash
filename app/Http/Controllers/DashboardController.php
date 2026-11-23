<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CheckPermission;
use App\Models\User;
use App\Support\Customers\CustomerModuleGate;
use App\Support\License\LicenseStateResolver;
use App\Support\Sellers\SellerAccessService;
use App\Support\Sellers\SellerModuleGate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard autenticado (CORE-08).
 *
 * Entrega solo el aviso sanitizado de estado de licencia; no es una pantalla
 * de administración y no expone secretos ni campos crudos de `license_states`.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly LicenseStateResolver $licenseStateResolver,
        private readonly CustomerModuleGate $customerModules,
        private readonly SellerModuleGate $sellerModules,
        private readonly SellerAccessService $sellerAccess,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Dashboard', [
            'license_notice' => $this->licenseStateResolver->dashboardNotice(),
            'can_view_inventory' => $user instanceof User && $this->canViewInventory($user),
            // Las rutas de clientes son globales (sin `{branch}`), por lo que
            // CheckPermission evalúa el mismo ámbito que usará la ruta: el rol
            // global o la sucursal por defecto del usuario.
            'can_view_customers' => $user instanceof User
                && $this->customerModules->isEnabled()
                && CheckPermission::allows($user, 'customers.view'),
            // Vendedores usa rutas globales y permiso en cualquier sucursal
            // asignada; el enlace solo se muestra cuando el módulo está
            // habilitado y el usuario puede administrar al menos una sucursal.
            'can_view_sellers' => $user instanceof User
                && $this->sellerModules->isEnabled()
                && $this->sellerAccess->canManageAnyBranch($user),
        ]);
    }

    /**
     * El enlace se muestra solo si el usuario puede ejercer `inventory.view`
     * en al menos una sucursal activa. La autorización real sigue siendo
     * server-side; este dato solo oculta el acceso en la interfaz.
     */
    private function canViewInventory(User $user): bool
    {
        if ($user->isGlobal()) {
            return CheckPermission::allows($user, 'inventory.view');
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

        foreach ($branchIds as $branchId) {
            if (CheckPermission::allows($user, 'inventory.view', $branchId)) {
                return true;
            }
        }

        return false;
    }
}
