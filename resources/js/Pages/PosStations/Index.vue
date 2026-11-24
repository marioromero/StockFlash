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
                    <h1 class="sf-title">Estaciones POS</h1>
                    <p class="sf-subtitle">
                        Registro de estaciones por sucursal con código único e inmutable. La vinculación de dispositivo
                        se realiza desde la ficha de cada estación mediante una acción explícita; el token nunca se
                        muestra, copia ni registra.
                    </p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede consultar estaciones POS, pero no crear ni modificar registros.
            </div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="station-filters-title">
                <h2 id="station-filters-title" class="sf-section-title">Filtros</h2>
                <form class="sf-filters" role="search" novalidate @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="station-q">Buscar por código o nombre</label>
                        <input id="station-q" v-model="searchTerm" type="search" maxlength="100" class="sf-input" />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="station-branch-filter">Sucursal</label>
                        <select id="station-branch-filter" v-model="branchTerm" class="sf-input">
                            <option value="">Todas las permitidas</option>
                            <option v-for="branch in branchOptions" :key="branch.id" :value="String(branch.id)">
                                {{ branch.code }} · {{ branch.name }}
                            </option>
                        </select>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="station-per-page">Filas por página</label>
                        <select id="station-per-page" v-model="perPageTerm" class="sf-input">
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

            <section class="sf-card" aria-labelledby="station-list-title">
                <h2 id="station-list-title" class="sf-section-title">Listado</h2>
                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ stations.total }} estación(es). Mostrando {{ stations.from ?? 0 }}–{{ stations.to ?? 0 }}.
                </p>

                <div v-if="stations.data.length === 0" class="sf-empty-text">
                    No hay estaciones POS que coincidan con los filtros.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">Estaciones POS registradas con su sucursal y estado.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Sucursal</th>
                                <th scope="col">Impresora</th>
                                <th scope="col">Dispositivo</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="station in stations.data" :key="station.id">
                                <td data-label="Código"><strong>{{ station.code }}</strong></td>
                                <td data-label="Nombre">{{ station.name }}</td>
                                <td data-label="Sucursal">{{ branchLabel(station) }}</td>
                                <td data-label="Impresora">{{ printerLabel(station.printer_profile) }}</td>
                                <td data-label="Dispositivo">{{ station.has_device ? 'Vinculado' : 'Sin vincular' }}</td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="station.is_active ? 'sf-badge-active' : 'sf-badge-inactive'">
                                        {{ station.is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/settings/pos-stations/${station.id}`" class="sf-link">
                                        Ver / editar
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="sf-pagination" aria-label="Paginación de estaciones POS">
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="stations.current_page <= 1"
                        @click="goToPage(stations.current_page - 1)"
                    >
                        Anterior
                    </button>
                    <span class="sf-text-muted">Página {{ stations.current_page }} de {{ stations.last_page }}</span>
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="stations.current_page >= stations.last_page"
                        @click="goToPage(stations.current_page + 1)"
                    >
                        Siguiente
                    </button>
                </nav>
            </section>

            <section v-if="!readOnly" class="sf-card" aria-labelledby="station-create-title">
                <h2 id="station-create-title" class="sf-section-title">Nueva estación POS</h2>
                <p class="sf-section-hint">
                    El código se guarda en mayúsculas y no se puede cambiar después. Seleccione una sucursal activa
                    dentro de su alcance. La estación se crea activa y sin dispositivo vinculado.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submitCreate">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="create-station-code">Código *</label>
                            <input
                                id="create-station-code"
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
                            <label class="sf-label" for="create-station-name">Nombre *</label>
                            <input
                                id="create-station-name"
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
                            <label class="sf-label" for="create-station-branch">Sucursal *</label>
                            <select
                                id="create-station-branch"
                                v-model="form.branch_id"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.branch_id }"
                                required
                            >
                                <option value="">Seleccione una sucursal</option>
                                <option v-for="branch in branchOptions" :key="branch.id" :value="String(branch.id)">
                                    {{ branch.code }} · {{ branch.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.branch_id" class="sf-error-text">{{ form.errors.branch_id }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-station-printer">Perfil de impresora</label>
                            <select id="create-station-printer" v-model="form.printer_profile" class="sf-input">
                                <option value="">Sin impresora</option>
                                <option v-for="profile in printerProfiles" :key="profile" :value="profile">
                                    {{ printerLabel(profile) }}
                                </option>
                            </select>
                            <p v-if="form.errors.printer_profile" class="sf-error-text">
                                {{ form.errors.printer_profile }}
                            </p>
                        </div>
                    </div>

                    <p v-if="form.errors.read_only" class="sf-error-text" role="alert">{{ form.errors.read_only }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                            Registrar estación
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

interface StationRow {
    id: number;
    code: string;
    name: string;
    printer_profile: string | null;
    is_active: boolean;
    has_device: boolean;
    branch: BranchOption | null;
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
    stations: Paginated<StationRow>;
    filters: { q: string; branch_id: number | null };
    branch_options: BranchOption[];
    printer_profiles: string[];
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => Object.values(page.props.errors ?? {})[0] ?? flash.value.error ?? null);
const readOnly = computed(() => props.read_only === true);
const branchOptions = computed(() => props.branch_options);
const printerProfiles = computed(() => props.printer_profiles);

const searchTerm = ref(props.filters.q ?? '');
const branchTerm = ref(props.filters.branch_id !== null ? String(props.filters.branch_id) : '');
const perPageTerm = ref(String(props.stations.per_page ?? 25));

const form = useForm({
    code: '',
    name: '',
    branch_id: '',
    printer_profile: '',
    read_only: '',
});

const PRINTER_LABELS: Record<string, string> = {
    thermal58: 'Térmica 58 mm',
    thermal80: 'Térmica 80 mm',
    a4: 'A4',
};

function printerLabel(profile: string | null): string {
    if (!profile) {
        return 'Sin impresora';
    }

    return PRINTER_LABELS[profile] ?? profile;
}

function branchLabel(station: StationRow): string {
    if (!station.branch) {
        return 'Sin sucursal';
    }

    return `${station.branch.code} · ${station.branch.name}`;
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
    router.get('/settings/pos-stations', filterParams(), { preserveState: true, replace: true });
}

function resetFilters(): void {
    searchTerm.value = '';
    branchTerm.value = '';
    perPageTerm.value = '25';
    router.get('/settings/pos-stations', { per_page: '25' }, { preserveState: true, replace: true });
}

function goToPage(pageNumber: number): void {
    if (pageNumber < 1 || pageNumber > props.stations.last_page) {
        return;
    }

    router.get('/settings/pos-stations', filterParams(pageNumber), { preserveScroll: true, replace: true });
}

function submitCreate(): void {
    form.transform((data) => ({
        code: data.code,
        name: data.name,
        branch_id: data.branch_id === '' ? null : Number(data.branch_id),
        printer_profile: data.printer_profile === '' ? null : data.printer_profile,
    })).post('/settings/pos-stations', {
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
.sf-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 720px) {
    .sf-header, .sf-main { padding: 1rem; }
    .sf-table thead { display: none; }
    .sf-table tr { display: block; border-bottom: 1px solid #e5e7eb; padding: 0.5rem 0; }
    .sf-table td { display: flex; justify-content: space-between; gap: 1rem; border: none; }
    .sf-table td::before { content: attr(data-label); font-weight: 600; color: #475569; }
}
</style>
