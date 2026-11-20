<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link href="/customers/groups" class="sf-button sf-button-secondary">Grupos</Link>
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">Clientes</h1>
                    <p class="sf-subtitle">
                        Registro de personas y empresas. El RUT y los datos de contacto se muestran enmascarados;
                        solo un usuario con permiso de datos sensibles puede revelarlos y esa lectura queda auditada.
                    </p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede consultar clientes, pero no crear ni modificar registros.
            </div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="customer-filters-title">
                <h2 id="customer-filters-title" class="sf-section-title">Filtros</h2>
                <form class="sf-filters" role="search" novalidate @submit.prevent="applyFilters">
                    <div class="sf-field">
                        <label class="sf-label" for="customer-q">Buscar por nombre</label>
                        <input id="customer-q" v-model="searchTerm" type="search" maxlength="100" class="sf-input" />
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="customer-group-filter">Grupo</label>
                        <select id="customer-group-filter" v-model="groupTerm" class="sf-input">
                            <option value="">Todos</option>
                            <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                {{ group.code }} · {{ group.name }}
                            </option>
                        </select>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="customer-status-filter">Estado</label>
                        <select id="customer-status-filter" v-model="statusTerm" class="sf-input">
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                            <option value="blocked">Bloqueados</option>
                        </select>
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="customer-per-page">Filas por página</label>
                        <select id="customer-per-page" v-model="perPageTerm" class="sf-input">
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

                <form class="sf-filters sf-rut-search" role="search" novalidate @submit.prevent="applyRutFilter">
                    <div class="sf-field">
                        <label class="sf-label" for="customer-rut-filter">Buscar por RUT exacto</label>
                        <input
                            id="customer-rut-filter"
                            v-model="rutTerm"
                            type="text"
                            maxlength="30"
                            class="sf-input"
                            placeholder="Ej.: 12.345.678-5"
                            autocomplete="off"
                        />
                    </div>

                    <div class="sf-form-actions sf-field-wide">
                        <button
                            type="submit"
                            class="sf-button sf-button-primary"
                            :disabled="rutTerm.trim() === ''"
                        >
                            Buscar por RUT
                        </button>
                        <button
                            v-if="filters.rut_filter"
                            type="button"
                            class="sf-button sf-button-secondary"
                            @click="clearRutFilter"
                        >
                            Quitar filtro RUT
                        </button>
                    </div>

                    <p v-if="filters.rut_filter" class="sf-section-hint sf-field-wide" role="status">
                        Filtro RUT activo. El RUT no se muestra ni se incluye en la URL.
                    </p>
                </form>
            </section>

            <section class="sf-card" aria-labelledby="customer-list-title">
                <h2 id="customer-list-title" class="sf-section-title">Listado</h2>
                <p class="sf-text-muted" role="status" aria-live="polite">
                    {{ customers.total }} cliente(s). Mostrando {{ customers.from ?? 0 }}–{{ customers.to ?? 0 }}.
                </p>

                <div v-if="customers.data.length === 0" class="sf-empty-text">
                    No hay clientes que coincidan con los filtros.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-sr-only">Clientes registrados con datos sensibles enmascarados.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">RUT</th>
                                <th scope="col">Correo</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Grupo</th>
                                <th scope="col">Descuento</th>
                                <th scope="col">Tope</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="customer in customers.data" :key="customer.id">
                                <td data-label="Nombre">
                                    <strong>{{ customer.name }}</strong>
                                    <span v-if="customer.trade_name" class="sf-text-muted"> · {{ customer.trade_name }}</span>
                                </td>
                                <td data-label="Tipo">{{ customer.type === 'company' ? 'Empresa' : 'Persona' }}</td>
                                <td data-label="RUT">{{ customer.rut_masked ?? 'Sin RUT' }}</td>
                                <td data-label="Correo">{{ customer.email_masked ?? '—' }}</td>
                                <td data-label="Teléfono">{{ customer.phone_masked ?? '—' }}</td>
                                <td data-label="Grupo">{{ customer.group?.name ?? '—' }}</td>
                                <td data-label="Descuento">{{ discountLabel(customer.discounts.effective_default) }}</td>
                                <td data-label="Tope">{{ discountLabel(customer.discounts.effective_max) }}</td>
                                <td data-label="Estado">
                                    <span class="sf-badge" :class="statusClass(customer)">
                                        {{ statusLabel(customer) }}
                                    </span>
                                </td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/customers/${customer.id}`" class="sf-link">Ver / editar</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="sf-pagination" aria-label="Paginación de clientes">
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="customers.current_page <= 1"
                        @click="goToPage(customers.current_page - 1)"
                    >
                        Anterior
                    </button>
                    <span class="sf-text-muted">Página {{ customers.current_page }} de {{ customers.last_page }}</span>
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="customers.current_page >= customers.last_page"
                        @click="goToPage(customers.current_page + 1)"
                    >
                        Siguiente
                    </button>
                </nav>
            </section>

            <section v-if="canManage" class="sf-card" aria-labelledby="customer-create-title">
                <h2 id="customer-create-title" class="sf-section-title">Nuevo cliente</h2>
                <p class="sf-section-hint">
                    El RUT es opcional; si lo ingresa se valida con módulo 11, se guarda cifrado y se indexa de
                    forma ciega. El descuento propio vacío hereda el del grupo.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submitCreate">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="create-type">Tipo *</label>
                            <select id="create-type" v-model="form.type" class="sf-input" :class="{ 'sf-input-error': form.errors.type }">
                                <option value="person">Persona</option>
                                <option value="company">Empresa</option>
                            </select>
                            <p v-if="form.errors.type" class="sf-error-text">{{ form.errors.type }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-name">Nombre o razón social *</label>
                            <input
                                id="create-name"
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
                            <label class="sf-label" for="create-trade-name">Nombre de fantasía</label>
                            <input id="create-trade-name" v-model="form.trade_name" type="text" maxlength="150" class="sf-input" />
                            <p v-if="form.errors.trade_name" class="sf-error-text">{{ form.errors.trade_name }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-rut">RUT</label>
                            <input
                                id="create-rut"
                                v-model="form.rut"
                                type="text"
                                maxlength="30"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.rut }"
                                placeholder="Ej.: 12.345.678-5"
                                autocomplete="off"
                            />
                            <p v-if="form.errors.rut" class="sf-error-text">{{ form.errors.rut }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-email">Correo</label>
                            <input id="create-email" v-model="form.email" type="email" maxlength="150" class="sf-input" />
                            <p v-if="form.errors.email" class="sf-error-text">{{ form.errors.email }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-phone">Teléfono</label>
                            <input id="create-phone" v-model="form.phone" type="text" maxlength="30" class="sf-input" />
                            <p v-if="form.errors.phone" class="sf-error-text">{{ form.errors.phone }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-group">Grupo de clientes</label>
                            <select id="create-group" v-model="form.customer_group_id" class="sf-input">
                                <option value="">Sin grupo</option>
                                <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                    {{ group.code }} · {{ group.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.customer_group_id" class="sf-error-text">{{ form.errors.customer_group_id }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-default-discount">Descuento propio (%)</label>
                            <input
                                id="create-default-discount"
                                v-model="form.default_discount_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                placeholder="Heredar del grupo"
                            />
                            <p v-if="form.errors.default_discount_percent" class="sf-error-text">
                                {{ form.errors.default_discount_percent }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="create-max-discount">Tope propio (%)</label>
                            <input
                                id="create-max-discount"
                                v-model="form.max_discount_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                placeholder="Heredar del grupo"
                            />
                            <p v-if="form.errors.max_discount_percent" class="sf-error-text">
                                {{ form.errors.max_discount_percent }}
                            </p>
                        </div>
                    </div>

                    <fieldset v-if="form.type === 'company'" class="sf-fieldset">
                        <legend class="sf-legend">Datos tributarios de la empresa (opcionales)</legend>
                        <div class="sf-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="create-business-activity">Giro</label>
                                <input id="create-business-activity" v-model="form.business_activity" type="text" maxlength="150" class="sf-input" />
                                <p v-if="form.errors.business_activity" class="sf-error-text">{{ form.errors.business_activity }}</p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="create-fiscal-address">Domicilio tributario</label>
                                <input id="create-fiscal-address" v-model="form.fiscal_address" type="text" maxlength="255" class="sf-input" />
                                <p v-if="form.errors.fiscal_address" class="sf-error-text">{{ form.errors.fiscal_address }}</p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="create-fiscal-commune">Comuna tributaria</label>
                                <input id="create-fiscal-commune" v-model="form.fiscal_commune" type="text" maxlength="100" class="sf-input" />
                                <p v-if="form.errors.fiscal_commune" class="sf-error-text">{{ form.errors.fiscal_commune }}</p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="create-fiscal-city">Ciudad tributaria</label>
                                <input id="create-fiscal-city" v-model="form.fiscal_city" type="text" maxlength="100" class="sf-input" />
                                <p v-if="form.errors.fiscal_city" class="sf-error-text">{{ form.errors.fiscal_city }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <p v-if="form.errors.read_only" class="sf-error-text" role="alert">{{ form.errors.read_only }}</p>
                    <p v-if="form.errors.customer" class="sf-error-text" role="alert">{{ form.errors.customer }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing || readOnly">
                            Registrar cliente
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

interface GroupOption {
    id: number;
    code: string;
    name: string;
}

interface Discounts {
    own_default: string | null;
    own_max: string | null;
    group_default: string | null;
    group_max: string | null;
    effective_default: string | null;
    effective_max: string | null;
}

interface CustomerRow {
    id: number;
    type: 'person' | 'company' | string;
    name: string;
    trade_name: string | null;
    rut_masked: string | null;
    has_rut: boolean;
    email_masked: string | null;
    phone_masked: string | null;
    group: { id: number; code: string; name: string } | null;
    discounts: Discounts;
    is_active: boolean;
    is_blocked: boolean;
    is_anonymized: boolean;
    is_selectable: boolean;
    origin: string;
    created_at: string | null;
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
    customers: Paginated<CustomerRow>;
    groups: GroupOption[];
    filters: { q: string; group_id: number | null; status: string | null; rut_filter?: boolean };
    can_manage: boolean;
    can_view_sensitive: boolean;
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => Object.values(page.props.errors ?? {})[0] ?? flash.value.error ?? null);
const canManage = computed(() => props.can_manage === true);
const readOnly = computed(() => props.read_only === true);

const searchTerm = ref(props.filters.q ?? '');
const groupTerm = ref(props.filters.group_id !== null ? String(props.filters.group_id) : '');
const statusTerm = ref(props.filters.status ?? '');
const rutTerm = ref('');
const perPageTerm = ref(String(props.customers.per_page ?? 25));

const form = useForm({
    type: 'person' as 'person' | 'company',
    name: '',
    trade_name: '',
    rut: '',
    email: '',
    phone: '',
    business_activity: '',
    fiscal_address: '',
    fiscal_commune: '',
    fiscal_city: '',
    customer_group_id: '' as string,
    default_discount_percent: '',
    max_discount_percent: '',
    // Errores de servidor sin campo visible.
    read_only: '',
    customer: '',
});

function discountLabel(value: string | null): string {
    return value === null ? '—' : `${value}%`;
}

function statusLabel(customer: CustomerRow): string {
    if (customer.is_anonymized) {
        return 'Anonimizado';
    }

    if (customer.is_blocked) {
        return 'Bloqueado';
    }

    return customer.is_active ? 'Activo' : 'Inactivo';
}

function statusClass(customer: CustomerRow): string {
    if (customer.is_blocked || customer.is_anonymized) {
        return 'sf-badge-inactive';
    }

    return customer.is_active ? 'sf-badge-active' : 'sf-badge-inactive';
}

function filterParams(pageNumber?: number): Record<string, string> {
    const params: Record<string, string> = {
        per_page: perPageTerm.value,
    };

    if (searchTerm.value.trim() !== '') {
        params.q = searchTerm.value.trim();
    }

    if (groupTerm.value !== '') {
        params.group_id = groupTerm.value;
    }

    if (statusTerm.value !== '') {
        params.status = statusTerm.value;
    }

    if (pageNumber !== undefined) {
        params.page = String(pageNumber);
    }

    return params;
}

function applyFilters(): void {
    // El RUT nunca forma parte de la búsqueda GET.
    router.get('/customers', filterParams(), { preserveState: true, replace: true });
}

function applyRutFilter(): void {
    const value = rutTerm.value.trim();

    if (value === '') {
        return;
    }

    router.post('/customers/rut-search', { rut: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            const errors = (page.props.errors ?? {}) as Record<string, string | undefined>;

            if (!errors.rut) {
                // No conservar el RUT en claro en el formulario tras buscar.
                rutTerm.value = '';
            }
        },
    });
}

function clearRutFilter(): void {
    rutTerm.value = '';
    router.post('/customers/rut-search/clear', {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    searchTerm.value = '';
    groupTerm.value = '';
    statusTerm.value = '';
    rutTerm.value = '';
    perPageTerm.value = '25';

    if (props.filters.rut_filter) {
        // El filtro RUT vive en sesión: se limpia con su endpoint POST.
        router.post('/customers/rut-search/clear', {}, { preserveState: true, replace: true });

        return;
    }

    router.get('/customers', { per_page: '25' }, { preserveState: true, replace: true });
}

function goToPage(pageNumber: number): void {
    if (pageNumber < 1 || pageNumber > props.customers.last_page) {
        return;
    }

    router.get('/customers', filterParams(pageNumber), { preserveScroll: true, replace: true });
}

function submitCreate(): void {
    form.transform((data) => ({
        ...data,
        customer_group_id: data.customer_group_id === '' ? null : Number(data.customer_group_id),
        default_discount_percent: data.default_discount_percent === '' ? null : data.default_discount_percent,
        max_discount_percent: data.max_discount_percent === '' ? null : data.max_discount_percent,
        email: data.email === '' ? null : data.email,
        phone: data.phone === '' ? null : data.phone,
        trade_name: data.trade_name === '' ? null : data.trade_name,
        rut: data.rut === '' ? null : data.rut,
        business_activity: data.business_activity === '' ? null : data.business_activity,
        fiscal_address: data.fiscal_address === '' ? null : data.fiscal_address,
        fiscal_commune: data.fiscal_commune === '' ? null : data.fiscal_commune,
        fiscal_city: data.fiscal_city === '' ? null : data.fiscal_city,
    })).post('/customers', {
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
.sf-rut-search { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
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
.sf-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 720px) {
    .sf-header, .sf-main { padding: 1rem; }
    .sf-table thead { display: none; }
    .sf-table tr { display: block; border-bottom: 1px solid #e5e7eb; padding: 0.5rem 0; }
    .sf-table td { display: flex; justify-content: space-between; gap: 1rem; border: none; }
    .sf-table td::before { content: attr(data-label); font-weight: 600; color: #475569; }
}
</style>
