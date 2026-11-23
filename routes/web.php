<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'version' => 'Laravel ' . app()->version(),
    ]);
})->name('home');

// --- Manual HTML de usuario (DOCS-001) ---
// El contenido es autoría exclusiva de ChatGPT; OpenCode solo sirve y verifica.
Route::get('/docs/', fn () => response(
    file_get_contents(public_path('docs/index.html')),
    200,
    ['Content-Type' => 'text/html; charset=UTF-8']
))->name('docs.index');

// --- Public project roadmap (owner decision 2026-11-20) ---
// Public, read-only HTML maintained exclusively by ChatGPT; no authenticated
// data, secrets, personal information or application state is served.
Route::get('/roadmap', fn () => response(
    file_get_contents(resource_path('roadmap/index.html')),
    200,
    [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
    ]
))->name('roadmap.index');

// --- Instalador técnico (INSTALL-001 · CORE-17) ---
// Fail-closed: cerrado por defecto en `config/install.php`; solo se abre con
// un token de arranque presentado fuera de la app pública. Ningún GET migra.
Route::middleware(\App\Http\Middleware\EnsureInstallerEnabled::class)->group(function (): void {
    Route::get('/install', [\App\Http\Controllers\InstallController::class, 'show'])->name('install.show');
    Route::post('/install/bootstrap', [\App\Http\Controllers\InstallController::class, 'bootstrap'])->name('install.bootstrap');
    Route::post('/install/preflight', [\App\Http\Controllers\InstallController::class, 'preflightStep'])->name('install.preflight');
    Route::post('/install/connection', [\App\Http\Controllers\InstallController::class, 'connectionStep'])->name('install.connection');
    Route::post('/install/run', [\App\Http\Controllers\InstallController::class, 'run'])->name('install.run');
});

// --- Asistente de primer inicio (SETUP-001 · CORE-02) ---
// Público por diseño: el estado de instalación y el token de flujo lo
// protegen. Las rutas privadas quedan bloqueadas por EnsureSetupCompleted.
Route::get('/setup', [\App\Http\Controllers\SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [\App\Http\Controllers\SetupController::class, 'store'])->name('setup.store');

// --- Autenticación (AUTH-001) ---

Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
// Nombre propio para poder eximir exactamente el POST de ingreso del guard de
// instalación sin abrir un prefijo de rutas.
Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [\App\Http\Controllers\Auth\LogoutController::class, 'destroy'])->name('logout');

// Cambio de contraseña (autenticados; también verifica el fingerprint SEC-05)
Route::middleware(['auth', 'verify.session'])->group(function () {
    Route::get('/password/change', [\App\Http\Controllers\Auth\PasswordController::class, 'show'])->name('password.change');
    Route::put('/password/change', [\App\Http\Controllers\Auth\PasswordController::class, 'update'])->name('password.update');
});

// --- Dashboard protegido (AUTH-001) ---
Route::middleware(['auth', 'password.change', 'verify.session'])->group(function () {
    // El aviso de estado de licencia se calcula solo lectura desde
    // `license_states`; no expone secretos ni administra la licencia (CORE-08).
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->name('dashboard');

    // Rutas de prueba de autorización (AUTH-001). No son CRUD: devuelven
    // un payload simple para verificar que el middleware de permisos y de
    // acceso a sucursal se ejecuta correctamente.
    Route::get('/usuarios', function () {
        return response()->json(['ok' => true, 'seccion' => 'usuarios']);
    })->middleware('can.permission:users.manage')->name('usuarios.index');

    Route::get('/dashboard/branch/{branch}', function (string $branch) {
        return response()->json(['ok' => true, 'sucursal' => $branch]);
    })->middleware('branch.access')->name('dashboard.branch');

    // --- Administración global de usuarios (USERS-001 · CORE-03) ---
    // Es administración global: NO usa `branch.access`.
    Route::middleware('can.permission:users.manage')->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])
            ->name('users.index');
        Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])
            ->name('users.create');
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])
            ->name('users.store');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])
            ->name('users.edit');
        Route::match(['put', 'patch'], '/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])
            ->name('users.update');
        Route::match(['post', 'patch'], '/users/{user}/activate', [\App\Http\Controllers\UserController::class, 'activate'])
            ->name('users.activate');
        Route::match(['post', 'patch'], '/users/{user}/deactivate', [\App\Http\Controllers\UserController::class, 'deactivate'])
            ->name('users.deactivate');
        Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])
            ->name('users.destroy');
    });

    // --- Ajustes del comprobante interno (CFG-001 · CORE-09) ---
    // Solo las tres claves de Parte 2 §3.1; autenticación y permiso real en
    // servidor. No usa `branch.access`: es configuración global.
    Route::middleware('can.permission:settings.manage')->group(function () {
        Route::get('/settings/receipt', [\App\Http\Controllers\Settings\ReceiptSettingsController::class, 'edit'])
            ->name('settings.receipt.edit');
        Route::put('/settings/receipt', [\App\Http\Controllers\Settings\ReceiptSettingsController::class, 'update'])
            ->name('settings.receipt.update');
    });

    // --- Datos del comercio y contacto responsable (CFG-002 · CORE-09) ---
    // Configuración global de las nueve claves de Parte 1 §8.1 / Parte 2 §3.1.
    // No usa `branch.access`: no es configuración por sucursal. PATCH queda
    // deshabilitado a propósito para evitar actualizaciones parciales.
    Route::middleware('can.permission:settings.manage')->group(function () {
        Route::get('/settings/business', [\App\Http\Controllers\Settings\BusinessSettingsController::class, 'edit'])
            ->name('settings.business.edit');
        Route::put('/settings/business', [\App\Http\Controllers\Settings\BusinessSettingsController::class, 'update'])
            ->name('settings.business.update');
    });

    // --- Preferencias generales del comercio (CFG-003 · CORE-09) ---
    // Configuración global de las siete claves de Parte 2 §3.1. No usa
    // `branch.access`. PATCH queda deshabilitado a propósito: PUT es completo.
    Route::middleware('can.permission:settings.manage')->group(function () {
        Route::get('/settings/general', [\App\Http\Controllers\Settings\GeneralSettingsController::class, 'edit'])
            ->name('settings.general.edit');
        Route::put('/settings/general', [\App\Http\Controllers\Settings\GeneralSettingsController::class, 'update'])
            ->name('settings.general.update');
    });

    // --- Maestros del catálogo P0 (CATMAST-001 · CAT-02/CAT-05) ---
    // Catálogo común a la instalación: NO usa `branch.access`. La lectura
    // exige `catalog.view`; cada escritura exige `catalog.manage` en servidor.
    Route::middleware('can.permission:catalog.view')->group(function () {
        Route::get('/catalog/masters', [\App\Http\Controllers\CatalogMastersController::class, 'index'])
            ->name('catalog.masters.index');
    });

    Route::middleware('can.permission:catalog.manage')->group(function () {
        Route::post('/catalog/masters/tax-rates', [\App\Http\Controllers\CatalogMastersController::class, 'storeTaxRate'])
            ->name('catalog.masters.tax-rates.store');
        Route::put('/catalog/masters/tax-rates/{taxRate}', [\App\Http\Controllers\CatalogMastersController::class, 'updateTaxRate'])
            ->name('catalog.masters.tax-rates.update');
        Route::delete('/catalog/masters/tax-rates/{taxRate}', [\App\Http\Controllers\CatalogMastersController::class, 'destroyTaxRate'])
            ->name('catalog.masters.tax-rates.destroy');

        Route::post('/catalog/masters/categories', [\App\Http\Controllers\CatalogMastersController::class, 'storeCategory'])
            ->name('catalog.masters.categories.store');
        Route::put('/catalog/masters/categories/{category}', [\App\Http\Controllers\CatalogMastersController::class, 'updateCategory'])
            ->name('catalog.masters.categories.update');
        Route::delete('/catalog/masters/categories/{category}', [\App\Http\Controllers\CatalogMastersController::class, 'destroyCategory'])
            ->name('catalog.masters.categories.destroy');

        Route::post('/catalog/masters/brands', [\App\Http\Controllers\CatalogMastersController::class, 'storeBrand'])
            ->name('catalog.masters.brands.store');
        Route::put('/catalog/masters/brands/{brand}', [\App\Http\Controllers\CatalogMastersController::class, 'updateBrand'])
            ->name('catalog.masters.brands.update');
        Route::delete('/catalog/masters/brands/{brand}', [\App\Http\Controllers\CatalogMastersController::class, 'destroyBrand'])
            ->name('catalog.masters.brands.destroy');

        Route::post('/catalog/masters/tags', [\App\Http\Controllers\CatalogMastersController::class, 'storeTag'])
            ->name('catalog.masters.tags.store');
        Route::put('/catalog/masters/tags/{tag}', [\App\Http\Controllers\CatalogMastersController::class, 'updateTag'])
            ->name('catalog.masters.tags.update');
        Route::delete('/catalog/masters/tags/{tag}', [\App\Http\Controllers\CatalogMastersController::class, 'destroyTag'])
            ->name('catalog.masters.tags.destroy');

        Route::post('/catalog/masters/attributes', [\App\Http\Controllers\CatalogMastersController::class, 'storeAttribute'])
            ->name('catalog.masters.attributes.store');
        Route::put('/catalog/masters/attributes/{attribute}', [\App\Http\Controllers\CatalogMastersController::class, 'updateAttribute'])
            ->name('catalog.masters.attributes.update');
        Route::delete('/catalog/masters/attributes/{attribute}', [\App\Http\Controllers\CatalogMastersController::class, 'destroyAttribute'])
            ->name('catalog.masters.attributes.destroy');

        Route::post('/catalog/masters/attribute-values', [\App\Http\Controllers\CatalogMastersController::class, 'storeAttributeValue'])
            ->name('catalog.masters.attribute-values.store');
        Route::put('/catalog/masters/attribute-values/{attributeValue}', [\App\Http\Controllers\CatalogMastersController::class, 'updateAttributeValue'])
            ->name('catalog.masters.attribute-values.update');
        Route::delete('/catalog/masters/attribute-values/{attributeValue}', [\App\Http\Controllers\CatalogMastersController::class, 'destroyAttributeValue'])
            ->name('catalog.masters.attribute-values.destroy');
    });

    // --- Productos, variantes y códigos de barras P0 (CATPROD-001 · CAT-01/03/04/05/07/08) ---
    // Catálogo común a la instalación: NO usa `branch.access` ni lee `branch_id`
    // desde query/body. Lectura con `catalog.view`; mutaciones con
    // `catalog.manage`. Los costos se filtran en servidor con `catalog.view_cost`.
    Route::middleware('can.permission:catalog.view')->group(function () {
        Route::get('/catalog/products', [\App\Http\Controllers\Catalog\ProductController::class, 'index'])
            ->name('catalog.products.index');
        Route::get('/catalog/products/{product}', [\App\Http\Controllers\Catalog\ProductController::class, 'show'])
            ->name('catalog.products.show');
    });

    Route::middleware('can.permission:catalog.manage')->group(function () {
        Route::post('/catalog/products', [\App\Http\Controllers\Catalog\ProductController::class, 'store'])
            ->name('catalog.products.store');
        Route::put('/catalog/products/{product}', [\App\Http\Controllers\Catalog\ProductController::class, 'update'])
            ->name('catalog.products.update');
        Route::delete('/catalog/products/{product}', [\App\Http\Controllers\Catalog\ProductController::class, 'destroy'])
            ->name('catalog.products.destroy');

        Route::post('/catalog/products/{product}/variants', [\App\Http\Controllers\Catalog\ProductVariantController::class, 'store'])
            ->name('catalog.products.variants.store');
        Route::put('/catalog/products/{product}/variants/{variant}', [\App\Http\Controllers\Catalog\ProductVariantController::class, 'update'])
            ->name('catalog.products.variants.update');
        Route::delete('/catalog/products/{product}/variants/{variant}', [\App\Http\Controllers\Catalog\ProductVariantController::class, 'destroy'])
            ->name('catalog.products.variants.destroy');

        Route::post('/catalog/products/{product}/variants/{variant}/barcodes', [\App\Http\Controllers\Catalog\ProductBarcodeController::class, 'store'])
            ->name('catalog.products.barcodes.store');
        Route::put('/catalog/products/{product}/variants/{variant}/barcodes/{barcode}', [\App\Http\Controllers\Catalog\ProductBarcodeController::class, 'update'])
            ->name('catalog.products.barcodes.update');
        Route::delete('/catalog/products/{product}/variants/{variant}/barcodes/{barcode}', [\App\Http\Controllers\Catalog\ProductBarcodeController::class, 'destroy'])
            ->name('catalog.products.barcodes.destroy');
    });

    // --- Inventario físico por sucursal (INVCORE-001 · INV-01/02/03) ---
    // Solo lectura en esta ola: no se exponen rutas de ajuste, venta ni reserva.
    // El ámbito de sucursal proviene solo de `{branch}` y `branch.access`;
    // `branch_id` de query/body nunca amplía la autorización.
    Route::get('/inventory', [\App\Http\Controllers\Inventory\StockController::class, 'entry'])
        ->middleware('can.permission:inventory.view')
        ->name('inventory.entry');

    Route::get('/inventory/stock/{branch}', [\App\Http\Controllers\Inventory\StockController::class, 'index'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.stock.index');

    // INVCSV-001 · carga inicial de existencias desde CSV. La sucursal sale
    // solo de `{branch}` y `branch.access`; el actor, el tipo, el origen y los
    // saldos se resuelven en servidor. Lectura/plantilla con `inventory.view`;
    // vista previa y commit con `inventory.adjust`.
    Route::get('/inventory/stock/{branch}/import', [\App\Http\Controllers\Inventory\InitialStockImportController::class, 'index'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.import.index');

    Route::get('/inventory/stock/{branch}/import/template', [\App\Http\Controllers\Inventory\InitialStockImportController::class, 'template'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.import.template');

    Route::post('/inventory/stock/{branch}/import/preview', [\App\Http\Controllers\Inventory\InitialStockImportController::class, 'preview'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.import.preview');

    Route::post('/inventory/stock/{branch}/import/commit', [\App\Http\Controllers\Inventory\InitialStockImportController::class, 'commit'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.import.commit');

    // INVKARDEX-001 · reporte Kardex de solo lectura. La sucursal sale de
    // `{branch}` y `branch.access`; no se acepta `branch_id` de query.
    Route::get('/inventory/stock/{branch}/kardex', [\App\Http\Controllers\Inventory\KardexController::class, 'index'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.kardex.index');

    // INVREORDER-001 · reposición y panel de stock crítico. Lectura con
    // `inventory.view`; la escritura de umbrales vive en la ruta PUT con
    // `inventory.adjust`. La sucursal sale de `{branch}` y `branch.access`.
    Route::get('/inventory/stock/{branch}/reorder', [\App\Http\Controllers\Inventory\ReorderController::class, 'index'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.reorder.index');

    // INVWRITE-001 · escritura manual: solo carga inicial única y ajuste con
    // motivo. La sucursal sale de `{branch}` y `branch.access`; el resto del
    // contexto se resuelve en servidor.
    Route::post('/inventory/stock/{branch}/initial', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'storeInitial'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.stock.initial');

    Route::post('/inventory/stock/{branch}/adjustment', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'storeAdjustment'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.stock.adjust');

    // INVREORDER-001 · único endpoint de escritura de settings: solo
    // `reorder_point`, `reorder_qty` y `max_stock`; sin balance, costo,
    // safety_stock, location_label ni sucursal/usuario desde el cliente.
    Route::put('/inventory/stock/{branch}/reorder/{variant}', [\App\Http\Controllers\Inventory\ReorderController::class, 'update'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.reorder.update');

    // INVTRANSFER-001 — traspasos entre sucursales. Lectura con
    // `inventory.view`; crear/solicitar/enviar/cancelar/recibir con
    // `inventory.adjust`. La sucursal sale de `{branch}` y `branch.access`.
    Route::get('/inventory/stock/{branch}/transfers', [\App\Http\Controllers\Inventory\StockTransferController::class, 'index'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.transfers.index');

    Route::post('/inventory/stock/{branch}/transfers', [\App\Http\Controllers\Inventory\StockTransferController::class, 'store'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.transfers.store');

    Route::get('/inventory/stock/{branch}/transfers/{transfer}', [\App\Http\Controllers\Inventory\StockTransferController::class, 'show'])
        ->middleware(['can.permission:inventory.view', 'branch.access'])
        ->name('inventory.transfers.show');

    Route::post('/inventory/stock/{branch}/transfers/{transfer}/request', [\App\Http\Controllers\Inventory\StockTransferController::class, 'request'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.transfers.request');

    Route::post('/inventory/stock/{branch}/transfers/{transfer}/dispatch', [\App\Http\Controllers\Inventory\StockTransferController::class, 'dispatch'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.transfers.dispatch');

    Route::post('/inventory/stock/{branch}/transfers/{transfer}/cancel', [\App\Http\Controllers\Inventory\StockTransferController::class, 'cancel'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.transfers.cancel');

    Route::post('/inventory/stock/{branch}/transfers/{transfer}/receive', [\App\Http\Controllers\Inventory\StockTransferController::class, 'receive'])
        ->middleware(['can.permission:inventory.adjust', 'branch.access'])
        ->name('inventory.transfers.receive');

    // --- Clientes y grupos (CUSCORE-001 · CUS-01/02/03 · RN-58/61/64) ---
    // Catálogo común a la instalación: NO usa `branch.access`. La lectura exige
    // `customers.view`; cada mutación exige `customers.manage`; el revelado de
    // datos personales exige `customers.view_sensitive` y se audita. El gate de
    // módulo es fail-closed y cubre todas estas rutas.
    Route::middleware('customers.module')->group(function (): void {
        Route::middleware('can.permission:customers.view')->group(function (): void {
            Route::get('/customers', [\App\Http\Controllers\Customers\CustomerController::class, 'index'])
                ->name('customers.index');
            // La búsqueda por RUT usa POST y solo persiste el índice ciego en
            // sesión; el RUT nunca viaja por query string ni en props Inertia.
            Route::post('/customers/rut-search', [\App\Http\Controllers\Customers\CustomerController::class, 'searchByRut'])
                ->name('customers.rut-search');
            Route::post('/customers/rut-search/clear', [\App\Http\Controllers\Customers\CustomerController::class, 'clearRutSearch'])
                ->name('customers.clear-rut');
            Route::get('/customers/groups', [\App\Http\Controllers\Customers\CustomerGroupController::class, 'index'])
                ->name('customers.groups.index');
            Route::get('/customers/groups/{group}', [\App\Http\Controllers\Customers\CustomerGroupController::class, 'show'])
                ->whereNumber('group')
                ->name('customers.groups.show');
            Route::get('/customers/{customer}', [\App\Http\Controllers\Customers\CustomerController::class, 'show'])
                ->whereNumber('customer')
                ->name('customers.show');
        });

        Route::middleware('can.permission:customers.manage')->group(function (): void {
            Route::post('/customers', [\App\Http\Controllers\Customers\CustomerController::class, 'store'])
                ->name('customers.store');
            Route::patch('/customers/{customer}', [\App\Http\Controllers\Customers\CustomerController::class, 'update'])
                ->whereNumber('customer')
                ->name('customers.update');
            Route::post('/customers/{customer}/activate', [\App\Http\Controllers\Customers\CustomerController::class, 'activate'])
                ->whereNumber('customer')
                ->name('customers.activate');
            Route::post('/customers/{customer}/deactivate', [\App\Http\Controllers\Customers\CustomerController::class, 'deactivate'])
                ->whereNumber('customer')
                ->name('customers.deactivate');

            Route::post('/customers/groups', [\App\Http\Controllers\Customers\CustomerGroupController::class, 'store'])
                ->name('customers.groups.store');
            Route::patch('/customers/groups/{group}', [\App\Http\Controllers\Customers\CustomerGroupController::class, 'update'])
                ->whereNumber('group')
                ->name('customers.groups.update');
            Route::post('/customers/groups/{group}/activate', [\App\Http\Controllers\Customers\CustomerGroupController::class, 'activate'])
                ->whereNumber('group')
                ->name('customers.groups.activate');
            Route::post('/customers/groups/{group}/deactivate', [\App\Http\Controllers\Customers\CustomerGroupController::class, 'deactivate'])
                ->whereNumber('group')
                ->name('customers.groups.deactivate');
        });

        Route::get('/customers/{customer}/reveal', [\App\Http\Controllers\Customers\CustomerController::class, 'reveal'])
            ->whereNumber('customer')
            ->middleware('can.permission:customers.view_sensitive')
            ->name('customers.reveal');
    });

    // --- Vendedores (SELLERS-001 · SEL-01 · RN-13/RN-14) ---
    // Rutas globales sin `{branch}`: el permiso `sellers.manage` se evalúa en
    // cualquier sucursal asignada mediante `sellers.manage`; el alcance fino
    // de sucursales se filtra en servidor. El módulo es fail-closed: fila
    // explícita `sellers` o, en su defecto, `pos` habilitado.
    Route::middleware(['sellers.module', 'sellers.manage'])->group(function (): void {
        Route::get('/sellers', [\App\Http\Controllers\Sellers\SellerController::class, 'index'])
            ->name('sellers.index');
        Route::post('/sellers', [\App\Http\Controllers\Sellers\SellerController::class, 'store'])
            ->name('sellers.store');
        Route::get('/sellers/{seller}', [\App\Http\Controllers\Sellers\SellerController::class, 'show'])
            ->whereNumber('seller')
            ->name('sellers.show');
        Route::patch('/sellers/{seller}', [\App\Http\Controllers\Sellers\SellerController::class, 'update'])
            ->whereNumber('seller')
            ->name('sellers.update');
        Route::post('/sellers/{seller}/activate', [\App\Http\Controllers\Sellers\SellerController::class, 'activate'])
            ->whereNumber('seller')
            ->name('sellers.activate');
        Route::post('/sellers/{seller}/deactivate', [\App\Http\Controllers\Sellers\SellerController::class, 'deactivate'])
            ->whereNumber('seller')
            ->name('sellers.deactivate');
    });

    // --- Administración global de sucursales (BRANCH-001 · CORE-05) ---
    // Es administración global: NO usa `branch.access`.
    Route::middleware('can.permission:settings.manage')->group(function () {
        Route::get('/branches', [\App\Http\Controllers\BranchController::class, 'index'])
            ->name('branches.index');
        Route::get('/branches/create', [\App\Http\Controllers\BranchController::class, 'create'])
            ->name('branches.create');
        Route::post('/branches', [\App\Http\Controllers\BranchController::class, 'store'])
            ->name('branches.store');
        Route::get('/branches/{branch}/edit', [\App\Http\Controllers\BranchController::class, 'edit'])
            ->name('branches.edit');
        Route::match(['put', 'patch'], '/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'update'])
            ->name('branches.update');
        Route::match(['post', 'patch'], '/branches/{branch}/deactivate', [\App\Http\Controllers\BranchController::class, 'deactivate'])
            ->name('branches.deactivate');
        Route::match(['post', 'patch'], '/branches/{branch}/activate', [\App\Http\Controllers\BranchController::class, 'activate'])
            ->name('branches.activate');
        Route::delete('/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'destroy'])
            ->name('branches.destroy');
    });
});
