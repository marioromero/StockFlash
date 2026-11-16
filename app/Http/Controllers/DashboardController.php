<?php

namespace App\Http\Controllers;

use App\Support\License\LicenseStateResolver;
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
    public function __construct(private readonly LicenseStateResolver $licenseStateResolver) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'license_notice' => $this->licenseStateResolver->dashboardNotice(),
        ]);
    }
}
