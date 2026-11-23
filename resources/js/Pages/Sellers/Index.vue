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
                    <h1 class="sf-title">Vendedores</h1>
                    <p class="sf-subtitle">
                        Registro de vendedores con código único, usuario vinculado opcional, comisión configurada y
                        sucursales asignadas. El código identifica al vendedor; no es una credencial de acceso.
                    </p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede consultar vendedores, pero no crear ni modificar registros.
            </div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="seller-filters-title">
                <h2 id="seller-filters-title" class="sf-section-title">Filtros</h2>
                <form class="sf-filters" role="search" novalidate @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="seller-q">Buscar por nombre o código</label>
                        <input id="seller-q" v-model="searchTerm" type="search" maxlength="100" class="sf-input" />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="seller-branch-filter">Sucursal</label>
                        <select id="seller-branch-filter" v-model="branchTerm" class="sf-input">
                            <option value="">Todas las permitidas</option>
                            <option v-for="branch in branchOptions" :key="branch.id" :value="String(branch.id)">
                                {{ branch.code }} · {{ branch.name }}
                            </option>
                        </select>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="seller-per-page">Filas por página</label>
                        <select id="seller-per-page" v-model="perPageTerm" class="sf-input">
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

            <section class="sf-card" aria-labelledby="seller-list-title">
                <h2 id="seller-list-title" class="sf-section-title">Listado</h2>
                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ sellers.total }} vendedor(es). Mostrando {{ sellers.from ?? 0 }}–{{ sellers.to ?? 0 }}.
                </p>

                <div v-if="sellers.data.length === 0" class="sf-empty-text">
                    No hay vendedores que coincidan con los filtros.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">Vendedores registrados con sus sucursales autorizadas.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Usuario vinculado</th>
                                <th scope="col">Comisión</th>
                                <th scope="col">Sucursales</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="seller in sellers.data" :key="seller.id">
                                <td data-label="Código"><strong>{{ seller.code }}</strong></td>
                                <td data-label="Nombre">{{ seller.name }}</td>
                                <td data-label="Usuario vinculado">{{ seller.user?.name ?? 'Sin usuario' }}</td>
                                <td data-label="Comisión">{{ seller.commission_percent }}%</td>
                                <td data-label="Sucursales">
                                    <span v-if="seller.branches.length === 0">Sin sucursales visibles</span>
                                    <span v-else>
                                        {{ seller.branches.map((branch) => branch.name).join(', ') }}
                                    </span>
                                </td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="statusClass(seller)">
                                        {{ statusLabel(seller) }}
                                    </span>
                                </td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/sellers/${seller.id}`" class="sf-link">Ver / editar</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="sf-pagination" aria-label="Paginación de vendedores">
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="sellers.current_page <= 1"
                        @click="goToPage(sellers.current_page - 1)"
                    >
                        Anterior
                    </button>
                    <span class="sf-text-muted">Página {{ sellers.current_page }} de {{ sellers.last_page }}</span>
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="sellers.current_page >= sellers.last_page"
                        @click="goToPage(sellers.current_page + 1)"
                    >
                        Siguiente
                    </button>
                </nav>
            </section>

            <section v-if="!readOnly" class="sf-card" aria-labelledby="seller-create-title">
                <h2 id="seller-create-title" class="sf-section-title">Nuevo vendedor</h2>
                <p class="sf-section-hint">
                    El código se guarda en mayúsculas y no se puede cambiar después. Seleccione al menos una sucursal
                    activa dentro de su alcance. Si vincula un usuario por sucursal, debe tener acceso a todas las
                    sucursales asignadas.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submitCreate">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="create-seller-code">Código *</label>
                            <input
                                id="create-seller-code"
                                v-model="form.code"
                                type="text"
                                maxlength="20"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.code }"
                                required
                                autocomplete="off"
                            />
                            <p v-if="form.errors.code" class="sf-error-text">{{ form.errors.code }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-seller-name">Nombre *</label>
                            <input
                                id="create-seller-name"
                                v-model="form.name"
                                type="text"
                                maxlength="150"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="sf-error-text">{{ form.errors.name }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-seller-user">Usuario vinculado (opcional)</label>
                            <select id="create-seller-user" v-model="form.user_id" class="sf-input">
                                <option value="">Sin usuario</option>
                                <option v-for="user in userOptions" :key="user.id" :value="String(user.id)">
                                    {{ user.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.user_id" class="sf-error-text">{{ form.errors.user_id }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-seller-commission">Comisión (%)</label>
                            <input
                                id="create-seller-commission"
                                v-model="form.commission_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.commission_percent }"
                                placeholder="0.00"
                            />
                            <p v-if="form.errors.commission_percent" class="sf-error-text">
                                {{ form.errors.commission_percent }}
                            </p>
                        </div>
                    </div>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Sucursales *</legend>
                        <div v-if="branchOptions.length === 0" class="sf-empty-text">
                            No hay sucursales activas dentro de su alcance.
                        </div>
                        <label v-for="branch in branchOptions" :key="branch.id" class="sf-check">
                            <input v-model="form.branches" type="checkbox" :value="branch.id" />
                            <span>{{ branch.code }} · {{ branch.name }}</span>
                        </label>
                        <p v-if="form.errors.branches" class="sf-error-text">{{ form.errors.branches }}</p>
                    </fieldset>

                    <p v-if="form.errors.read_only" class="sf-error-text" role="alert">{{ form.errors.read_only }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                            Registrar vendedor
                        </button>
                    </div>
                </form>
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

interface UserOption {
    id: number;
    name: string;
}

interface SellerRow {
    id: number;
    code: string;
    name: string;
    commission_percent: string;
    is_active: boolean;
    is_anonymized: boolean;
    user: UserOption | null;
    branches: BranchOption[];
}

interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: { user?: { name?: string } | null };
    flash?: { success?: string | null; warning?: string | null; error?: string | null };
    errors?: Record<string, string>;
}

const props = defineProps<{
    sellers: Paginated<SellerRow>;
    filters: { q: string; branch_id: number | null };
    branch_options: BranchOption[];
    user_options: UserOption[];
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => Object.values(page.props.errors ?? {})[0] ?? flash.value.error ?? null);
const readOnly = computed(() => props.read_only === true);
const branchOptions = computed(() => props.branch_options);
const userOptions = computed(() => props.user_options);

const searchTerm = ref(props.filters.q ?? '');
const branchTerm = ref(props.filters.branch_id !== null ? String(props.filters.branch_id) : '');
const perPageTerm = ref(String(props.sellers.per_page ?? 25));

const form = useForm({
    code: '',
    name: '',
    user_id: '',
    commission_percent: '0',
    branches: [] as number[],
    read_only: '',
});

function statusLabel(seller: SellerRow): string {
    if (seller.is_anonymized) {
        return 'Anonimizado';
    }

    return seller.is_active ? 'Activo' : 'Inactivo';
}

function statusClass(seller: SellerRow): string {
    return seller.is_anonymized || !seller.is_active ? 'sf-badge-inactive' : 'sf-badge-active';
}

function filterParams(pageNumber?: number): Record<string, string> {
    const params: Record<string, string> = {
        per_page: perPageTerm.value,
    };

    if (searchTerm.value.trim() !== '') {
        params.q = searchTerm.value.trim();
    }

    if (branchTerm.value !== '') {
        params.branch_id = branchTerm.value;
    }

    if (pageNumber !== undefined) {
        params.page = String(pageNumber);
    }

    return params;
}

function applyFilters(): void {
    router.get('/sellers', filterParams(), { preserveState: true, replace: true });
}

function resetFilters(): void {
    searchTerm.value = '';
    branchTerm.value = '';
    perPageTerm.value = '25';
    router.get('/sellers', { per_page: '25' }, { preserveState: true, replace: true });
}

function goToPage(pageNumber: number): void {
    if (pageNumber < 1 || pageNumber > props.sellers.last_page) {
        return;
    }

    router.get('/sellers', filterParams(pageNumber), { preserveScroll: true, replace: true });
}

function submitCreate(): void {
    form.transform((data) => ({
        ...data,
        user_id: data.user_id === '' ? null : Number(data.user_id),
        commission_percent: data.commission_percent.trim() === '' ? null : data.commission_percent.trim(),
        branches: data.branches,
    })).post('/sellers', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<style scoped>
.sf-page { min-height: 100vh; background: #f3f4f6; }
.sf-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #fff; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap; gap: 0.75rem; }
.sf-brand { font-size: 1.5rem; font-weight: 800; }
.sf-brand .stock { color: #0f172a; }
.sf-brand .flash { color: #2563eb; }
.sf-user { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.sf-user-name { font-size: 0.875rem; color: #374151; }
.sf-main { padding: 2rem; display: flex; flex-direction: column; gap: 1rem; }
.sf-titlebar { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.sf-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.sf-subtitle, .sf-section-hint, .sf-text-muted { font-size: 0.875rem; color: #4b5563; margin: 0.35rem 0 0; }
.sf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; }
.sf-section-title { font-size: 1.125rem; font-weight: 700; color: #111827; margin: 0 0 0.75rem; }
.sf-filters, .sf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem; }
.sf-field-wide { grid-column: 1 / -1; }
.sf-field { display: flex; flex-direction: column; gap: 0.25rem; }
.sf-label { font-size: 0.8125rem; font-weight: 600; color: #374151; }
.sf-input { width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.875rem; background: #fff; }
.sf-input-error { border-color: #dc2626; }
.sf-form-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; }
.sf-button { display: inline-block; padding: 0.55rem 1rem; border: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; }
.sf-button:disabled { opacity: 0.55; cursor: not-allowed; }
.sf-button-primary { background: #2563eb; color: #fff; }
.sf-button-secondary { background: #e5e7eb; color: #111827; }
.sf-message { padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; }
.sf-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.sf-warning { background: #fffbeb; color: #78350f; border: 1px solid #fde68a; }
.sf-error { background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
.sf-error-text { color: #b91c1c; font-size: 0.8125rem; margin: 0.15rem 0 0; }
.sf-table-wrap { overflow-x: auto; }
.sf-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.sf-table th, .sf-table td { padding: 0.55rem 0.6rem; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
.sf-table th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; color: #475569; }
.sf-actions-cell { white-space: nowrap; }
.sf-link { color: #1d4ed8; font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; font-size: 0.875rem; }
.sf-badge { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
.sf-badge-active { background: #ecfdf5; color: #065f46; }
.sf-badge-inactive { background: #fef2f2; color: #7f1d1d; }
.sf-empty-text { color: #6b7280; font-size: 0.875rem; }
.sf-pagination { display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
.sf-fieldset { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin: 1rem 0 0; }
.sf-legend { font-weight: 700; color: #374151; padding: 0 0.35rem; }
.sf-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #374151; margin: 0.35rem 0; }
.sf-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 720px) {
    .sf-header, .sf-main { padding: 1rem; }
    .sf-table thead { display: none; }
    .sf-table tr { display: block; border-bottom: 1px solid #e5e7eb; padding: 0.5rem 0; }
    .sf-table td { display: flex; justify-content: space-between; gap: 1rem; border: none; }
    .sf-table td::before { content: attr(data-label); font-weight: 600; color: #475569; }
}
</style>
