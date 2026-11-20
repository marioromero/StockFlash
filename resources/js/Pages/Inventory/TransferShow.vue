<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link :href="`/inventory/stock/${branch.id}/transfers`" class="sf-button sf-button-secondary">
                    Volver a traspasos
                </Link>
                <Link :href="`/inventory/stock/${branch.id}`" class="sf-button sf-button-secondary">
                    Volver al inventario
                </Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">
                        Traspaso {{ transfer.number ?? 'Borrador' }}
                        <span class="sf-badge" :class="badgeClass(transfer.status)">{{ transfer.status_label }}</span>
                    </h1>
                    <p class="sf-subtitle">
                        {{ transfer.from_branch.name }} ({{ transfer.from_branch.code }})
                        → {{ transfer.to_branch.name }} ({{ transfer.to_branch.code }})
                    </p>
                </div>
                <p class="sf-branch" aria-live="polite">
                    Sucursal actual: <strong>{{ branch.name }}</strong>
                    <span class="sf-text-muted">({{ branch.code }})</span>
                </p>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede consultar el traspaso, pero no ejecutar transiciones.
            </div>
            <div v-if="!canAdjust" class="sf-message sf-info" role="note">
                Puede consultar este traspaso, pero no ejecutar transiciones.
            </div>
            <div v-if="sharedErrors.length > 0" class="sf-message sf-error" role="alert">
                <p>No fue posible completar la acción:</p>
                <ul class="sf-error-list">
                    <li v-for="message in sharedErrors" :key="message">{{ message }}</li>
                </ul>
            </div>

            <section class="sf-card" aria-labelledby="transfer-summary-title">
                <h2 id="transfer-summary-title" class="sf-section-title">Resumen</h2>
                <dl class="sf-summary">
                    <div>
                        <dt>Origen</dt>
                        <dd>{{ transfer.from_branch.name }} ({{ transfer.from_branch.code }})</dd>
                    </div>
                    <div>
                        <dt>Destino</dt>
                        <dd>{{ transfer.to_branch.name }} ({{ transfer.to_branch.code }})</dd>
                    </div>
                    <div>
                        <dt>Solicitado</dt>
                        <dd>{{ transfer.requested_at ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Enviado</dt>
                        <dd>{{ transfer.shipped_at ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Recibido</dt>
                        <dd>{{ transfer.received_at ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Líneas</dt>
                        <dd>{{ transfer.line_count }}</dd>
                    </div>
                    <div>
                        <dt>Total solicitado</dt>
                        <dd>{{ transfer.requested_total }}</dd>
                    </div>
                    <div>
                        <dt>Total enviado</dt>
                        <dd>{{ transfer.shipped_total }}</dd>
                    </div>
                    <div>
                        <dt>Total recibido</dt>
                        <dd>{{ transfer.received_total }}</dd>
                    </div>
                    <div>
                        <dt>Diferencia pendiente</dt>
                        <dd><strong>{{ transfer.pending_total }}</strong></dd>
                    </div>
                </dl>
                <p v-if="transfer.notes" class="sf-notes"><strong>Notas:</strong> {{ transfer.notes }}</p>
            </section>

            <section class="sf-card" aria-labelledby="transfer-actions-title">
                <h2 id="transfer-actions-title" class="sf-section-title">Acciones</h2>
                <p class="sf-section-hint">
                    Las acciones se habilitan según el estado del traspaso y la sucursal actual. El servidor
                    revalida sucursal, estado, permiso y licencia en cada operación.
                </p>

                <div v-if="hasAnyAction" class="sf-form-actions">
                    <button
                        v-if="transfer.actions.request"
                        type="button"
                        class="sf-button sf-button-primary"
                        :disabled="processing"
                        @click="runAction('request')"
                    >
                        Solicitar traspaso
                    </button>
                    <button
                        v-if="transfer.actions.dispatch"
                        type="button"
                        class="sf-button sf-button-primary"
                        :disabled="processing"
                        @click="runAction('dispatch')"
                    >
                        Enviar traspaso
                    </button>
                    <button
                        v-if="transfer.actions.cancel"
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="processing"
                        @click="runAction('cancel')"
                    >
                        Cancelar traspaso
                    </button>
                </div>

                <p v-if="!hasAnyAction" class="sf-text-muted">
                    No hay acciones disponibles para su usuario en esta sucursal y estado.
                </p>

                <p v-if="transfer.status === 'partially_received'" class="sf-message sf-warning" role="status">
                    La diferencia pendiente no se acredita automáticamente a ninguna sucursal. Requiere una
                    resolución posterior separada; esta pantalla no genera ajustes, reversas ni reclamos.
                </p>
                <p v-if="transfer.status === 'received'" class="sf-message sf-success" role="status">
                    Todas las cantidades enviadas fueron recibidas en el destino.
                </p>
                <p v-if="transfer.status === 'cancelled'" class="sf-message sf-info" role="status">
                    El traspaso fue cancelado sin movimientos de existencia.
                </p>
            </section>

            <section
                v-if="transfer.actions.receive"
                class="sf-card"
                aria-labelledby="transfer-receive-title"
            >
                <h2 id="transfer-receive-title" class="sf-section-title">Registrar recepción</h2>
                <p class="sf-section-hint">
                    Declare la cantidad realmente recibida por línea; no puede superar la cantidad enviada.
                    Solo las cantidades positivas suman existencia en esta sucursal.
                </p>
                <p class="sf-message sf-warning" role="note">
                    Sin disposición automática de faltantes: lo enviado y no recibido queda pendiente y visible.
                </p>

                <form novalidate class="sf-form" @submit.prevent="submitReceive">
                    <div class="sf-table-wrap">
                        <table class="sf-table">
                            <thead>
                                <tr>
                                    <th scope="col">Producto</th>
                                    <th scope="col">SKU</th>
                                    <th scope="col">Enviado</th>
                                    <th scope="col">Cantidad recibida</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in receiveForm.lines" :key="`receive-${line.item_id}`">
                                    <td data-label="Producto">{{ line.product_name }}</td>
                                    <td data-label="SKU">{{ line.sku }}</td>
                                    <td data-label="Enviado">{{ line.shipped_qty }}</td>
                                    <td data-label="Cantidad recibida">
                                        <label class="sf-visually-hidden" :for="`receive-qty-${index}`">
                                            Cantidad recibida para {{ line.sku }}
                                        </label>
                                        <input
                                            :id="`receive-qty-${index}`"
                                            v-model="receiveForm.lines[index].received_qty"
                                            type="text"
                                            inputmode="decimal"
                                            maxlength="20"
                                            autocomplete="off"
                                            class="sf-input"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="receiveForm.errors.lines" class="sf-error" role="alert">{{ receiveForm.errors.lines }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="receiveForm.processing">
                            Registrar recepción
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card" aria-labelledby="transfer-lines-title">
                <h2 id="transfer-lines-title" class="sf-section-title">Líneas del traspaso</h2>
                <div class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Solicitado</th>
                                <th scope="col">Enviado</th>
                                <th scope="col">Recibido</th>
                                <th scope="col">Pendiente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in transfer.lines" :key="`line-${line.id}`">
                                <td data-label="Producto">{{ line.product_name }}</td>
                                <td data-label="SKU">{{ line.sku }}</td>
                                <td data-label="Solicitado">{{ line.requested_qty }}</td>
                                <td data-label="Enviado">{{ line.shipped_qty }}</td>
                                <td data-label="Recibido">{{ line.received_qty }}</td>
                                <td data-label="Pendiente"><strong>{{ line.pending_qty }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

interface BranchOption {
    id: number;
    code: string;
    name: string;
}

interface TransferLine {
    id: number;
    variant_id: number;
    sku: string;
    product_name: string;
    unit: string;
    requested_qty: string;
    shipped_qty: string;
    received_qty: string;
    pending_qty: string;
}

interface TransferActions {
    request: boolean;
    dispatch: boolean;
    cancel: boolean;
    receive: boolean;
}

interface TransferDetail {
    id: number;
    number: string | null;
    status: string;
    status_label: string;
    from_branch: BranchOption;
    to_branch: BranchOption;
    notes: string | null;
    is_origin: boolean;
    is_destination: boolean;
    requested_at: string | null;
    shipped_at: string | null;
    received_at: string | null;
    line_count: number;
    requested_total: string;
    shipped_total: string;
    received_total: string;
    pending_total: string;
    lines: TransferLine[];
    actions: TransferActions;
}

interface ReceiveLineInput {
    item_id: number;
    received_qty: string;
    sku: string;
    product_name: string;
    shipped_qty: string;
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
    errors?: Record<string, string>;
}

const props = defineProps<{
    branch: BranchOption;
    transfer: TransferDetail;
    can_view_inventory: boolean;
    can_adjust: boolean;
    read_only: boolean;
}>();

type ActionName = 'request' | 'dispatch' | 'cancel';

const page = usePage<SharedPageProps>();
const processing = ref(false);

const receiveForm = useForm<{ lines: ReceiveLineInput[] }>({
    lines: props.transfer.lines.map((line) => ({
        item_id: line.id,
        received_qty: line.shipped_qty,
        sku: line.sku,
        product_name: line.product_name,
        shipped_qty: line.shipped_qty,
    })),
});

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const canAdjust = computed(() => props.can_adjust === true);
const readOnly = computed(() => props.read_only === true);
const hasAnyAction = computed(
    () => props.transfer.actions.request
        || props.transfer.actions.dispatch
        || props.transfer.actions.cancel
        || props.transfer.actions.receive,
);
const sharedErrors = computed<string[]>(() => {
    const errors = (page.props.errors ?? {}) as Record<string, unknown>;

    return Object.values(errors).filter(
        (message): message is string => typeof message === 'string' && message !== '',
    );
});

function badgeClass(status: string): string {
    return `sf-badge-${status}`;
}

function actionUrl(action: ActionName): string {
    return `/inventory/stock/${props.branch.id}/transfers/${props.transfer.id}/${action}`;
}

function runAction(action: ActionName): void {
    if (processing.value) {
        return;
    }

    if (action === 'dispatch') {
        const confirmed = window.confirm(
            'Al enviar, la existencia se descontará de la sucursal de origen. ¿Desea continuar?',
        );

        if (!confirmed) {
            return;
        }
    }

    if (action === 'cancel') {
        const confirmed = window.confirm(
            'El traspaso quedará cancelado sin movimientos de existencia. ¿Desea continuar?',
        );

        if (!confirmed) {
            return;
        }
    }

    processing.value = true;

    router.post(actionUrl(action), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function submitReceive(): void {
    receiveForm.clearErrors();
    receiveForm.transform((data) => ({
        lines: data.lines.map((line) => ({
            item_id: line.item_id,
            received_qty: line.received_qty.trim(),
        })),
    }));
    receiveForm.post(`/inventory/stock/${props.branch.id}/transfers/${props.transfer.id}/receive`, {
        preserveScroll: true,
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
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.sf-subtitle {
    margin-top: 0.35rem;
    font-size: 0.9rem;
    color: #4b5563;
    line-height: 1.5;
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

.sf-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.85rem;
    margin: 0.85rem 0 0;
}

.sf-summary dt {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
}

.sf-summary dd {
    margin: 0.15rem 0 0;
    font-size: 0.9rem;
    color: #111827;
}

.sf-notes {
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid #e5e7eb;
    font-size: 0.875rem;
    color: #374151;
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    margin-top: 0.85rem;
}

.sf-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    margin-top: 0.85rem;
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

.sf-visually-hidden {
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

.sf-error-list {
    margin: 0.35rem 0 0;
    padding-left: 1.2rem;
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
