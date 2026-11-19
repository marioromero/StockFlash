<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link :href="`/inventory/stock/${branch.id}`" class="sf-button sf-button-secondary">
                    Volver al inventario
                </Link>
                <Link :href="`/inventory/stock/${branch.id}/kardex`" class="sf-button sf-button-secondary">
                    Ver Kardex
                </Link>
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">Reposición y stock crítico</h1>
                    <p class="sf-subtitle">
                        Configure el punto de reposición, la cantidad sugerida y el máximo por variante en esta
                        sucursal. El panel destaca las variantes cuyo punto configurado es mayor que cero y cuyo
                        disponible es igual o inferior a ese punto. No modifica existencias ni movimientos.
                    </p>
                </div>
                <div class="sf-titlebar-side">
                    <p class="sf-branch" aria-live="polite">
                        Sucursal: <strong>{{ branch.name }}</strong>
                        <span class="sf-text-muted">({{ branch.code }})</span>
                    </p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: el panel puede consultarse, pero no se guardan cambios de
                configuración hasta regularizar la licencia.
            </div>
            <div v-if="!canViewInventory" class="sf-message sf-info" role="note">
                Su usuario no tiene permiso para consultar inventario en esta sucursal. La autorización se
                verifica siempre en el servidor.
            </div>
            <div v-else-if="!canAdjust" class="sf-message sf-info" role="note">
                Su usuario puede consultar la reposición, pero no configurar umbrales en esta sucursal.
            </div>

            <section class="sf-card" aria-labelledby="reorder-summary-title">
                <h2 id="reorder-summary-title" class="sf-section-title">Resumen de la sucursal</h2>
                <dl class="sf-summary-grid">
                    <div class="sf-summary-item">
                        <dt>Variantes en el filtro</dt>
                        <dd>{{ rows.total }}</dd>
                    </div>
                    <div class="sf-summary-item sf-summary-critical">
                        <dt>Stock crítico en la sucursal</dt>
                        <dd>{{ criticalCount }}</dd>
                    </div>
                </dl>
                <p class="sf-section-hint">
                    Una variante es crítica solo cuando tiene configuración con punto mayor que 0 y su
                    disponible es igual o menor al punto. Un punto 0 o una variante sin configurar no genera
                    alerta.
                </p>
            </section>

            <section class="sf-card" aria-labelledby="reorder-filters-title">
                <h2 id="reorder-filters-title" class="sf-section-title">Filtros del panel</h2>
                <form class="sf-filters" role="search" novalidate @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="reorder-q">Buscar por SKU o nombre de producto</label>
                        <input
                            id="reorder-q"
                            v-model="searchTerm"
                            type="search"
                            maxlength="100"
                            class="sf-input"
                            placeholder="Ej.: SKU-123 o arroz"
                        />
                    </div>

                    <div class="sf-field sf-check-field">
                        <label class="sf-check-label" for="reorder-critical">
                            <input id="reorder-critical" v-model="criticalTerm" type="checkbox" />
                            Solo stock crítico
                        </label>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="reorder-per-page">Variantes por página</label>
                        <select id="reorder-per-page" v-model="perPageTerm" class="sf-input">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div class="sf-form-actions sf-field-wide">
                        <button type="submit" class="sf-button sf-button-primary">Aplicar filtros</button>
                        <button type="button" class="sf-button sf-button-secondary" @click="resetFilters">
                            Limpiar filtros
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card" aria-labelledby="reorder-list-title">
                <h2 id="reorder-list-title" class="sf-section-title">Variantes y umbrales</h2>
                <p class="sf-section-hint">
                    Las filas históricas de variantes inactivas o eliminadas se muestran solo para consulta y no
                    admiten cambios. La cantidad sugerida y el máximo pueden quedar sin valor.
                </p>

                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ rows.total }} variante(s) en el filtro actual.
                    <template v-if="rows.from !== null && rows.to !== null">
                        Mostrando {{ rows.from }}–{{ rows.to }}.
                    </template>
                </p>

                <div v-if="rows.data.length === 0" class="sf-empty-text">
                    No hay variantes que coincidan con los filtros en esta sucursal.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">
                            Variantes de la sucursal {{ branch.name }} con existencias y configuración de reposición.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">SKU</th>
                                <th scope="col">Producto</th>
                                <th scope="col">Existencia</th>
                                <th scope="col">Reservado</th>
                                <th scope="col">Disponible</th>
                                <th scope="col">Punto de reposición</th>
                                <th scope="col">Cantidad sugerida</th>
                                <th scope="col">Máximo</th>
                                <th scope="col">Estado crítico</th>
                                <th scope="col" class="sf-actions-heading">Configuración</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in rows.data" :key="`reorder-${row.variant_id}`">
                                <tr :class="{ 'sf-critical-row': row.is_critical }">
                                    <td data-label="SKU">{{ row.sku }}</td>
                                    <td data-label="Producto"><strong>{{ row.product_name }}</strong></td>
                                    <td data-label="Existencia" class="sf-quantity">{{ row.on_hand }}</td>
                                    <td data-label="Reservado" class="sf-quantity">{{ row.reserved }}</td>
                                    <td
                                        data-label="Disponible"
                                        class="sf-quantity"
                                        :class="{ 'sf-negative': row.available.startsWith('-') }"
                                    >
                                        {{ row.available }}
                                    </td>
                                    <td data-label="Punto de reposición" class="sf-quantity">
                                        <template v-if="row.has_settings">{{ row.reorder_point }}</template>
                                        <span v-else class="sf-text-muted">Sin configurar</span>
                                    </td>
                                    <td data-label="Cantidad sugerida" class="sf-quantity">
                                        <template v-if="row.reorder_qty !== null">{{ row.reorder_qty }}</template>
                                        <span v-else class="sf-text-muted">Sin cantidad sugerida</span>
                                    </td>
                                    <td data-label="Máximo" class="sf-quantity">
                                        <template v-if="row.max_stock !== null">{{ row.max_stock }}</template>
                                        <span v-else class="sf-text-muted">Sin máximo</span>
                                    </td>
                                    <td data-label="Estado crítico">
                                        <span v-if="row.is_critical" class="sf-badge sf-badge-critical">Crítico</span>
                                        <span v-else class="sf-text-muted">Sin alerta</span>
                                    </td>
                                    <td data-label="Configuración" class="sf-actions-cell">
                                        <button
                                            v-if="row.can_edit && canAdjust && !readOnly"
                                            type="button"
                                            class="sf-link"
                                            :aria-expanded="activeRow?.variant_id === row.variant_id"
                                            @click="openForm(row)"
                                        >
                                            {{ row.has_settings ? 'Editar' : 'Configurar' }}
                                        </button>
                                        <span v-else-if="!row.can_edit" class="sf-text-muted">
                                            Histórica (solo lectura)
                                        </span>
                                        <span v-else class="sf-text-muted">Solo lectura</span>
                                    </td>
                                </tr>
                                <tr
                                    v-if="activeRow && activeRow.variant_id === row.variant_id"
                                    class="sf-form-row"
                                >
                                    <td colspan="10">
                                        <form class="sf-inline-form" novalidate @submit.prevent="submit">
                                            <h3 class="sf-form-title">
                                                Configurar reposición — {{ row.product_name }} ({{ row.sku }})
                                            </h3>
                                            <p class="sf-form-summary">
                                                Disponible actual: <strong>{{ row.available }}</strong>.
                                                El punto de reposición es obligatorio y no puede ser negativo.
                                            </p>

                                            <div class="sf-form-grid">
                                                <div class="sf-field">
                                                    <label class="sf-label" for="reorder-point">Punto de reposición</label>
                                                    <input
                                                        id="reorder-point"
                                                        v-model="form.reorder_point"
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        inputmode="decimal"
                                                        class="sf-input"
                                                        :aria-describedby="form.errors.reorder_point ? 'reorder-point-error' : undefined"
                                                    />
                                                    <p v-if="form.errors.reorder_point" id="reorder-point-error" class="sf-error" role="alert">
                                                        {{ form.errors.reorder_point }}
                                                    </p>
                                                </div>

                                                <div class="sf-field">
                                                    <label class="sf-label" for="reorder-qty">
                                                        Cantidad sugerida (opcional)
                                                    </label>
                                                    <input
                                                        id="reorder-qty"
                                                        v-model="form.reorder_qty"
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        inputmode="decimal"
                                                        class="sf-input"
                                                        :aria-describedby="form.errors.reorder_qty ? 'reorder-qty-error' : undefined"
                                                    />
                                                    <p v-if="form.errors.reorder_qty" id="reorder-qty-error" class="sf-error" role="alert">
                                                        {{ form.errors.reorder_qty }}
                                                    </p>
                                                </div>

                                                <div class="sf-field">
                                                    <label class="sf-label" for="reorder-max">
                                                        Stock máximo (opcional)
                                                    </label>
                                                    <input
                                                        id="reorder-max"
                                                        v-model="form.max_stock"
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        inputmode="decimal"
                                                        class="sf-input"
                                                        :aria-describedby="form.errors.max_stock ? 'reorder-max-error' : undefined"
                                                    />
                                                    <p v-if="form.errors.max_stock" id="reorder-max-error" class="sf-error" role="alert">
                                                        {{ form.errors.max_stock }}
                                                    </p>
                                                </div>
                                            </div>

                                            <p v-if="form.errors.variant_id" class="sf-error" role="alert">
                                                {{ form.errors.variant_id }}
                                            </p>
                                            <p v-if="form.errors.read_only" class="sf-error" role="alert">
                                                {{ form.errors.read_only }}
                                            </p>
                                            <p v-if="form.errors.reorder" class="sf-error" role="alert">
                                                {{ form.errors.reorder }}
                                            </p>

                                            <div class="sf-form-actions">
                                                <button
                                                    type="submit"
                                                    class="sf-button sf-button-primary"
                                                    :disabled="form.processing || readOnly"
                                                >
                                                    Guardar configuración
                                                </button>
                                                <button type="button" class="sf-button sf-button-secondary" @click="closeForm">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="rows.previous_page_url || rows.next_page_url"
                    class="sf-pagination"
                    aria-label="Paginación del panel de reposición"
                >
                    <Link
                        v-if="rows.previous_page_url"
                        :href="rows.previous_page_url"
                        class="sf-page-link"
                        preserve-scroll
                    >
                        Anterior
                    </Link>
                    <span class="sf-page-status">
                        Página {{ rows.current_page }} de {{ rows.last_page }}
                    </span>
                    <Link
                        v-if="rows.next_page_url"
                        :href="rows.next_page_url"
                        class="sf-page-link"
                        preserve-scroll
                    >
                        Siguiente
                    </Link>
                </nav>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

interface ReorderRow {
    variant_id: number;
    product_id: number;
    sku: string;
    product_name: string;
    can_edit: boolean;
    has_settings: boolean;
    on_hand: string;
    reserved: string;
    available: string;
    reorder_point: string;
    reorder_qty: string | null;
    max_stock: string | null;
    is_critical: boolean;
}

interface ReorderRowsPage {
    data: ReorderRow[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    previous_page_url: string | null;
    next_page_url: string | null;
}

interface ReorderFilters {
    q: string;
    critical: boolean;
    per_page: number;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { name?: string } | null;
    };
    flash?: {
        success?: string | null;
        warning?: string | null;
        error?: string | null;
    };
}

const props = defineProps<{
    branch: { id: number; code: string; name: string };
    rows: ReorderRowsPage;
    filters: ReorderFilters;
    critical_count: number;
    can_adjust: boolean;
    can_view_inventory: boolean;
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const flash = computed(() => page.props.flash ?? {});
const canViewInventory = computed(() => props.can_view_inventory === true);
const canAdjust = computed(() => props.can_adjust === true);
const readOnly = computed(() => props.read_only === true);
const criticalCount = computed(() => props.critical_count ?? 0);

const searchTerm = ref(props.filters.q ?? '');
const criticalTerm = ref(props.filters.critical === true);
const perPageTerm = ref(String(props.filters.per_page ?? 25));
const activeRow = ref<ReorderRow | null>(null);

const form = useForm({
    reorder_point: '',
    reorder_qty: '',
    max_stock: '',
    // Errores del servidor que no corresponden a un campo visible.
    variant_id: '',
    read_only: '',
    reorder: '',
});

watch(
    () => props.filters,
    (value) => {
        searchTerm.value = value.q ?? '';
        criticalTerm.value = value.critical === true;
        perPageTerm.value = String(value.per_page ?? 25);
    },
    { deep: true },
);

function applyFilters(): void {
    const params: Record<string, string> = {};

    if (searchTerm.value.trim() !== '') {
        params.q = searchTerm.value.trim();
    }

    if (criticalTerm.value) {
        params.critical = '1';
    }

    params.per_page = perPageTerm.value;

    router.get(`/inventory/stock/${props.branch.id}/reorder`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    searchTerm.value = '';
    criticalTerm.value = false;
    perPageTerm.value = '25';
    router.get(`/inventory/stock/${props.branch.id}/reorder`, { per_page: '25' }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openForm(row: ReorderRow): void {
    if (!row.can_edit || !canAdjust.value || readOnly.value) {
        return;
    }

    if (activeRow.value?.variant_id === row.variant_id) {
        closeForm();

        return;
    }

    form.reset();
    form.clearErrors();
    form.reorder_point = row.has_settings ? row.reorder_point : '';
    form.reorder_qty = row.reorder_qty ?? '';
    form.max_stock = row.max_stock ?? '';
    activeRow.value = row;
}

function closeForm(): void {
    activeRow.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    if (!activeRow.value) {
        return;
    }

    form.clearErrors();

    form.transform((data) => ({
        reorder_point: data.reorder_point,
        reorder_qty: data.reorder_qty === '' ? null : data.reorder_qty,
        max_stock: data.max_stock === '' ? null : data.max_stock,
    }));

    form.put(`/inventory/stock/${props.branch.id}/reorder/${activeRow.value.variant_id}`, {
        preserveScroll: true,
        onSuccess: () => {
            activeRow.value = null;
            form.reset();
        },
    });
}
</script>

<style scoped>
.sf-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f3f4f6;
}

.sf-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1rem 2rem;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

.sf-brand {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
}

.sf-brand .stock {
    color: #0f172a;
}

.sf-brand .flash {
    color: #2563eb;
}

.sf-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.sf-user-name {
    font-size: 0.875rem;
    color: #374151;
}

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.sf-titlebar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}

.sf-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.sf-subtitle {
    margin-top: 0.35rem;
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.5;
    max-width: 820px;
}

.sf-branch {
    font-size: 0.9rem;
    color: #1f2937;
}

.sf-titlebar-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.sf-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sf-section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #111827;
}

.sf-section-hint {
    margin: 0.35rem 0 1rem;
    font-size: 0.8125rem;
    color: #6b7280;
    line-height: 1.5;
}

.sf-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
    margin: 0.85rem 0 0;
}

.sf-summary-item {
    padding: 0.85rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    background: #f9fafb;
}

.sf-summary-item dt {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #4b5563;
}

.sf-summary-item dd {
    margin: 0.25rem 0 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    font-variant-numeric: tabular-nums;
}

.sf-summary-critical dd {
    color: #b91c1c;
}

.sf-filters {
    margin-top: 0.85rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.sf-field-wide {
    grid-column: 1 / -1;
}

.sf-check-field {
    justify-content: flex-end;
}

.sf-check-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.sf-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
}

.sf-input {
    width: 100%;
    padding: 0.5rem 0.65rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.9rem;
    background: #ffffff;
    color: #111827;
    box-sizing: border-box;
}

.sf-empty-text {
    padding: 1rem;
    border: 1px dashed #d1d5db;
    border-radius: 0.5rem;
    color: #6b7280;
    font-size: 0.875rem;
}

.sf-table-wrap {
    overflow-x: auto;
    margin: 0.75rem 0 1rem;
}

.sf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.sf-table th,
.sf-table td {
    padding: 0.6rem 0.65rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
}

.sf-table th {
    background: #f9fafb;
    color: #374151;
    font-weight: 700;
    white-space: nowrap;
}

.sf-quantity {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.sf-negative {
    color: #b91c1c;
    font-weight: 600;
}

.sf-critical-row td {
    background: #fef2f2;
}

.sf-badge {
    display: inline-block;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.sf-badge-critical {
    background: #fee2e2;
    color: #991b1b;
}

.sf-actions-heading {
    width: 12rem;
}

.sf-actions-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.sf-form-row td {
    background: #f9fafb;
}

.sf-inline-form {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.5rem 0;
}

.sf-form-title {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.sf-form-summary {
    font-size: 0.85rem;
    color: #374151;
}

.sf-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
}

.sf-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.sf-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.sf-page-status {
    font-size: 0.8125rem;
    color: #4b5563;
}

.sf-page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    padding: 0.35rem 0.65rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    color: #1d4ed8;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
}

.sf-link:focus-visible,
.sf-button:focus-visible,
.sf-input:focus-visible,
.sf-page-link:focus-visible {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
}

.sf-button {
    display: inline-block;
    padding: 0.55rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.sf-button-primary {
    background: #2563eb;
    color: #ffffff;
}

.sf-button-primary:hover {
    background: #1d4ed8;
}

.sf-button-secondary {
    background: #e5e7eb;
    color: #111827;
}

.sf-button-secondary:hover {
    background: #d1d5db;
}

.sf-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sf-link {
    padding: 0;
    border: none;
    background: none;
    color: #1d4ed8;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}

.sf-message {
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.sf-success {
    background: #dcfce7;
    color: #166534;
}

.sf-warning {
    background: #fef3c7;
    color: #92400e;
}

.sf-info {
    background: #eff6ff;
    color: #1e3a8a;
}

.sf-error {
    color: #b91c1c;
    font-size: 0.8125rem;
    font-weight: 600;
}

.sf-text-muted {
    color: #6b7280;
    font-size: 0.8125rem;
}

.sf-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 760px) {
    .sf-header {
        padding: 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .sf-main {
        padding: 1rem;
    }

    .sf-titlebar-side {
        align-items: flex-start;
    }

    .sf-card {
        padding: 1rem;
    }

    .sf-table thead {
        display: none;
    }

    .sf-table,
    .sf-table tbody,
    .sf-table tr,
    .sf-table td {
        display: block;
        width: 100%;
    }

    .sf-table tr {
        border-bottom: 1px solid #e5e7eb;
        padding: 0.5rem 0;
    }

    .sf-table td {
        border: none;
        padding: 0.3rem 0;
    }

    .sf-table td::before {
        content: attr(data-label);
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
    }

    .sf-actions-cell {
        display: flex;
    }
}
</style>
