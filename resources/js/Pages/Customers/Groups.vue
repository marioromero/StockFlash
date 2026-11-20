<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link href="/customers" class="sf-button sf-button-secondary">Volver a clientes</Link>
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">Grupos de clientes</h1>
                    <p class="sf-subtitle">
                        Cada grupo define un descuento por defecto y un tope opcional. El cliente puede heredarlos
                        o sobrescribirlos con sus propios valores.
                    </p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede consultar grupos, pero no crear ni modificar.
            </div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="group-filters-title">
                <h2 id="group-filters-title" class="sf-section-title">Filtros</h2>
                <form class="sf-filters" role="search" novalidate @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="group-q">Buscar por código o nombre</label>
                        <input id="group-q" v-model="searchTerm" type="search" maxlength="100" class="sf-input" />
                    </div>
                    <div class="sf-field">
                        <label class="sf-label" for="group-per-page">Filas por página</label>
                        <select id="group-per-page" v-model="perPageTerm" class="sf-input">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="sf-form-actions sf-field-wide">
                        <button type="submit" class="sf-button sf-button-primary">Aplicar filtros</button>
                        <button type="button" class="sf-button sf-button-secondary" @click="resetFilters">Limpiar filtros</button>
                    </div>
                </form>
            </section>

            <section class="sf-card" aria-labelledby="group-list-title">
                <h2 id="group-list-title" class="sf-section-title">Listado</h2>
                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ groups.total }} grupo(s). Mostrando {{ groups.from ?? 0 }}–{{ groups.to ?? 0 }}.
                </p>

                <div v-if="groups.data.length === 0" class="sf-empty-text">
                    No hay grupos que coincidan con los filtros.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">Grupos de clientes con descuentos y estado.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Descuento por defecto</th>
                                <th scope="col">Tope</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in groups.data" :key="group.id">
                                <td data-label="Código"><strong>{{ group.code }}</strong></td>
                                <td data-label="Nombre">{{ group.name }}</td>
                                <td data-label="Descuento por defecto">{{ discountLabel(group.default_discount_percent) }}</td>
                                <td data-label="Tope">{{ discountLabel(group.max_discount_percent) }}</td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="group.is_active ? 'sf-badge-active' : 'sf-badge-inactive'">
                                        {{ group.is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/customers/groups/${group.id}`" class="sf-link">Editar</Link>
                                    <button
                                        v-if="group.is_active"
                                        type="button"
                                        class="sf-link"
                                        :disabled="readOnly || processingStatus"
                                        @click="changeStatus(group.id, 'deactivate')"
                                    >
                                        Desactivar
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="sf-link"
                                        :disabled="readOnly || processingStatus"
                                        @click="changeStatus(group.id, 'activate')"
                                    >
                                        Activar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="sf-pagination" aria-label="Paginación de grupos">
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="groups.current_page <= 1"
                        @click="goToPage(groups.current_page - 1)"
                    >
                        Anterior
                    </button>
                    <span class="sf-text-muted">Página {{ groups.current_page }} de {{ groups.last_page }}</span>
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="groups.current_page >= groups.last_page"
                        @click="goToPage(groups.current_page + 1)"
                    >
                        Siguiente
                    </button>
                </nav>
            </section>

            <section v-if="canManage" class="sf-card" aria-labelledby="group-form-title">
                <h2 id="group-form-title" class="sf-section-title">
                    {{ selectedGroup ? 'Editar grupo' : 'Nuevo grupo' }}
                </h2>
                <p class="sf-section-hint">
                    El código es único y se recorta. El descuento por defecto es obligatorio (0 si se omite); el
                    tope puede quedar vacío. El estado se cambia desde el listado.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submit">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="group-code">Código *</label>
                            <input
                                id="group-code"
                                v-model="form.code"
                                type="text"
                                maxlength="30"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.code }"
                                required
                                autocomplete="off"
                            />
                            <p v-if="form.errors.code" class="sf-error-text">{{ form.errors.code }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="group-name">Nombre *</label>
                            <input
                                id="group-name"
                                v-model="form.name"
                                type="text"
                                maxlength="100"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="sf-error-text">{{ form.errors.name }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="group-default-discount">Descuento por defecto (%)</label>
                            <input
                                id="group-default-discount"
                                v-model="form.default_discount_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                placeholder="0.00"
                            />
                            <p v-if="form.errors.default_discount_percent" class="sf-error-text">
                                {{ form.errors.default_discount_percent }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="group-max-discount">Tope del grupo (%)</label>
                            <input
                                id="group-max-discount"
                                v-model="form.max_discount_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                placeholder="Sin tope propio"
                            />
                            <p v-if="form.errors.max_discount_percent" class="sf-error-text">
                                {{ form.errors.max_discount_percent }}
                            </p>
                        </div>
                    </div>

                    <p v-if="form.errors.read_only" class="sf-error-text" role="alert">{{ form.errors.read_only }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing || readOnly">
                            {{ selectedGroup ? 'Guardar cambios' : 'Crear grupo' }}
                        </button>
                        <Link v-if="selectedGroup" href="/customers/groups" class="sf-button sf-button-secondary">
                            Cancelar edición
                        </Link>
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

interface GroupRow {
    id: number;
    code: string;
    name: string;
    default_discount_percent: string | null;
    max_discount_percent: string | null;
    is_active: boolean;
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
    groups: Paginated<GroupRow>;
    filters: { q: string };
    can_manage: boolean;
    read_only: boolean;
    selected_group?: GroupRow | null;
}>();

const page = usePage<SharedPageProps>();
const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => Object.values(page.props.errors ?? {})[0] ?? flash.value.error ?? null);
const canManage = computed(() => props.can_manage === true);
const readOnly = computed(() => props.read_only === true);
const selectedGroup = computed(() => props.selected_group ?? null);

const searchTerm = ref(props.filters.q ?? '');
const perPageTerm = ref(String(props.groups.per_page ?? 25));
const processingStatus = ref(false);

const form = useForm({
    code: selectedGroup.value?.code ?? '',
    name: selectedGroup.value?.name ?? '',
    default_discount_percent: selectedGroup.value?.default_discount_percent ?? '',
    max_discount_percent: selectedGroup.value?.max_discount_percent ?? '',
    read_only: '',
});

function discountLabel(value: string | null): string {
    return value === null ? '—' : `${value}%`;
}

function filterParams(pageNumber?: number): Record<string, string> {
    const params: Record<string, string> = { per_page: perPageTerm.value };

    if (searchTerm.value.trim() !== '') {
        params.q = searchTerm.value.trim();
    }

    if (pageNumber !== undefined) {
        params.page = String(pageNumber);
    }

    return params;
}

function applyFilters(): void {
    router.get('/customers/groups', filterParams(), { preserveState: true, replace: true });
}

function resetFilters(): void {
    searchTerm.value = '';
    perPageTerm.value = '25';
    router.get('/customers/groups', { per_page: '25' }, { preserveState: true, replace: true });
}

function goToPage(pageNumber: number): void {
    if (pageNumber < 1 || pageNumber > props.groups.last_page) {
        return;
    }

    router.get('/customers/groups', filterParams(pageNumber), { preserveScroll: true, replace: true });
}

function submit(): void {
    form.transform((data) => ({
        code: data.code,
        name: data.name,
        default_discount_percent: data.default_discount_percent === '' ? null : data.default_discount_percent,
        max_discount_percent: data.max_discount_percent === '' ? null : data.max_discount_percent,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!selectedGroup.value) {
                form.reset();
            }
        },
    };

    if (selectedGroup.value) {
        form.patch(`/customers/groups/${selectedGroup.value.id}`, options);
    } else {
        form.post('/customers/groups', options);
    }
}

function changeStatus(id: number, action: 'activate' | 'deactivate'): void {
    if (!window.confirm(action === 'activate' ? '¿Activar este grupo?' : '¿Desactivar este grupo?')) {
        return;
    }

    processingStatus.value = true;
    router.post(`/customers/groups/${id}/${action}`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingStatus.value = false;
        },
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
.sf-link { color: #1d4ed8; font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; font-size: 0.875rem; margin-right: 0.5rem; }
.sf-badge { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
.sf-badge-active { background: #ecfdf5; color: #065f46; }
.sf-badge-inactive { background: #fef2f2; color: #7f1d1d; }
.sf-empty-text { color: #6b7280; font-size: 0.875rem; }
.sf-pagination { display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
.sf-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 720px) {
    .sf-header, .sf-main { padding: 1rem; }
    .sf-table thead { display: none; }
    .sf-table tr { display: block; border-bottom: 1px solid #e5e7eb; padding: 0.5rem 0; }
    .sf-table td { display: flex; justify-content: space-between; gap: 1rem; border: none; }
    .sf-table td::before { content: attr(data-label); font-weight: 600; color: #475569; }
}
</style>
