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
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">Kardex de inventario</h1>
                    <p class="sf-subtitle">
                        Reporte de solo lectura con los movimientos físicos registrados para la sucursal,
                        la variante y el período seleccionados. No se calculan costos, precios ni márgenes.
                    </p>
                </div>
                <p class="sf-branch" aria-live="polite">
                    Sucursal: <strong>{{ branch.name }}</strong>
                    <span class="sf-text-muted">({{ branch.code }})</span>
                </p>
            </div>

            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: el Kardex puede consultarse, pero no se registran movimientos
                hasta regularizar la licencia.
            </div>

            <div v-if="Object.keys(errors).length > 0" class="sf-message sf-error" role="alert">
                <p>Revise los filtros ingresados.</p>
                <ul class="sf-error-list">
                    <li v-for="(message, field) in errors" :key="`error-${field}`">{{ message }}</li>
                </ul>
            </div>

            <section class="sf-card" aria-labelledby="kardex-filters-title">
                <h2 id="kardex-filters-title" class="sf-section-title">Filtros del reporte</h2>
                <form class="sf-filters" role="search" novalidate @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="kardex-q">Buscar por SKU o nombre de producto</label>
                        <input
                            id="kardex-q"
                            v-model="form.q"
                            type="search"
                            maxlength="100"
                            class="sf-input"
                            placeholder="Ej.: SKU-123 o arroz"
                        />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="kardex-variant">Variante (opcional)</label>
                        <input
                            id="kardex-variant"
                            v-model="form.variant_id"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            class="sf-input"
                            placeholder="Identificador de variante"
                            aria-describedby="kardex-variant-hint"
                        />
                        <p id="kardex-variant-hint" class="sf-text-muted">
                            Solo se muestran variantes de esta sucursal.
                        </p>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="kardex-from">Desde</label>
                        <input id="kardex-from" v-model="form.from" type="date" class="sf-input" />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="kardex-to">Hasta</label>
                        <input id="kardex-to" v-model="form.to" type="date" class="sf-input" />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="kardex-per-page">Movimientos por página</label>
                        <select id="kardex-per-page" v-model="form.per_page" class="sf-input">
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

            <section class="sf-card" aria-labelledby="kardex-list-title">
                <h2 id="kardex-list-title" class="sf-section-title">Movimientos registrados</h2>
                <p class="sf-section-hint">
                    Ordenado por orden de anexado (ID). El saldo registrado corresponde a ese momento;
                    no se recalcula por fecha.
                </p>

                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ movements.data.length }} movimiento(s) en esta página.
                    <template v-if="movements.per_page">
                        Hasta {{ movements.per_page }} por página.
                    </template>
                </p>

                <div v-if="movements.data.length === 0" class="sf-empty-text">
                    No hay movimientos que coincidan con los filtros en esta sucursal.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">
                            Movimientos del Kardex de la sucursal {{ branch.name }}, ordenados por ID descendente.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Fecha y hora</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Cantidad</th>
                                <th scope="col">Saldo registrado</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Producto</th>
                                <th scope="col">Corrección</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in movements.data" :key="`movement-${row.id}`">
                                <td data-label="Fecha y hora">
                                    <time :datetime="toDateTimeAttribute(row.occurred_at)">{{ row.occurred_at }}</time>
                                </td>
                                <td data-label="Tipo">{{ row.type_label }}</td>
                                <td data-label="Cantidad" class="sf-quantity">
                                    <span :class="{ 'sf-negative': row.quantity.startsWith('-') }">
                                        {{ signedQuantity(row.quantity) }}
                                    </span>
                                </td>
                                <td data-label="Saldo registrado">{{ row.balance_after }}</td>
                                <td data-label="SKU">{{ row.sku }}</td>
                                <td data-label="Producto"><strong>{{ row.product_name }}</strong></td>
                                <td data-label="Corrección">
                                    <template v-if="row.is_reversal">
                                        <span class="sf-badge">Reversa</span>
                                        Corrige el movimiento #{{ row.reverses_movement_id }}
                                    </template>
                                    <template v-else>—</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="movements.previous_page_url || movements.next_page_url"
                    class="sf-pagination"
                    aria-label="Paginación del Kardex"
                >
                    <Link
                        v-if="movements.previous_page_url"
                        :href="movements.previous_page_url"
                        class="sf-page-link"
                        preserve-scroll
                    >
                        Página anterior
                    </Link>
                    <Link
                        v-if="movements.next_page_url"
                        :href="movements.next_page_url"
                        class="sf-page-link"
                        preserve-scroll
                    >
                        Página siguiente
                    </Link>
                </nav>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface MovementRow {
    id: number;
    occurred_at: string;
    type: string;
    type_label: string;
    quantity: string;
    balance_after: string;
    reverses_movement_id: number | null;
    is_reversal: boolean;
    variant_id: number;
    sku: string;
    product_name: string;
}

interface MovementPage {
    data: MovementRow[];
    previous_page_url: string | null;
    next_page_url: string | null;
    per_page: number;
}

interface KardexFilters {
    q: string;
    variant_id: number | null;
    from: string | null;
    to: string | null;
    per_page: number;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { name?: string } | null;
    };
    errors?: Record<string, string>;
}

const props = defineProps<{
    branch: { id: number; code: string; name: string };
    movements: MovementPage;
    filters: KardexFilters;
    read_only: boolean;
    can_view_inventory: boolean;
}>();

const page = usePage<SharedPageProps>();
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errors = computed(() => page.props.errors ?? {});
const readOnly = computed(() => props.read_only === true);

function filterForm(source: KardexFilters) {
    return {
        q: source.q ?? '',
        variant_id: source.variant_id === null ? '' : String(source.variant_id),
        from: source.from ?? '',
        to: source.to ?? '',
        per_page: String(source.per_page ?? 25),
    };
}

const form = reactive(filterForm(props.filters));

watch(
    () => props.filters,
    (value) => {
        Object.assign(form, filterForm(value));
    },
    { deep: true },
);

function signedQuantity(value: string): string {
    return value.startsWith('-') ? value : `+${value}`;
}

function toDateTimeAttribute(value: string): string {
    return value.replace(' ', 'T');
}

function applyFilters(): void {
    const params: Record<string, string> = {};

    if (form.q.trim() !== '') {
        params.q = form.q.trim();
    }

    if (form.variant_id.trim() !== '') {
        params.variant_id = form.variant_id.trim();
    }

    if (form.from !== '') {
        params.from = form.from;
    }

    if (form.to !== '') {
        params.to = form.to;
    }

    if (form.per_page !== '') {
        params.per_page = form.per_page;
    }

    router.get(`/inventory/stock/${props.branch.id}/kardex`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    Object.assign(form, filterForm({
        q: '',
        variant_id: null,
        from: null,
        to: null,
        per_page: 25,
    }));

    router.get(`/inventory/stock/${props.branch.id}/kardex`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
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
    gap: 1rem;
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
}

.sf-quantity {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.sf-negative {
    color: #b91c1c;
    font-weight: 600;
}

.sf-badge {
    display: inline-block;
    margin-right: 0.35rem;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #92400e;
    font-size: 0.75rem;
    font-weight: 700;
}

.sf-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.sf-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.75rem;
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

.sf-message {
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.sf-warning {
    background: #fef3c7;
    color: #92400e;
}

.sf-error {
    background: #fee2e2;
    color: #991b1b;
}

.sf-error-list {
    margin: 0.35rem 0 0;
    padding-left: 1.2rem;
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
}
</style>
