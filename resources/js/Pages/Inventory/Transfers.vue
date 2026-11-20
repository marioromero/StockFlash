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
                    <h1 class="sf-title">Traspasos entre sucursales</h1>
                    <p class="sf-subtitle">
                        Enviar descuenta la existencia física solo en la sucursal de origen; recibir la suma
                        solo en la sucursal de destino. Lo enviado y no recibido queda como diferencia
                        pendiente visible, sin ajustes ni reclamos automáticos.
                    </p>
                </div>
                <p class="sf-branch" aria-live="polite">
                    Sucursal: <strong>{{ branch.name }}</strong>
                    <span class="sf-text-muted">({{ branch.code }})</span>
                </p>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede consultar traspasos, pero no crear ni ejecutar transiciones.
            </div>
            <div v-if="!canViewInventory" class="sf-message sf-info" role="note">
                Su usuario no tiene permiso para consultar inventario en esta sucursal.
            </div>
            <div v-else-if="!canAdjust" class="sf-message sf-info" role="note">
                Puede consultar los traspasos de esta sucursal, pero no crear ni ejecutar transiciones.
            </div>

            <section class="sf-card" aria-labelledby="transfer-filters-title">
                <h2 id="transfer-filters-title" class="sf-section-title">Filtros</h2>
                <form class="sf-filters" role="search" @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="transfer-status">Estado</label>
                        <select id="transfer-status" v-model="filterStatus" class="sf-input">
                            <option value="">Todos</option>
                            <option v-for="status in statuses" :key="`filter-${status.value}`" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="transfer-number">Número</label>
                        <input
                            id="transfer-number"
                            v-model="filterNumber"
                            type="search"
                            maxlength="30"
                            class="sf-input"
                            placeholder="Ej.: SUC-A000001"
                        />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="transfer-per-page">Filas por página</label>
                        <select id="transfer-per-page" v-model.number="filterPerPage" class="sf-input">
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>

                    <div class="sf-form-actions sf-field-wide">
                        <button type="submit" class="sf-button sf-button-primary">Aplicar filtros</button>
                        <button type="button" class="sf-button sf-button-secondary" @click="clearFilters">
                            Limpiar
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="canAdjust && !readOnly" class="sf-card" aria-labelledby="transfer-create-title">
                <h2 id="transfer-create-title" class="sf-section-title">Nuevo borrador de traspaso</h2>
                <p class="sf-section-hint">
                    El número se asigna recién al solicitar el traspaso. Las líneas se resuelven por SKU a una
                    variante activa que controla existencias; no se admiten SKU repetidos.
                </p>

                <div v-if="destinationBranches.length === 0" class="sf-empty-text">
                    No hay otra sucursal activa disponible como destino.
                </div>

                <form v-else novalidate class="sf-form" @submit.prevent="submitCreate">
                    <div class="sf-form-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="transfer-destination">Sucursal de destino</label>
                            <select
                                id="transfer-destination"
                                v-model="form.to_branch_id"
                                class="sf-input"
                                :aria-describedby="form.errors.to_branch_id ? 'transfer-destination-error' : undefined"
                            >
                                <option value="">Seleccione una sucursal</option>
                                <option v-for="destination in destinationBranches" :key="`dest-${destination.id}`" :value="String(destination.id)">
                                    {{ destination.name }} ({{ destination.code }})
                                </option>
                            </select>
                            <p v-if="form.errors.to_branch_id" id="transfer-destination-error" class="sf-error" role="alert">
                                {{ form.errors.to_branch_id }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-wide">
                            <label class="sf-label" for="transfer-notes">Notas (opcional)</label>
                            <textarea
                                id="transfer-notes"
                                v-model="form.notes"
                                class="sf-input"
                                rows="2"
                                maxlength="500"
                                aria-describedby="transfer-notes-hint"
                            />
                            <p id="transfer-notes-hint" class="sf-text-muted">
                                Máximo 500 caracteres. No incluya datos personales ni información de pago.
                            </p>
                            <p v-if="form.errors.notes" class="sf-error" role="alert">{{ form.errors.notes }}</p>
                        </div>
                    </div>

                    <fieldset class="sf-lines">
                        <legend class="sf-label">Líneas del traspaso</legend>
                        <div v-for="(line, index) in form.lines" :key="`line-${index}`" class="sf-line-row">
                            <div class="sf-field">
                                <label class="sf-label" :for="`transfer-sku-${index}`">SKU</label>
                                <input
                                    :id="`transfer-sku-${index}`"
                                    v-model="line.sku"
                                    type="text"
                                    maxlength="100"
                                    autocomplete="off"
                                    class="sf-input"
                                />
                                <p v-if="lineError(index, 'sku')" class="sf-error" role="alert">
                                    {{ lineError(index, 'sku') }}
                                </p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" :for="`transfer-qty-${index}`">Cantidad solicitada</label>
                                <input
                                    :id="`transfer-qty-${index}`"
                                    v-model="line.quantity"
                                    type="text"
                                    inputmode="decimal"
                                    maxlength="20"
                                    autocomplete="off"
                                    class="sf-input"
                                />
                                <p v-if="lineError(index, 'quantity')" class="sf-error" role="alert">
                                    {{ lineError(index, 'quantity') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="sf-button sf-button-secondary sf-line-remove"
                                :disabled="form.lines.length <= 1"
                                @click="removeLine(index)"
                            >
                                Quitar
                            </button>
                        </div>

                        <p v-if="form.errors.lines" class="sf-error" role="alert">{{ form.errors.lines }}</p>
                        <p v-if="duplicateSku" class="sf-error" role="alert">
                            Hay un SKU repetido (sin distinguir mayúsculas). Corrija las líneas antes de guardar.
                        </p>
                        <p v-if="createError" class="sf-error" role="alert">{{ createError }}</p>

                        <button type="button" class="sf-button sf-button-secondary" :disabled="form.lines.length >= 100" @click="addLine">
                            Agregar línea
                        </button>
                    </fieldset>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing || duplicateSku">
                            Guardar borrador
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card" aria-labelledby="transfer-list-title">
                <h2 id="transfer-list-title" class="sf-section-title">Traspasos de la sucursal</h2>
                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ transfers.total }} traspaso(s) encontrado(s).
                    <template v-if="transfers.from !== null && transfers.to !== null">
                        Mostrando {{ transfers.from }}–{{ transfers.to }}.
                    </template>
                </p>

                <div v-if="transfers.data.length === 0" class="sf-empty-text">
                    No hay traspasos que coincidan con los filtros en esta sucursal.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Número</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Origen → destino</th>
                                <th scope="col">Solicitado</th>
                                <th scope="col">Enviado</th>
                                <th scope="col">Pendiente</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="transfer in transfers.data" :key="`transfer-${transfer.id}`">
                                <td data-label="Número">
                                    <Link :href="transferUrl(transfer.id)" class="sf-link">
                                        {{ transfer.number ?? 'Borrador' }}
                                    </Link>
                                </td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="badgeClass(transfer.status)">{{ transfer.status_label }}</span>
                                </td>
                                <td data-label="Origen → destino">
                                    <span :class="{ 'sf-own-branch': transfer.is_origin }">{{ transfer.from_branch.name }}</span>
                                    →
                                    <span :class="{ 'sf-own-branch': transfer.is_destination }">{{ transfer.to_branch.name }}</span>
                                </td>
                                <td data-label="Solicitado">{{ transfer.requested_total }}</td>
                                <td data-label="Enviado">{{ transfer.shipped_total }}</td>
                                <td data-label="Pendiente">{{ transfer.pending_total }}</td>
                                <td data-label="Acciones">
                                    <Link :href="transferUrl(transfer.id)" class="sf-link">Ver detalle</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="transfers.last_page > 1" class="sf-pagination" aria-label="Paginación de traspasos">
                    <template v-for="link in transfers.links" :key="`page-${link.label}`">
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

interface BranchOption {
    id: number;
    code: string;
    name: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface StatusOption {
    value: string;
    label: string;
}

interface TransferRow {
    id: number;
    number: string | null;
    status: string;
    status_label: string;
    from_branch: BranchOption;
    to_branch: BranchOption;
    is_origin: boolean;
    is_destination: boolean;
    line_count: number;
    requested_total: string;
    shipped_total: string;
    received_total: string;
    pending_total: string;
    requested_at: string | null;
    shipped_at: string | null;
    received_at: string | null;
}

interface TransferPage {
    data: TransferRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface TransferLineInput {
    sku: string;
    quantity: string;
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
    branch: BranchOption;
    transfers: TransferPage;
    filters: { status: string | null; number: string; per_page: number };
    statuses: StatusOption[];
    destination_branches: BranchOption[];
    can_view_inventory: boolean;
    can_adjust: boolean;
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const filterStatus = ref(props.filters.status ?? '');
const filterNumber = ref(props.filters.number ?? '');
const filterPerPage = ref(props.filters.per_page ?? 25);
const createError = ref('');

const form = useForm<{
    to_branch_id: string;
    notes: string;
    lines: TransferLineInput[];
}>({
    to_branch_id: '',
    notes: '',
    lines: [{ sku: '', quantity: '' }],
});

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const destinationBranches = computed(() => props.destination_branches ?? []);
const statuses = computed(() => props.statuses ?? []);
const canViewInventory = computed(() => props.can_view_inventory === true);
const canAdjust = computed(() => props.can_adjust === true);
const readOnly = computed(() => props.read_only === true);

const duplicateSku = computed(() => {
    const seen = new Set<string>();

    for (const line of form.lines) {
        const key = line.sku.trim().toLowerCase();

        if (key === '') {
            continue;
        }

        if (seen.has(key)) {
            return true;
        }

        seen.add(key);
    }

    return false;
});

watch(
    () => props.filters,
    (value) => {
        filterStatus.value = value.status ?? '';
        filterNumber.value = value.number ?? '';
        filterPerPage.value = value.per_page ?? 25;
    },
    { deep: true },
);

function transferUrl(id: number): string {
    return `/inventory/stock/${props.branch.id}/transfers/${id}`;
}

function badgeClass(status: string): string {
    return `sf-badge-${status}`;
}

function addLine(): void {
    if (form.lines.length >= 100) {
        return;
    }

    form.lines.push({ sku: '', quantity: '' });
}

function removeLine(index: number): void {
    if (form.lines.length <= 1) {
        return;
    }

    form.lines.splice(index, 1);
}

function lineError(index: number, field: 'sku' | 'quantity'): string | null {
    const errors = form.errors as Record<string, string>;

    return errors[`lines.${index}.${field}`] ?? null;
}

function submitCreate(): void {
    form.clearErrors();
    createError.value = '';

    if (form.to_branch_id === '') {
        createError.value = 'Debe seleccionar la sucursal de destino.';

        return;
    }

    if (duplicateSku.value) {
        createError.value = 'Hay un SKU repetido (sin distinguir mayúsculas).';

        return;
    }

    form.transform((data) => ({
        to_branch_id: Number(data.to_branch_id),
        notes: data.notes.trim() === '' ? null : data.notes,
        lines: data.lines.map((line) => ({
            sku: line.sku.trim(),
            quantity: line.quantity.trim(),
        })),
    }));

    form.post(`/inventory/stock/${props.branch.id}/transfers`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            createError.value = '';
        },
    });
}

function applyFilters(): void {
    router.get(
        `/inventory/stock/${props.branch.id}/transfers`,
        {
            status: filterStatus.value === '' ? undefined : filterStatus.value,
            number: filterNumber.value.trim() === '' ? undefined : filterNumber.value.trim(),
            per_page: filterPerPage.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters(): void {
    filterStatus.value = '';
    filterNumber.value = '';
    filterPerPage.value = 25;
    applyFilters();
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

.sf-filters,
.sf-form {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    margin-top: 0.85rem;
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

textarea.sf-input {
    resize: vertical;
}

.sf-lines {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.sf-line-row {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) minmax(140px, 1fr) auto;
    gap: 0.65rem;
    align-items: end;
}

.sf-line-remove {
    align-self: end;
}

.sf-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
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

.sf-own-branch {
    font-weight: 700;
}

.sf-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    background: #e5e7eb;
    color: #374151;
}

.sf-badge-draft {
    background: #f3f4f6;
    color: #4b5563;
}

.sf-badge-requested {
    background: #dbeafe;
    color: #1e40af;
}

.sf-badge-in_transit {
    background: #fef3c7;
    color: #92400e;
}

.sf-badge-received {
    background: #dcfce7;
    color: #166534;
}

.sf-badge-partially_received {
    background: #ffedd5;
    color: #9a3412;
}

.sf-badge-cancelled {
    background: #fee2e2;
    color: #991b1b;
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

    .sf-card {
        padding: 1rem;
    }

    .sf-line-row {
        grid-template-columns: 1fr;
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
