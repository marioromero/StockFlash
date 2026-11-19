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
                        Con permiso de ajuste puede cargar existencias iniciales y registrar correcciones
                        con motivo; el sistema valida permisos, licencia y autorización en el servidor.
                    </p>
                </div>
                <div class="sf-titlebar-side">
                    <p class="sf-branch" aria-live="polite">
                        Sucursal: <strong>{{ branch.name }}</strong> <span class="sf-text-muted">({{ branch.code }})</span>
                    </p>
                    <Link
                        :href="`/inventory/stock/${branch.id}/kardex`"
                        class="sf-button sf-button-secondary"
                    >
                        Ver Kardex
                    </Link>
                    <Link
                        :href="`/inventory/stock/${branch.id}/reorder`"
                        class="sf-button sf-button-secondary"
                    >
                        Reposición
                    </Link>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: el inventario puede consultarse, pero no se registran movimientos hasta regularizar la licencia.
            </div>
            <div v-if="!canViewInventory" class="sf-message sf-info" role="note">
                Su usuario no tiene permiso para consultar inventario en esta sucursal. La autorización se verifica siempre en el servidor.
            </div>
            <div v-else-if="!canAdjust" class="sf-message sf-info" role="note">
                Su usuario puede consultar existencias, pero no cargar ni ajustar inventario en esta sucursal.
            </div>

            <section class="sf-card" aria-labelledby="stock-list-title">
                <h2 id="stock-list-title" class="sf-section-title">Existencias por variante</h2>
                <p class="sf-section-hint">
                    Disponible = existencia física − reservado. El cálculo lo realiza el esquema de base de datos.
                    La carga inicial solo se ofrece cuando la variante no registra movimientos en la sucursal.
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
                                <th v-if="canAdjust" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in stock.data" :key="`stock-${row.variant_id}`">
                                <tr>
                                    <td data-label="Producto"><strong>{{ row.product_name }}</strong></td>
                                    <td data-label="SKU">{{ row.sku }}</td>
                                    <td data-label="Existencia">{{ row.on_hand }}</td>
                                    <td data-label="Reservado">{{ row.reserved }}</td>
                                    <td data-label="Disponible">{{ row.available }}</td>
                                    <td data-label="Último movimiento">{{ row.last_movement_at ?? '—' }}</td>
                                    <td v-if="canAdjust" data-label="Acciones" class="sf-actions-cell">
                                        <button
                                            v-if="!row.initialized"
                                            type="button"
                                            class="sf-link"
                                            :aria-expanded="isActive(row, 'initial')"
                                            :disabled="readOnly"
                                            @click="openForm(row, 'initial')"
                                        >
                                            Cargar inicial
                                        </button>
                                        <button
                                            type="button"
                                            class="sf-link"
                                            :aria-expanded="isActive(row, 'adjustment')"
                                            :disabled="readOnly"
                                            @click="openForm(row, 'adjustment')"
                                        >
                                            Ajustar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="activeRow && activeRow.variant_id === row.variant_id" class="sf-form-row">
                                    <td :colspan="canAdjust ? 7 : 6">
                                        <form class="sf-inline-form" novalidate @submit.prevent="submit">
                                            <h3 class="sf-form-title">
                                                {{ mode === 'initial' ? 'Cargar existencia inicial' : 'Registrar ajuste de existencias' }}
                                                — {{ row.product_name }} ({{ row.sku }})
                                            </h3>
                                            <p class="sf-form-summary">
                                                Existencia actual: <strong>{{ row.on_hand }}</strong> ·
                                                Reservado: <strong>{{ row.reserved }}</strong> ·
                                                Disponible: <strong>{{ row.available }}</strong>
                                            </p>

                                            <div class="sf-form-grid">
                                                <div class="sf-field">
                                                    <label class="sf-label" for="stock-write-quantity">
                                                        {{ mode === 'initial' ? 'Cantidad inicial' : 'Cantidad del ajuste (con signo)' }}
                                                    </label>
                                                    <input
                                                        id="stock-write-quantity"
                                                        v-model="form.quantity"
                                                        type="text"
                                                        inputmode="decimal"
                                                        autocomplete="off"
                                                        class="sf-input"
                                                        :aria-describedby="`stock-write-quantity-hint${form.errors.quantity ? ' stock-write-quantity-error' : ''}`"
                                                    />
                                                    <p id="stock-write-quantity-hint" class="sf-text-muted">
                                                        {{ mode === 'initial'
                                                            ? 'Mayor que cero. Hasta 9 enteros y 3 decimales.'
                                                            : 'Use signo negativo para corregir hacia abajo. Hasta 9 enteros y 3 decimales.' }}
                                                    </p>
                                                    <p v-if="form.errors.quantity" id="stock-write-quantity-error" class="sf-error" role="alert">
                                                        {{ form.errors.quantity }}
                                                    </p>
                                                </div>

                                                <template v-if="mode === 'adjustment'">
                                                    <div class="sf-field">
                                                        <label class="sf-label" for="stock-write-reason">Motivo</label>
                                                        <select
                                                            id="stock-write-reason"
                                                            v-model="form.reason_code_id"
                                                            class="sf-input"
                                                            :aria-describedby="form.errors.reason_code_id ? 'stock-write-reason-error' : undefined"
                                                        >
                                                            <option value="">Seleccione un motivo</option>
                                                            <option v-for="reason in reasons" :key="`reason-${reason.id}`" :value="String(reason.id)">
                                                                {{ reason.label }}
                                                            </option>
                                                        </select>
                                                        <p v-if="form.errors.reason_code_id" id="stock-write-reason-error" class="sf-error" role="alert">
                                                            {{ form.errors.reason_code_id }}
                                                        </p>
                                                    </div>

                                                    <div class="sf-field sf-field-wide">
                                                        <label class="sf-label" for="stock-write-note">
                                                            Nota
                                                            <span v-if="selectedReason?.requires_note" class="sf-required">(obligatoria para este motivo)</span>
                                                        </label>
                                                        <textarea
                                                            id="stock-write-note"
                                                            v-model="form.note"
                                                            class="sf-input"
                                                            rows="2"
                                                            maxlength="255"
                                                            :aria-describedby="`stock-write-note-hint${form.errors.note ? ' stock-write-note-error' : ''}`"
                                                        />
                                                        <p id="stock-write-note-hint" class="sf-text-muted">
                                                            Máximo 255 caracteres. No incluya datos personales ni información de pago.
                                                        </p>
                                                        <p v-if="form.errors.note" id="stock-write-note-error" class="sf-error" role="alert">
                                                            {{ form.errors.note }}
                                                        </p>
                                                    </div>
                                                </template>
                                            </div>

                                            <fieldset v-if="mode === 'adjustment'" class="sf-authorizer">
                                                <legend class="sf-label">Autorización por PIN (solo si el valor del ajuste supera el umbral)</legend>
                                                <p class="sf-text-muted">
                                                    Complete autorizador y PIN solo cuando el sistema lo solicite por el valor a costo del ajuste.
                                                    El autorizador debe tener el permiso override.authorize y autoridad suficiente en la sucursal; puede ser el mismo usuario actual si cumple la política.
                                                </p>
                                                <div class="sf-form-grid">
                                                    <div class="sf-field">
                                                        <label class="sf-label" for="stock-write-authorizer">Autorizador</label>
                                                        <select
                                                            id="stock-write-authorizer"
                                                            v-model="form.authorizer_id"
                                                            class="sf-input"
                                                            :aria-describedby="form.errors.authorizer_id ? 'stock-write-authorizer-error' : undefined"
                                                        >
                                                            <option value="">Sin autorizador</option>
                                                            <option v-for="authorizer in authorizers" :key="`authorizer-${authorizer.id}`" :value="String(authorizer.id)">
                                                                {{ authorizer.name }}
                                                            </option>
                                                        </select>
                                                        <p v-if="form.errors.authorizer_id" id="stock-write-authorizer-error" class="sf-error" role="alert">
                                                            {{ form.errors.authorizer_id }}
                                                        </p>
                                                    </div>

                                                    <div class="sf-field">
                                                        <label class="sf-label" for="stock-write-pin">PIN del autorizador</label>
                                                        <input
                                                            id="stock-write-pin"
                                                            v-model="form.pin"
                                                            type="password"
                                                            inputmode="numeric"
                                                            autocomplete="off"
                                                            class="sf-input"
                                                            :aria-describedby="form.errors.pin ? 'stock-write-pin-error' : undefined"
                                                        />
                                                        <p v-if="form.errors.pin" id="stock-write-pin-error" class="sf-error" role="alert">
                                                            {{ form.errors.pin }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </fieldset>

                                            <p v-if="form.errors.request_token" class="sf-error" role="alert">
                                                {{ form.errors.request_token }}
                                            </p>
                                            <p v-if="form.errors.variant_id" class="sf-error" role="alert">
                                                {{ form.errors.variant_id }}
                                            </p>
                                            <p v-if="form.errors.adjustment" class="sf-error" role="alert">
                                                {{ form.errors.adjustment }}
                                            </p>
                                            <p v-if="form.errors.read_only" class="sf-error" role="alert">
                                                {{ form.errors.read_only }}
                                            </p>

                                            <input v-if="mode === 'adjustment'" v-model="form.request_token" type="hidden" />

                                            <div class="sf-form-actions">
                                                <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing || readOnly">
                                                    {{ mode === 'initial' ? 'Registrar carga inicial' : 'Registrar ajuste' }}
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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

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
    initialized: boolean;
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

interface ReasonOption {
    id: number;
    label: string;
    requires_note: boolean;
}

interface AuthorizerOption {
    id: number;
    name: string;
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
    stock: StockPage;
    filters: { q: string };
    can_view_inventory: boolean;
    can_adjust?: boolean;
    read_only: boolean;
    reasons?: ReasonOption[];
    authorizers?: AuthorizerOption[];
    adjustment_token?: string;
}>();

type WriteMode = 'initial' | 'adjustment';

const page = usePage<SharedPageProps>();
const searchTerm = ref(props.filters.q ?? '');
const activeRow = ref<StockRow | null>(null);
const mode = ref<WriteMode>('initial');

const form = useForm({
    variant_id: 0,
    quantity: '',
    reason_code_id: '',
    note: '',
    request_token: props.adjustment_token ?? '',
    authorizer_id: '',
    pin: '',
    // Errores de servidor que no corresponden a un campo visible del formulario.
    adjustment: '',
    read_only: '',
});

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const canViewInventory = computed(() => props.can_view_inventory === true);
const canAdjust = computed(() => props.can_adjust === true);
const readOnly = computed(() => props.read_only === true);
const activeFilter = computed(() => props.filters.q ?? '');
const reasons = computed(() => props.reasons ?? []);
const authorizers = computed(() => props.authorizers ?? []);
const selectedReason = computed(
    () => reasons.value.find((reason) => String(reason.id) === form.reason_code_id) ?? null,
);

watch(
    () => props.filters.q,
    (value) => {
        searchTerm.value = value ?? '';
    },
);

watch(
    () => props.adjustment_token,
    (value) => {
        form.request_token = value ?? '';
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

function isActive(row: StockRow, rowMode: WriteMode): boolean {
    return activeRow.value?.variant_id === row.variant_id && mode.value === rowMode;
}

function openForm(row: StockRow, rowMode: WriteMode): void {
    if (!canAdjust.value || readOnly.value) {
        return;
    }

    if (isActive(row, rowMode)) {
        closeForm();

        return;
    }

    form.reset();
    form.clearErrors();
    form.variant_id = row.variant_id;
    form.request_token = props.adjustment_token ?? '';
    activeRow.value = row;
    mode.value = rowMode;
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

    const url = mode.value === 'initial'
        ? `/inventory/stock/${props.branch.id}/initial`
        : `/inventory/stock/${props.branch.id}/adjustment`;

    if (mode.value === 'initial') {
        form.transform((data) => ({
            variant_id: data.variant_id,
            quantity: data.quantity,
        }));
    } else {
        form.transform((data) => ({
            variant_id: data.variant_id,
            quantity: data.quantity,
            reason_code_id: data.reason_code_id,
            note: data.note === '' ? null : data.note,
            request_token: data.request_token,
            authorizer_id: data.authorizer_id === '' ? null : data.authorizer_id,
            pin: data.pin === '' ? null : data.pin,
        }));
    }

    form.post(url, {
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

.sf-required {
    color: #b91c1c;
    font-weight: 700;
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

textarea.sf-input {
    resize: vertical;
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

.sf-actions-heading {
    width: 11rem;
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

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.sf-field-wide {
    grid-column: 1 / -1;
}

.sf-authorizer {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
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

.sf-link:disabled {
    color: #9ca3af;
    cursor: not-allowed;
    text-decoration: none;
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
