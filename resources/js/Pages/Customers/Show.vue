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
                    <h1 class="sf-title">{{ customer.name }}</h1>
                    <p class="sf-subtitle">
                        {{ customer.type === 'company' ? 'Empresa' : 'Persona' }} ·
                        {{ statusLabel }}
                    </p>
                </div>
                <div class="sf-titlebar-side">
                    <button
                        v-if="canViewSensitive"
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="revealing"
                        @click="revealSensitive"
                    >
                        {{ revealing ? 'Revelando…' : 'Revelar datos sensibles' }}
                    </button>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: la ficha puede consultarse, pero no se guardan cambios.
            </div>
            <div v-if="revealError" class="sf-message sf-error" role="alert">{{ revealError }}</div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="customer-detail-title">
                <h2 id="customer-detail-title" class="sf-section-title">Datos registrados (enmascarados)</h2>
                <dl class="sf-summary-grid">
                    <div class="sf-summary-item"><dt>RUT</dt><dd>{{ customer.rut_masked ?? 'Sin RUT' }}</dd></div>
                    <div class="sf-summary-item"><dt>Correo</dt><dd>{{ customer.email_masked ?? 'Sin correo' }}</dd></div>
                    <div class="sf-summary-item"><dt>Teléfono</dt><dd>{{ customer.phone_masked ?? 'Sin teléfono' }}</dd></div>
                    <div class="sf-summary-item"><dt>Grupo</dt><dd>{{ customer.group?.name ?? 'Sin grupo' }}</dd></div>
                    <div class="sf-summary-item">
                        <dt>Descuento efectivo</dt>
                        <dd>{{ discountLabel(customer.discounts.effective_default) }}</dd>
                    </div>
                    <div class="sf-summary-item">
                        <dt>Tope efectivo</dt>
                        <dd>{{ discountLabel(customer.discounts.effective_max) }}</dd>
                    </div>
                    <div class="sf-summary-item">
                        <dt>Datos tributarios</dt>
                        <dd>{{ fiscalSummary }}</dd>
                    </div>
                    <div class="sf-summary-item"><dt>Estado</dt><dd>{{ statusLabel }}</dd></div>
                </dl>
                <p v-if="!canViewSensitive" class="sf-section-hint">
                    Los datos sensibles solo pueden revelarse con el permiso de datos sensibles; el revelado queda
                    auditado. La edición no muestra los valores existentes.
                </p>
            </section>

            <section v-if="revealed" class="sf-card" aria-labelledby="customer-revealed-title">
                <h2 id="customer-revealed-title" class="sf-section-title">Datos sensibles revelados</h2>
                <p class="sf-section-hint">Esta lectura quedó registrada en la auditoría, sin copiar los valores.</p>
                <dl class="sf-summary-grid">
                    <div class="sf-summary-item"><dt>RUT</dt><dd>{{ revealed.rut ?? 'Sin RUT' }}</dd></div>
                    <div class="sf-summary-item"><dt>Correo</dt><dd>{{ revealed.email ?? 'Sin correo' }}</dd></div>
                    <div class="sf-summary-item"><dt>Teléfono</dt><dd>{{ revealed.phone ?? 'Sin teléfono' }}</dd></div>
                    <div class="sf-summary-item"><dt>Giro</dt><dd>{{ revealed.business_activity ?? '—' }}</dd></div>
                    <div class="sf-summary-item"><dt>Domicilio tributario</dt><dd>{{ revealed.fiscal_address ?? '—' }}</dd></div>
                    <div class="sf-summary-item"><dt>Comuna / ciudad</dt><dd>{{ fiscalRevealed }}</dd></div>
                </dl>
            </section>

            <section v-if="canManage" class="sf-card" aria-labelledby="customer-edit-title">
                <h2 id="customer-edit-title" class="sf-section-title">Editar cliente</h2>
                <p class="sf-section-hint">
                    Los campos sensibles se dejan en blanco para conservar el valor actual. No se muestran los
                    valores existentes; escriba uno nuevo solo si desea reemplazarlo.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submitUpdate">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="edit-type">Tipo *</label>
                            <select id="edit-type" v-model="form.type" class="sf-input" :class="{ 'sf-input-error': form.errors.type }">
                                <option value="person">Persona</option>
                                <option value="company">Empresa</option>
                            </select>
                            <p v-if="form.errors.type" class="sf-error-text">{{ form.errors.type }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-name">Nombre o razón social *</label>
                            <input id="edit-name" v-model="form.name" type="text" maxlength="150" class="sf-input" required />
                            <p v-if="form.errors.name" class="sf-error-text">{{ form.errors.name }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-trade-name">Nombre de fantasía</label>
                            <input id="edit-trade-name" v-model="form.trade_name" type="text" maxlength="150" class="sf-input" />
                            <p v-if="form.errors.trade_name" class="sf-error-text">{{ form.errors.trade_name }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-rut">Nuevo RUT</label>
                            <input
                                id="edit-rut"
                                v-model="form.rut"
                                type="text"
                                maxlength="30"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.rut }"
                                :placeholder="customer.has_rut ? 'Dejar en blanco para conservar' : 'Sin RUT registrado'"
                                autocomplete="off"
                            />
                            <p v-if="form.errors.rut" class="sf-error-text">{{ form.errors.rut }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-email">Nuevo correo</label>
                            <input
                                id="edit-email"
                                v-model="form.email"
                                type="email"
                                maxlength="150"
                                class="sf-input"
                                :placeholder="customer.has_email ? 'Dejar en blanco para conservar' : 'Sin correo registrado'"
                            />
                            <p v-if="form.errors.email" class="sf-error-text">{{ form.errors.email }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-phone">Nuevo teléfono</label>
                            <input
                                id="edit-phone"
                                v-model="form.phone"
                                type="text"
                                maxlength="30"
                                class="sf-input"
                                :placeholder="customer.has_phone ? 'Dejar en blanco para conservar' : 'Sin teléfono registrado'"
                            />
                            <p v-if="form.errors.phone" class="sf-error-text">{{ form.errors.phone }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-group">Grupo de clientes</label>
                            <select id="edit-group" v-model="form.customer_group_id" class="sf-input">
                                <option value="">Sin grupo</option>
                                <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                    {{ group.code }} · {{ group.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.customer_group_id" class="sf-error-text">{{ form.errors.customer_group_id }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-default-discount">Descuento propio (%)</label>
                            <input
                                id="edit-default-discount"
                                v-model="form.default_discount_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                placeholder="Vacío = heredar del grupo"
                            />
                            <p v-if="form.errors.default_discount_percent" class="sf-error-text">
                                {{ form.errors.default_discount_percent }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-max-discount">Tope propio (%)</label>
                            <input
                                id="edit-max-discount"
                                v-model="form.max_discount_percent"
                                type="text"
                                inputmode="decimal"
                                maxlength="6"
                                class="sf-input"
                                placeholder="Vacío = heredar del grupo"
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
                                <label class="sf-label" for="edit-business-activity">Nuevo giro</label>
                                <input
                                    id="edit-business-activity"
                                    v-model="form.business_activity"
                                    type="text"
                                    maxlength="150"
                                    class="sf-input"
                                    :placeholder="customer.has_business_activity ? 'Dejar en blanco para conservar' : 'Sin giro registrado'"
                                />
                                <p v-if="form.errors.business_activity" class="sf-error-text">{{ form.errors.business_activity }}</p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="edit-fiscal-address">Nuevo domicilio tributario</label>
                                <input
                                    id="edit-fiscal-address"
                                    v-model="form.fiscal_address"
                                    type="text"
                                    maxlength="255"
                                    class="sf-input"
                                    :placeholder="customer.has_fiscal_address ? 'Dejar en blanco para conservar' : 'Sin domicilio registrado'"
                                />
                                <p v-if="form.errors.fiscal_address" class="sf-error-text">{{ form.errors.fiscal_address }}</p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="edit-fiscal-commune">Nueva comuna tributaria</label>
                                <input
                                    id="edit-fiscal-commune"
                                    v-model="form.fiscal_commune"
                                    type="text"
                                    maxlength="100"
                                    class="sf-input"
                                    :placeholder="customer.has_fiscal_commune ? 'Dejar en blanco para conservar' : 'Sin comuna registrada'"
                                />
                                <p v-if="form.errors.fiscal_commune" class="sf-error-text">{{ form.errors.fiscal_commune }}</p>
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="edit-fiscal-city">Nueva ciudad tributaria</label>
                                <input
                                    id="edit-fiscal-city"
                                    v-model="form.fiscal_city"
                                    type="text"
                                    maxlength="100"
                                    class="sf-input"
                                    :placeholder="customer.has_fiscal_city ? 'Dejar en blanco para conservar' : 'Sin ciudad registrada'"
                                />
                                <p v-if="form.errors.fiscal_city" class="sf-error-text">{{ form.errors.fiscal_city }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <p v-if="form.errors.read_only" class="sf-error-text" role="alert">{{ form.errors.read_only }}</p>
                    <p v-if="form.errors.customer" class="sf-error-text" role="alert">{{ form.errors.customer }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing || readOnly || customer.is_anonymized">
                            Guardar cambios
                        </button>
                        <button
                            v-if="customer.is_active"
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processingStatus || readOnly || customer.is_anonymized"
                            @click="changeStatus('deactivate')"
                        >
                            Desactivar
                        </button>
                        <button
                            v-else
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processingStatus || readOnly || customer.is_anonymized || customer.is_blocked"
                            @click="changeStatus('activate')"
                        >
                            Activar
                        </button>
                    </div>
                    <p v-if="customer.is_blocked" class="sf-section-hint">
                        El cliente está bloqueado: no puede reactivarse hasta que se retire el bloqueo por el
                        procedimiento correspondiente.
                    </p>
                    <p v-if="customer.is_anonymized" class="sf-section-hint">
                        El cliente está anonimizado: no admite edición ni cambios de estado.
                    </p>
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

interface CustomerDetail {
    id: number;
    type: 'person' | 'company' | string;
    name: string;
    trade_name: string | null;
    rut_masked: string | null;
    has_rut: boolean;
    email_masked: string | null;
    has_email: boolean;
    phone_masked: string | null;
    has_phone: boolean;
    has_business_activity: boolean;
    has_fiscal_address: boolean;
    has_fiscal_commune: boolean;
    has_fiscal_city: boolean;
    group: { id: number; code: string; name: string } | null;
    discounts: Discounts;
    is_active: boolean;
    is_blocked: boolean;
    is_anonymized: boolean;
    is_selectable: boolean;
    origin: string;
    created_at: string | null;
    updated_at: string | null;
}

interface Revealed {
    customer_id: number;
    rut: string | null;
    email: string | null;
    phone: string | null;
    business_activity: string | null;
    fiscal_address: string | null;
    fiscal_commune: string | null;
    fiscal_city: string | null;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: { user?: { name?: string } | null };
    flash?: { success?: string | null; warning?: string | null; error?: string | null };
    errors?: Record<string, string>;
}

const props = defineProps<{
    customer: CustomerDetail;
    groups: GroupOption[];
    can_manage: boolean;
    can_view_sensitive: boolean;
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => Object.values(page.props.errors ?? {})[0] ?? flash.value.error ?? null);
const canManage = computed(() => props.can_manage === true);
const canViewSensitive = computed(() => props.can_view_sensitive === true);
const readOnly = computed(() => props.read_only === true);

const revealing = ref(false);
const revealError = ref<string | null>(null);
const revealed = ref<Revealed | null>(null);
const processingStatus = ref(false);

const form = useForm({
    type: props.customer.type === 'company' ? ('company' as const) : ('person' as const),
    name: props.customer.name,
    trade_name: props.customer.trade_name ?? '',
    rut: '',
    email: '',
    phone: '',
    business_activity: '',
    fiscal_address: '',
    fiscal_commune: '',
    fiscal_city: '',
    customer_group_id: props.customer.group !== null ? String(props.customer.group.id) : '',
    default_discount_percent: props.customer.discounts.own_default ?? '',
    max_discount_percent: props.customer.discounts.own_max ?? '',
    read_only: '',
    customer: '',
});

const statusLabel = computed(() => {
    if (props.customer.is_anonymized) {
        return 'Anonimizado';
    }

    if (props.customer.is_blocked) {
        return 'Bloqueado';
    }

    return props.customer.is_active ? 'Activo' : 'Inactivo';
});

const fiscalSummary = computed(() => {
    const flags = [
        props.customer.has_business_activity,
        props.customer.has_fiscal_address,
        props.customer.has_fiscal_commune,
        props.customer.has_fiscal_city,
    ];

    return flags.some(Boolean) ? 'Registrados (enmascarados)' : 'Sin datos';
});

const fiscalRevealed = computed(() => {
    if (!revealed.value) {
        return '—';
    }

    const parts = [revealed.value.fiscal_commune, revealed.value.fiscal_city].filter(
        (part): part is string => Boolean(part),
    );

    return parts.length > 0 ? parts.join(' / ') : '—';
});

function discountLabel(value: string | null): string {
    return value === null ? '—' : `${value}%`;
}

async function revealSensitive(): Promise<void> {
    revealError.value = '';
    revealing.value = true;

    try {
        const response = await fetch(`/customers/${props.customer.id}/reveal`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 403) {
            revealError.value = 'Su usuario no tiene permiso para revelar datos sensibles.';
            return;
        }

        if (!response.ok) {
            revealError.value = 'No fue posible revelar los datos sensibles.';
            return;
        }

        revealed.value = (await response.json()) as Revealed;
    } catch {
        revealError.value = 'No fue posible revelar los datos sensibles.';
    } finally {
        revealing.value = false;
    }
}

function submitUpdate(): void {
    form.transform((data) => ({
        ...data,
        customer_group_id: data.customer_group_id === '' ? null : Number(data.customer_group_id),
        default_discount_percent: data.default_discount_percent === '' ? null : data.default_discount_percent,
        max_discount_percent: data.max_discount_percent === '' ? null : data.max_discount_percent,
        trade_name: data.trade_name === '' ? null : data.trade_name,
        rut: data.rut === '' ? null : data.rut,
        email: data.email === '' ? null : data.email,
        phone: data.phone === '' ? null : data.phone,
        business_activity: data.business_activity === '' ? null : data.business_activity,
        fiscal_address: data.fiscal_address === '' ? null : data.fiscal_address,
        fiscal_commune: data.fiscal_commune === '' ? null : data.fiscal_commune,
        fiscal_city: data.fiscal_city === '' ? null : data.fiscal_city,
    })).patch(`/customers/${props.customer.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            revealed.value = null;
        },
    });
}

function changeStatus(action: 'activate' | 'deactivate'): void {
    if (!window.confirm(action === 'activate' ? '¿Activar este cliente?' : '¿Desactivar este cliente sin borrar sus datos?')) {
        return;
    }

    processingStatus.value = true;
    router.post(`/customers/${props.customer.id}/${action}`, {}, {
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
@media (max-width: 720px) { .sf-header, .sf-main { padding: 1rem; } }
</style>
