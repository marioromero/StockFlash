<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link href="/sellers" class="sf-button sf-button-secondary">Volver a vendedores</Link>
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">{{ seller.name }}</h1>
                    <p class="sf-subtitle">Código {{ seller.code }} · {{ statusLabel }}</p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: la ficha puede consultarse, pero no se guardan cambios.
            </div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="seller-detail-title">
                <h2 id="seller-detail-title" class="sf-section-title">Datos del vendedor</h2>
                <dl class="sf-summary-grid">
                    <div class="sf-summary-item"><dt>Código</dt><dd>{{ seller.code }}</dd></div>
                    <div class="sf-summary-item"><dt>Nombre</dt><dd>{{ seller.name }}</dd></div>
                    <div class="sf-summary-item">
                        <dt>Usuario vinculado</dt>
                        <dd>{{ seller.user?.name ?? 'Sin usuario' }}</dd>
                    </div>
                    <div class="sf-summary-item"><dt>Comisión</dt><dd>{{ seller.commission_percent }}%</dd></div>
                    <div class="sf-summary-item"><dt>Estado</dt><dd>{{ statusLabel }}</dd></div>
                    <div class="sf-summary-item">
                        <dt>Sucursales visibles</dt>
                        <dd>{{ branchSummary }}</dd>
                    </div>
                </dl>
                <p v-if="seller.has_hidden_assignments" class="sf-section-hint">
                    Existen asignaciones en sucursales fuera de su alcance. Se conservan sin cambios y no se muestran.
                </p>
            </section>

            <section v-if="!readOnly" class="sf-card" aria-labelledby="seller-edit-title">
                <h2 id="seller-edit-title" class="sf-section-title">Editar vendedor</h2>
                <p class="sf-section-hint">
                    El código no se puede modificar. Solo puede agregar o quitar sucursales dentro de su alcance; las
                    asignaciones fuera de él se conservan.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submitUpdate">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="edit-seller-name">Nombre *</label>
                            <input
                                id="edit-seller-name"
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
                            <label class="sf-label" for="edit-seller-user">Usuario vinculado (opcional)</label>
                            <select id="edit-seller-user" v-model="form.user_id" class="sf-input">
                                <option value="">Sin usuario</option>
                                <option v-for="user in userOptions" :key="user.id" :value="String(user.id)">
                                    {{ user.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.user_id" class="sf-error-text">{{ form.errors.user_id }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-seller-commission">Comisión (%)</label>
                            <input
                                id="edit-seller-commission"
                                v-model="form.commission_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.commission_percent }"
                            />
                            <p v-if="form.errors.commission_percent" class="sf-error-text">
                                {{ form.errors.commission_percent }}
                            </p>
                        </div>
                    </div>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Sucursales dentro de su alcance</legend>
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
                    <p v-if="form.errors.seller" class="sf-error-text" role="alert">{{ form.errors.seller }}</p>

                    <div class="sf-form-actions">
                        <button
                            type="submit"
                            class="sf-button sf-button-primary"
                            :disabled="form.processing || seller.is_anonymized"
                        >
                            Guardar cambios
                        </button>
                        <button
                            v-if="seller.is_active"
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processingStatus || seller.is_anonymized"
                            @click="changeStatus('deactivate')"
                        >
                            Desactivar
                        </button>
                        <button
                            v-else
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processingStatus || seller.is_anonymized"
                            @click="changeStatus('activate')"
                        >
                            Activar
                        </button>
                    </div>
                    <p v-if="seller.is_anonymized" class="sf-section-hint">
                        El vendedor está anonimizado: no admite edición ni cambios de estado.
                    </p>
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
    is_active?: boolean;
}

interface UserOption {
    id: number;
    name: string;
}

interface SellerDetail {
    id: number;
    code: string;
    name: string;
    commission_percent: string;
    is_active: boolean;
    is_anonymized: boolean;
    user: UserOption | null;
    branches: BranchOption[];
    has_hidden_assignments: boolean;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: { user?: { name?: string } | null };
    flash?: { success?: string | null; warning?: string | null; error?: string | null };
    errors?: Record<string, string>;
}

const props = defineProps<{
    seller: SellerDetail;
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
const processingStatus = ref(false);

const form = useForm({
    name: props.seller.name,
    user_id: props.seller.user !== null ? String(props.seller.user.id) : '',
    commission_percent: props.seller.commission_percent,
    branches: props.seller.branches
        .filter((branch) => props.branch_options.some((option) => option.id === branch.id))
        .map((branch) => branch.id),
    read_only: '',
    seller: '',
});

const statusLabel = computed(() => {
    if (props.seller.is_anonymized) {
        return 'Anonimizado';
    }

    return props.seller.is_active ? 'Activo' : 'Inactivo';
});

const branchSummary = computed(() => {
    if (props.seller.branches.length === 0) {
        return 'Sin sucursales visibles';
    }

    return props.seller.branches.map((branch) => branch.name).join(', ');
});

function submitUpdate(): void {
    form.transform((data) => ({
        ...data,
        user_id: data.user_id === '' ? null : Number(data.user_id),
        commission_percent: data.commission_percent.trim() === '' ? null : data.commission_percent.trim(),
        branches: data.branches,
    })).patch(`/sellers/${props.seller.id}`, {
        preserveScroll: true,
    });
}

function changeStatus(action: 'activate' | 'deactivate'): void {
    if (!window.confirm(action === 'activate' ? '¿Activar este vendedor?' : '¿Desactivar este vendedor sin borrar su código?')) {
        return;
    }

    processingStatus.value = true;
    router.post(`/sellers/${props.seller.id}/${action}`, {}, {
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
.sf-titlebar { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: center; }
.sf-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.sf-subtitle, .sf-section-hint, .sf-text-muted { font-size: 0.875rem; color: #4b5563; margin: 0.35rem 0 0; }
.sf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; }
.sf-section-title { font-size: 1.125rem; font-weight: 700; color: #111827; margin: 0 0 0.75rem; }
.sf-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin: 0; }
.sf-summary-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.65rem; }
.sf-summary-item dt { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.sf-summary-item dd { margin: 0.25rem 0 0; font-weight: 600; color: #111827; }
.sf-form { margin-top: 0.5rem; }
.sf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem; }
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
.sf-fieldset { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin: 1rem 0 0; }
.sf-legend { font-weight: 700; color: #374151; padding: 0 0.35rem; }
.sf-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #374151; margin: 0.35rem 0; }
@media (max-width: 720px) { .sf-header, .sf-main { padding: 1rem; } }
</style>
