<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-card">
                <h1 class="sf-title">{{ isEditing ? 'Editar sucursal' : 'Nueva sucursal' }}</h1>
                <p class="sf-subtitle">
                    {{ isEditing
                        ? 'Actualice los datos operativos de la sucursal. El código no se puede modificar.'
                        : 'Registre un local, una bodega o una sucursal online para operar en StockFlash.' }}
                </p>

                <div v-if="clientError" class="sf-message sf-error" role="alert">{{ clientError }}</div>
                <div v-if="generalError" class="sf-message sf-error" role="alert">{{ generalError }}</div>

                <form class="sf-form" @submit.prevent="submit">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="code">Código de sucursal *</label>
                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                maxlength="30"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('code') }"
                                :readonly="isEditing"
                                :disabled="isEditing"
                                autocomplete="off"
                                required
                            />
                            <p class="sf-hint">Mayúsculas, números y guiones. Máximo 30 caracteres.</p>
                            <p v-if="isEditing" class="sf-hint sf-hint-warning">
                                El código es inmutable porque se usa como prefijo de numeración (RN-74).
                            </p>
                            <p v-if="errorFor('code')" class="sf-error-text">{{ errorFor('code') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="name">Nombre *</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                maxlength="150"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('name') }"
                                required
                            />
                            <p v-if="errorFor('name')" class="sf-error-text">{{ errorFor('name') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="type">Tipo *</label>
                            <select id="type" v-model="form.type" class="sf-input" :class="{ 'sf-input-error': errorFor('type') }" required>
                                <option value="store">Local</option>
                                <option value="warehouse">Bodega</option>
                                <option value="online">Online</option>
                            </select>
                            <p v-if="errorFor('type')" class="sf-error-text">{{ errorFor('type') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="phone">Teléfono</label>
                            <input id="phone" v-model="form.phone" type="text" maxlength="30" class="sf-input" />
                            <p v-if="errorFor('phone')" class="sf-error-text">{{ errorFor('phone') }}</p>
                        </div>

                        <div class="sf-field sf-field-wide">
                            <label class="sf-label" for="address">Dirección</label>
                            <input id="address" v-model="form.address" type="text" maxlength="255" class="sf-input" />
                            <p v-if="errorFor('address')" class="sf-error-text">{{ errorFor('address') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="commune">Comuna</label>
                            <input id="commune" v-model="form.commune" type="text" maxlength="100" class="sf-input" />
                            <p v-if="errorFor('commune')" class="sf-error-text">{{ errorFor('commune') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="city">Ciudad</label>
                            <input id="city" v-model="form.city" type="text" maxlength="100" class="sf-input" />
                            <p v-if="errorFor('city')" class="sf-error-text">{{ errorFor('city') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="region">Región</label>
                            <input id="region" v-model="form.region" type="text" maxlength="100" class="sf-input" />
                            <p v-if="errorFor('region')" class="sf-error-text">{{ errorFor('region') }}</p>
                        </div>
                    </div>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Estado y operación</legend>

                        <template v-if="isEditing">
                            <p class="sf-status">
                                <span class="sf-badge" :class="branch?.is_active ? 'sf-badge-active' : 'sf-badge-inactive'">
                                    {{ branch?.is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                                <span class="sf-status-text">Estado actual</span>
                            </p>
                            <p class="sf-hint">
                                El estado no se edita en este formulario. Para cambiarlo use las acciones
                                <strong>Activar</strong> o <strong>Desactivar</strong> del listado de sucursales.
                            </p>
                        </template>

                        <label v-else class="sf-check">
                            <input v-model="form.is_active" type="checkbox" />
                            <span>Sucursal activa</span>
                        </label>

                        <label class="sf-check">
                            <input v-model="form.requires_cash_control" type="checkbox" />
                            <span>Requiere control de caja</span>
                        </label>

                        <p v-if="errorFor('is_active')" class="sf-error-text">{{ errorFor('is_active') }}</p>
                        <p v-if="errorFor('requires_cash_control')" class="sf-error-text">{{ errorFor('requires_cash_control') }}</p>
                    </fieldset>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Ajustes heredables</legend>
                        <p class="sf-hint">
                            Seleccione <strong>Heredar</strong> para usar la configuración general del sistema. No se aplican valores por defecto distintos del contrato.
                        </p>

                        <div class="sf-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="sales_flow_mode">Flujo de venta</label>
                                <select id="sales_flow_mode" v-model="form.sales_flow_mode" class="sf-input">
                                    <option value="">Heredar</option>
                                    <option value="direct">Directo</option>
                                    <option value="two_step">En dos pasos</option>
                                    <option value="both">Ambos</option>
                                </select>
                                <p v-if="errorFor('sales_flow_mode')" class="sf-error-text">{{ errorFor('sales_flow_mode') }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="seller_code_policy">Código de vendedor</label>
                                <select id="seller_code_policy" v-model="form.seller_code_policy" class="sf-input">
                                    <option value="">Heredar</option>
                                    <option value="none">No solicitarlo</option>
                                    <option value="optional">Opcional</option>
                                    <option value="required">Obligatorio</option>
                                </select>
                                <p v-if="errorFor('seller_code_policy')" class="sf-error-text">{{ errorFor('seller_code_policy') }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="allow_negative_stock">Stock negativo</label>
                                <select id="allow_negative_stock" v-model="form.allow_negative_stock" class="sf-input">
                                    <option :value="null">Heredar</option>
                                    <option :value="true">Permitir</option>
                                    <option :value="false">No permitir</option>
                                </select>
                                <p v-if="errorFor('allow_negative_stock')" class="sf-error-text">{{ errorFor('allow_negative_stock') }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Ajustes avanzados (JSON)</legend>
                        <div class="sf-field">
                            <label class="sf-label" for="settings">Configuración adicional</label>
                            <textarea
                                id="settings"
                                v-model="form.settings"
                                rows="6"
                                class="sf-input sf-textarea"
                                :class="{ 'sf-input-error': errorFor('settings') }"
                                placeholder='Opcional. Debe ser un objeto JSON, por ejemplo: {"clave": "valor"}'
                            ></textarea>
                            <p class="sf-hint">
                                Opcional. Solo se acepta un objeto JSON; no se permiten listas, valores sueltos, contraseñas, tokens ni secretos.
                            </p>
                            <p v-if="errorFor('settings')" class="sf-error-text">{{ errorFor('settings') }}</p>
                        </div>
                    </fieldset>

                    <div class="sf-actions">
                        <button type="button" class="sf-button sf-button-secondary" :disabled="form.processing" @click="cancel">
                            Cancelar
                        </button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                            {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear sucursal') }}
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

interface BranchData {
    id: number;
    code: string;
    name: string;
    type: 'store' | 'warehouse' | 'online';
    address: string | null;
    commune: string | null;
    city: string | null;
    region: string | null;
    phone: string | null;
    is_active: boolean;
    requires_cash_control: boolean;
    sales_flow_mode: 'direct' | 'two_step' | 'both' | null;
    seller_code_policy: 'none' | 'optional' | 'required' | null;
    allow_negative_stock: boolean | null;
    settings: Record<string, unknown> | null;
}

const props = defineProps<{
    branch: BranchData | null;
}>();

const isEditing = computed(() => props.branch !== null);
const clientError = ref<string | null>(null);

const form = useForm({
    code: props.branch?.code ?? '',
    name: props.branch?.name ?? '',
    type: props.branch?.type ?? 'store',
    address: props.branch?.address ?? '',
    commune: props.branch?.commune ?? '',
    city: props.branch?.city ?? '',
    region: props.branch?.region ?? '',
    phone: props.branch?.phone ?? '',
    is_active: props.branch?.is_active ?? true,
    requires_cash_control: props.branch?.requires_cash_control ?? true,
    sales_flow_mode: props.branch?.sales_flow_mode ?? '',
    seller_code_policy: props.branch?.seller_code_policy ?? '',
    allow_negative_stock: props.branch?.allow_negative_stock ?? null,
    settings: serializeSettings(props.branch?.settings ?? null),
});

const generalError = computed(() => {
    const keys = ['max_branches', 'general'] as const;

    for (const key of keys) {
        const message = errorFor(key);

        if (message) {
            return message;
        }
    }

    return null;
});

function errorFor(field: string): string | undefined {
    return (form.errors as Record<string, string>)[field];
}

function serializeSettings(settings: Record<string, unknown> | null): string {
    if (!settings) {
        return '';
    }

    if (Object.keys(settings).length === 0) {
        return '{}';
    }

    return JSON.stringify(settings, null, 2);
}

function validateSettings(): boolean {
    const raw = form.settings.trim();

    if (raw === '') {
        return true;
    }

    try {
        const parsed = JSON.parse(raw);

        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            clientError.value = 'Los ajustes deben ser un objeto JSON, no una lista ni un valor suelto.';
            return false;
        }
    } catch {
        clientError.value = 'Los ajustes no contienen un JSON válido.';
        return false;
    }

    clientError.value = null;

    return true;
}

function submit(): void {
    clientError.value = null;

    if (!validateSettings()) {
        return;
    }

    if (props.branch) {
        // El estado activo no viaja en la edición: se cambia solo con las
        // acciones dedicadas Activar/Desactivar del listado.
        form.transform((data) => {
            const { is_active: _ignored, ...payload } = data;

            return payload;
        }).put(`/branches/${props.branch.id}`);
    } else {
        form.post('/branches');
    }
}

function cancel(): void {
    router.get('/branches');
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

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    padding: 2rem;
}

.sf-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sf-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.sf-subtitle {
    margin: 0.35rem 0 1.25rem;
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.5;
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.sf-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sf-field-wide {
    grid-column: 1 / -1;
}

.sf-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.sf-input {
    width: 100%;
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

.sf-input:disabled {
    background: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
}

.sf-input-error {
    border-color: #dc2626;
}

.sf-textarea {
    resize: vertical;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.85rem;
}

.sf-fieldset {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sf-legend {
    padding: 0 0.35rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: #111827;
}

.sf-check {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.9rem;
    color: #374151;
}

.sf-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sf-status-text {
    font-size: 0.875rem;
    color: #374151;
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

.sf-hint {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.4;
}

.sf-hint-warning {
    color: #92400e;
}

.sf-error-text {
    font-size: 0.75rem;
    color: #b91c1c;
}

.sf-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
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

.sf-error {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 700px) {
    .sf-header {
        padding: 1rem;
    }

    .sf-main {
        padding: 1rem;
    }

    .sf-card {
        padding: 1rem;
    }

    .sf-grid {
        grid-template-columns: 1fr;
    }

    .sf-actions {
        flex-direction: column-reverse;
    }

    .sf-actions .sf-button {
        width: 100%;
    }
}
</style>
