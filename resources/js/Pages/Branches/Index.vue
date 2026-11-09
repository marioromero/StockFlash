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
                    <h1 class="sf-title">Sucursales</h1>
                    <p class="sf-subtitle">
                        Administre las sucursales de la instalación. Las sucursales desactivadas no permiten nuevas operaciones.
                    </p>
                </div>
                <Link href="/branches/create" class="sf-button sf-button-primary">Nueva sucursal</Link>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>
            <div v-if="processing" class="sf-message sf-info" role="status">Procesando solicitud...</div>

            <section v-if="branches.length === 0 && !processing" class="sf-card sf-empty">
                <h2 class="sf-empty-title">Aún no hay sucursales</h2>
                <p class="sf-text">
                    Cree la primera sucursal para comenzar a operar. Puede registrar un local, una bodega o una sucursal online.
                </p>
                <Link href="/branches/create" class="sf-button sf-button-primary">Crear primera sucursal</Link>
            </section>

            <section v-else class="sf-card">
                <div class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Comuna / Ciudad</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Control de caja</th>
                                <th scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="branch in branches" :key="branch.id">
                                <td data-label="Código"><strong>{{ branch.code }}</strong></td>
                                <td data-label="Nombre">{{ branch.name }}</td>
                                <td data-label="Tipo">{{ typeLabel(branch.type) }}</td>
                                <td data-label="Comuna / Ciudad">{{ locationLabel(branch) }}</td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="branch.is_active ? 'sf-badge-active' : 'sf-badge-inactive'">
                                        {{ branch.is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td data-label="Control de caja">{{ branch.requires_cash_control ? 'Sí' : 'No' }}</td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/branches/${branch.id}/edit`" class="sf-link">Editar</Link>
                                    <button
                                        v-if="branch.is_active"
                                        type="button"
                                        class="sf-link sf-link-warning"
                                        :disabled="processing"
                                        @click="deactivate(branch)"
                                    >
                                        Desactivar
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="sf-link sf-link-success"
                                        :disabled="processing"
                                        @click="activate(branch)"
                                    >
                                        Activar
                                    </button>
                                    <button
                                        type="button"
                                        class="sf-link sf-link-danger"
                                        :disabled="processing"
                                        @click="destroy(branch)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
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
import { Link, router, usePage } from '@inertiajs/vue3';

interface BranchRow {
    id: number;
    code: string;
    name: string;
    type: 'store' | 'warehouse' | 'online' | string;
    commune: string | null;
    city: string | null;
    is_active: boolean;
    requires_cash_control: boolean;
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
    branches: BranchRow[];
    active_count: number;
}>();

const page = usePage<SharedPageProps>();
const processing = ref(false);
const actionError = ref<string | null>(null);

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => {
    if (actionError.value) {
        return actionError.value;
    }

    const validationErrors = page.props.errors ?? {};

    return Object.values(validationErrors)[0] ?? flash.value.error ?? null;
});

function typeLabel(type: string): string {
    const labels: Record<string, string> = {
        store: 'Local',
        warehouse: 'Bodega',
        online: 'Online',
    };

    return labels[type] ?? type;
}

function locationLabel(branch: BranchRow): string {
    const parts = [branch.commune, branch.city].filter((part): part is string => Boolean(part));

    return parts.length > 0 ? parts.join(' / ') : '—';
}

function runAction(method: 'post' | 'delete', url: string): void {
    actionError.value = '';
    processing.value = true;

    const options = {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
        onError: (errors: Record<string, string>) => {
            actionError.value = Object.values(errors)[0] ?? 'No se pudo completar la operación.';
        },
    };

    if (method === 'post') {
        router.post(url, {}, options);
    } else {
        router.delete(url, options);
    }
}

function deactivate(branch: BranchRow): void {
    const isLastActive = props.active_count <= 1;
    const message = isLastActive
        ? `ADVERTENCIA: "${branch.name}" es la última sucursal activa. Si la desactiva, las operaciones que requieren sucursal responderán 403 hasta que active otra. El dashboard y esta administración seguirán disponibles. ¿Desea continuar?`
        : `¿Desactivar la sucursal "${branch.name}"? No se borrarán sus datos y podrá reactivarla más adelante.`;

    if (!window.confirm(message)) {
        return;
    }

    runAction('post', `/branches/${branch.id}/deactivate`);
}

function activate(branch: BranchRow): void {
    if (!window.confirm(`¿Activar la sucursal "${branch.name}"?`)) {
        return;
    }

    runAction('post', `/branches/${branch.id}/activate`);
}

function destroy(branch: BranchRow): void {
    if (!window.confirm(`¿Eliminar la sucursal "${branch.name}"? Se aplicará un borrado lógico, se conservará su historia y su código quedará reservado sin posibilidad de reutilizarlo.`)) {
        return;
    }

    runAction('delete', `/branches/${branch.id}`);
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
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.sf-titlebar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
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
    max-width: 70ch;
}

.sf-card {
    background: #ffffff;
    padding: 1.25rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sf-empty {
    text-align: center;
    padding: 3rem 1.5rem;
}

.sf-empty-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.5rem;
}

.sf-text {
    color: #4b5563;
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.sf-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.sf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.sf-table th,
.sf-table td {
    padding: 0.75rem 0.65rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.sf-table th {
    color: #374151;
    font-weight: 600;
    background: #f9fafb;
    white-space: nowrap;
}

.sf-actions-heading,
.sf-actions-cell {
    text-align: right;
    white-space: nowrap;
}

.sf-actions-cell {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.sf-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.sf-badge-active {
    background: #dcfce7;
    color: #166534;
}

.sf-badge-inactive {
    background: #e5e7eb;
    color: #374151;
}

.sf-link {
    border: none;
    background: none;
    padding: 0;
    font: inherit;
    color: #2563eb;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
}

.sf-link:hover:not(:disabled) {
    text-decoration: underline;
}

.sf-link:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sf-link-warning {
    color: #b45309;
}

.sf-link-success {
    color: #15803d;
}

.sf-link-danger {
    color: #b91c1c;
}

.sf-button {
    display: inline-block;
    padding: 0.55rem 0.95rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
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
    margin-bottom: 1rem;
}

.sf-success {
    background: #dcfce7;
    color: #166534;
}

.sf-warning {
    background: #fef3c7;
    color: #92400e;
}

.sf-error {
    background: #fee2e2;
    color: #991b1b;
}

.sf-info {
    background: #dbeafe;
    color: #1e40af;
}

@media (max-width: 760px) {
    .sf-header {
        padding: 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .sf-user {
        width: 100%;
        justify-content: space-between;
    }

    .sf-main {
        padding: 1rem;
    }

    .sf-titlebar {
        flex-direction: column;
    }

    .sf-titlebar .sf-button {
        width: 100%;
    }

    .sf-table,
    .sf-table thead,
    .sf-table tbody,
    .sf-table tr,
    .sf-table th,
    .sf-table td {
        display: block;
    }

    .sf-table thead {
        display: none;
    }

    .sf-table tr {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        margin-bottom: 0.75rem;
        padding: 0.5rem 0.75rem;
    }

    .sf-table td {
        border-bottom: 1px dashed #e5e7eb;
        padding: 0.5rem 0;
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        text-align: right;
    }

    .sf-table td:last-child {
        border-bottom: none;
    }

    .sf-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #374151;
        text-align: left;
    }

    .sf-actions-cell {
        justify-content: flex-end;
    }
}
</style>
