<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">Inventario</h1>
                    <p class="sf-subtitle">
                        Consulte la existencia física, el stock reservado y el disponible por sucursal.
                        Esta pantalla es solo lectura: no registra movimientos, ajustes, ventas ni reservas.
                    </p>
                </div>
                <p class="sf-branch" aria-live="polite">
                    Sucursal: <strong>{{ branch.name }}</strong> <span class="sf-text-muted">({{ branch.code }})</span>
                </p>
            </div>

            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: el inventario puede consultarse, pero no se registran movimientos hasta regularizar la licencia.
            </div>
            <div v-if="!canViewInventory" class="sf-message sf-info" role="note">
                Su usuario no tiene permiso para consultar inventario en esta sucursal. La autorización se verifica siempre en el servidor.
            </div>

            <section class="sf-card" aria-labelledby="stock-list-title">
                <h2 id="stock-list-title" class="sf-section-title">Existencias por variante</h2>
                <p class="sf-section-hint">
                    Disponible = existencia física − reservado. El cálculo lo realiza el esquema de base de datos.
                    Las reservas y los documentos de venta se incorporarán en etapas posteriores.
                </p>

                <form class="sf-search" role="search" @submit.prevent="search">
                    <label class="sf-label" for="stock-search">Buscar por SKU o nombre de producto</label>
                    <div class="sf-search-row">
                        <input
                            id="stock-search"
                            v-model="searchTerm"
                            type="search"
                            maxlength="100"
                            class="sf-input"
                            placeholder="Ej.: SKU-123 o arroz"
                        />
                        <button type="submit" class="sf-button sf-button-primary">Buscar</button>
                        <button
                            v-if="activeFilter !== ''"
                            type="button"
                            class="sf-button sf-button-secondary"
                            @click="clearSearch"
                        >
                            Limpiar
                        </button>
                    </div>
                </form>

                <p class="sf-text-muted" role="status">
                    {{ stock.total }} variante(s) encontrada(s).
                    <template v-if="stock.from !== null && stock.to !== null">
                        Mostrando {{ stock.from }}–{{ stock.to }}.
                    </template>
                </p>

                <div v-if="stock.data.length === 0" class="sf-empty-text">
                    No hay variantes que coincidan con la búsqueda en esta sucursal.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Existencia</th>
                                <th scope="col">Reservado</th>
                                <th scope="col">Disponible</th>
                                <th scope="col">Último movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in stock.data" :key="`stock-${row.variant_id}`">
                                <td data-label="Producto"><strong>{{ row.product_name }}</strong></td>
                                <td data-label="SKU">{{ row.sku }}</td>
                                <td data-label="Existencia">{{ row.on_hand }}</td>
                                <td data-label="Reservado">{{ row.reserved }}</td>
                                <td data-label="Disponible">{{ row.available }}</td>
                                <td data-label="Último movimiento">{{ row.last_movement_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="stock.last_page > 1" class="sf-pagination" aria-label="Paginación de inventario">
                    <template v-for="link in stock.links" :key="`page-${link.label}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="sf-page-link"
                            :class="{ 'sf-page-link-active': link.active }"
                            :aria-current="link.active ? 'page' : undefined"
                            preserve-scroll
                            preserve-state
                            v-html="link.label"
                        />
                        <span v-else class="sf-page-link sf-page-link-disabled" aria-disabled="true" v-html="link.label" />
                    </template>
                </nav>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface StockRow {
    variant_id: number;
    product_id: number;
    sku: string;
    product_name: string;
    on_hand: string;
    reserved: string;
    available: string;
    last_movement_at: string | null;
}

interface StockPage {
    data: StockRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { name?: string } | null;
    };
}

const props = defineProps<{
    branch: { id: number; code: string; name: string };
    stock: StockPage;
    filters: { q: string };
    can_view_inventory: boolean;
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const searchTerm = ref(props.filters.q ?? '');
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const canViewInventory = computed(() => props.can_view_inventory === true);
const readOnly = computed(() => props.read_only === true);
const activeFilter = computed(() => props.filters.q ?? '');

watch(
    () => props.filters.q,
    (value) => {
        searchTerm.value = value ?? '';
    },
);

function search(): void {
    router.get(
        `/inventory/stock/${props.branch.id}`,
        { q: searchTerm.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearSearch(): void {
    searchTerm.value = '';
    search();
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

.sf-search {
    margin-top: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sf-search-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
}

.sf-search-row .sf-input {
    flex: 1 1 280px;
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
    padding: 0.35rem 0.55rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    color: #1d4ed8;
    font-size: 0.8125rem;
    text-decoration: none;
}

.sf-page-link-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    font-weight: 700;
}

.sf-page-link-disabled {
    color: #9ca3af;
    cursor: not-allowed;
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

.sf-info {
    background: #eff6ff;
    color: #1e3a8a;
}

.sf-text-muted {
    color: #6b7280;
    font-size: 0.8125rem;
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
