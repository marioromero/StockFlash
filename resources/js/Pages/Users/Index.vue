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
                    <h1 class="sf-title">Usuarios</h1>
                    <p class="sf-subtitle">
                        Administre las cuentas, el rol de acceso y las asignaciones por sucursal. Las cuentas desactivadas o eliminadas no pueden operar.
                    </p>
                </div>
                <Link href="/users/create" class="sf-button sf-button-primary">Nuevo usuario</Link>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>
            <div v-if="processing" class="sf-message sf-info" role="status">Procesando solicitud...</div>

            <section class="sf-card sf-filters">
                <form class="sf-search" @submit.prevent="applyFilters">
                    <label class="sf-label" for="user-search">Buscar por nombre o correo</label>
                    <div class="sf-search-row">
                        <input
                            id="user-search"
                            v-model="search"
                            type="search"
                            class="sf-input"
                            placeholder="Ej.: Ana o ana@empresa.cl"
                            maxlength="150"
                        />
                        <button type="submit" class="sf-button sf-button-primary">Buscar</button>
                        <button type="button" class="sf-button sf-button-secondary" :disabled="search === ''" @click="clearFilters">
                            Limpiar
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="users.data.length === 0 && !processing" class="sf-card sf-empty">
                <h2 class="sf-empty-title">No hay usuarios para mostrar</h2>
                <p class="sf-text">
                    {{ filters.search
                        ? 'No se encontraron coincidencias con la búsqueda. Pruebe con otro nombre o correo.'
                        : 'Cree el primer usuario para comenzar a operar.' }}
                </p>
                <Link href="/users/create" class="sf-button sf-button-primary">Crear usuario</Link>
            </section>

            <section v-else class="sf-card">
                <div class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Correo</th>
                                <th scope="col">Acceso</th>
                                <th scope="col">Rol principal</th>
                                <th scope="col">Sucursales</th>
                                <th scope="col">Estado</th>
                                <th scope="col">PIN</th>
                                <th scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id">
                                <td data-label="Nombre">
                                    <strong>{{ user.name }}</strong>
                                    <span v-if="isSelf(user)" class="sf-self">Usted</span>
                                </td>
                                <td data-label="Correo">{{ user.email }}</td>
                                <td data-label="Acceso">
                                    <span class="sf-badge" :class="user.access_type === 'global' ? 'sf-badge-global' : 'sf-badge-branch'">
                                        {{ user.access_type === 'global' ? 'Global' : 'Por sucursal' }}
                                    </span>
                                </td>
                                <td data-label="Rol principal">{{ user.primary_role?.name ?? '—' }}</td>
                                <td data-label="Sucursales">
                                    <template v-if="user.assignments.length > 0">
                                        {{ assignmentLabel(user) }}
                                    </template>
                                    <span v-else-if="user.access_type === 'branch'" class="sf-badge sf-badge-warning">Sin asignaciones</span>
                                    <span v-else class="sf-text-muted">Todas</span>
                                </td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="user.is_active ? 'sf-badge-active' : 'sf-badge-inactive'">
                                        {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td data-label="PIN">{{ user.pin_configured ? 'Configurado' : 'No configurado' }}</td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/users/${user.id}/edit`" class="sf-link">Editar</Link>
                                    <button
                                        v-if="user.is_active && !isSelf(user)"
                                        type="button"
                                        class="sf-link sf-link-warning"
                                        :disabled="processing"
                                        @click="deactivate(user)"
                                    >
                                        Desactivar
                                    </button>
                                    <button
                                        v-if="!user.is_active"
                                        type="button"
                                        class="sf-link sf-link-success"
                                        :disabled="processing"
                                        @click="activate(user)"
                                    >
                                        Activar
                                    </button>
                                    <button
                                        v-if="!isSelf(user)"
                                        type="button"
                                        class="sf-link sf-link-danger"
                                        :disabled="processing"
                                        @click="destroy(user)"
                                    >
                                        Eliminar
                                    </button>
                                    <span v-else class="sf-text-muted">Acciones propias bloqueadas</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="sf-pagination" aria-label="Paginación de usuarios">
                    <span class="sf-pagination-info">
                        {{ users.from ?? 0 }}–{{ users.to ?? 0 }} de {{ users.total }}
                    </span>
                    <div class="sf-pagination-links">
                        <template v-for="(link, index) in users.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="sf-page-link"
                                :class="{ 'sf-page-link-active': link.active }"
                                preserve-scroll
                            >
                                <span v-html="link.label"></span>
                            </Link>
                            <span v-else class="sf-page-link sf-page-link-disabled" v-html="link.label"></span>
                        </template>
                    </div>
                </nav>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface RoleRef {
    id: number;
    code: string;
    name: string;
}

interface BranchRef {
    id: number;
    code: string;
    name: string;
}

interface Assignment {
    branch_id: number;
    branch_code: string | null;
    branch_name: string | null;
    role_id: number;
    role_code: string | null;
    role_name: string | null;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    access_type: 'global' | 'branch';
    global_role: RoleRef | null;
    primary_role: RoleRef | null;
    default_branch: BranchRef | null;
    assignments: Assignment[];
    pin_configured: boolean;
    max_discount_percent: number | null;
    max_return_amount: number | null;
}

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginatorLink[];
}

interface Catalog {
    global_roles: RoleRef[];
    branch_roles: RoleRef[];
    branches: BranchRef[];
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { id?: number; name?: string } | null;
    };
    flash?: {
        success?: string | null;
        warning?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
}

const props = defineProps<{
    users: Paginator<UserRow>;
    filters: { search: string };
    catalogs: Catalog;
}>();

const page = usePage<SharedPageProps>();
const processing = ref(false);
const actionError = ref<string | null>(null);
const search = ref(props.filters.search ?? '');

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const errorMessage = computed(() => {
    if (actionError.value) {
        return actionError.value;
    }

    const validationErrors = page.props.errors ?? {};

    return Object.values(validationErrors)[0] ?? flash.value.error ?? null;
});

function isSelf(user: UserRow): boolean {
    return currentUserId.value !== null && currentUserId.value === user.id;
}

function assignmentLabel(user: UserRow): string {
    return user.assignments
        .map((assignment) => `${assignment.branch_code ?? assignment.branch_id} · ${assignment.role_name ?? assignment.role_code}`)
        .join(' | ');
}

function applyFilters(): void {
    router.get('/users', { search: search.value }, { preserveState: true, replace: true });
}

function clearFilters(): void {
    search.value = '';
    router.get('/users', {}, { preserveState: true, replace: true });
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

function deactivate(user: UserRow): void {
    if (!window.confirm(`¿Desactivar al usuario "${user.name}"? Su sesión se invalidará en la siguiente petición y no podrá iniciar sesión.`)) {
        return;
    }

    runAction('post', `/users/${user.id}/deactivate`);
}

function activate(user: UserRow): void {
    if (!window.confirm(`¿Activar al usuario "${user.name}"? Se verificará el límite de usuarios activos del plan.`)) {
        return;
    }

    runAction('post', `/users/${user.id}/activate`);
}

function destroy(user: UserRow): void {
    if (!window.confirm(`¿Eliminar al usuario "${user.name}"? Se aplicará un borrado lógico, la cuenta quedará inactiva y su correo seguirá reservado sin posibilidad de reutilizarlo.`)) {
        return;
    }

    runAction('delete', `/users/${user.id}`);
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
    max-width: 1280px;
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
    margin-bottom: 1rem;
}

.sf-filters {
    padding: 1rem 1.25rem;
}

.sf-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.35rem;
}

.sf-search-row {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.sf-input {
    flex: 1;
    min-width: 220px;
    padding: 0.55rem 0.7rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.95rem;
    background: #ffffff;
    color: #111827;
}

.sf-input:focus {
    border-color: #2563eb;
    outline: 2px solid #bfdbfe;
    outline-offset: 0;
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

.sf-text-muted {
    color: #6b7280;
    font-size: 0.8rem;
}

.sf-self {
    margin-left: 0.5rem;
    padding: 0.05rem 0.4rem;
    border-radius: 9999px;
    background: #e0e7ff;
    color: #3730a3;
    font-size: 0.7rem;
    font-weight: 600;
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
    white-space: nowrap;
}

.sf-badge-active {
    background: #dcfce7;
    color: #166534;
}

.sf-badge-inactive {
    background: #e5e7eb;
    color: #374151;
}

.sf-badge-global {
    background: #dbeafe;
    color: #1e40af;
}

.sf-badge-branch {
    background: #ede9fe;
    color: #5b21b6;
}

.sf-badge-warning {
    background: #fef3c7;
    color: #92400e;
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

.sf-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sf-button-primary {
    background: #2563eb;
    color: #ffffff;
}

.sf-button-primary:hover:not(:disabled) {
    background: #1d4ed8;
}

.sf-button-secondary {
    background: #e5e7eb;
    color: #111827;
}

.sf-button-secondary:hover:not(:disabled) {
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

.sf-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.sf-pagination-info {
    font-size: 0.8rem;
    color: #4b5563;
}

.sf-pagination-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.sf-page-link {
    display: inline-block;
    padding: 0.25rem 0.55rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.8rem;
    color: #1f2937;
    text-decoration: none;
}

.sf-page-link-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}

.sf-page-link-disabled {
    opacity: 0.45;
}

@media (max-width: 860px) {
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
