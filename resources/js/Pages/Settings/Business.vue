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
                <h1 class="sf-title">Datos del comercio</h1>
                <p class="sf-subtitle">
                    Registre la identidad del comercio y los datos de contacto del responsable.
                    Todos los campos son opcionales; puede completarlos o actualizarlos cuando lo necesite.
                </p>

                <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
                <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
                <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
                <div v-if="readOnly" class="sf-message sf-warning" role="status">
                    Modo solo lectura por licencia: no es posible modificar los datos hasta regularizar la licencia.
                </div>
                <div v-if="readOnlyError" class="sf-message sf-error" role="alert">{{ readOnlyError }}</div>

                <div class="sf-message sf-info" role="note">
                    La lectura de los datos del responsable queda registrada en la auditoría del sistema
                    por tratarse de datos personales.
                </div>
                <div class="sf-message sf-info" role="note">
                    Los datos tributarios se usan cuando corresponda a la configuración fiscal del sistema.
                    Esta pantalla no valida el RUT/ID tributario ante el SII ni emite documentos tributarios.
                </div>

                <form class="sf-form" @submit.prevent="submit">
                    <fieldset class="sf-fieldset" :disabled="readOnly">
                        <legend class="sf-legend">Comercio</legend>

                        <div class="sf-field">
                            <label class="sf-label" for="business_name">Nombre del comercio</label>
                            <input
                                id="business_name"
                                v-model="form.business_name"
                                type="text"
                                maxlength="150"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('business_name') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 150 caracteres.</p>
                            <p v-if="errorFor('business_name')" class="sf-error-text">{{ errorFor('business_name') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="fiscal_id">RUT/ID tributario</label>
                            <input
                                id="fiscal_id"
                                v-model="form.fiscal_id"
                                type="text"
                                maxlength="50"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('fiscal_id') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Se guarda como texto, sin validación ante el SII.</p>
                            <p v-if="errorFor('fiscal_id')" class="sf-error-text">{{ errorFor('fiscal_id') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="fiscal_name">Razón social</label>
                            <input
                                id="fiscal_name"
                                v-model="form.fiscal_name"
                                type="text"
                                maxlength="150"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('fiscal_name') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 150 caracteres.</p>
                            <p v-if="errorFor('fiscal_name')" class="sf-error-text">{{ errorFor('fiscal_name') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="commercial_activity">Giro/actividad comercial</label>
                            <input
                                id="commercial_activity"
                                v-model="form.commercial_activity"
                                type="text"
                                maxlength="255"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('commercial_activity') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 255 caracteres.</p>
                            <p v-if="errorFor('commercial_activity')" class="sf-error-text">{{ errorFor('commercial_activity') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="fiscal_address">Dirección tributaria</label>
                            <input
                                id="fiscal_address"
                                v-model="form.fiscal_address"
                                type="text"
                                maxlength="255"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('fiscal_address') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 255 caracteres.</p>
                            <p v-if="errorFor('fiscal_address')" class="sf-error-text">{{ errorFor('fiscal_address') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="fiscal_commune">Comuna</label>
                            <input
                                id="fiscal_commune"
                                v-model="form.fiscal_commune"
                                type="text"
                                maxlength="100"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('fiscal_commune') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 100 caracteres.</p>
                            <p v-if="errorFor('fiscal_commune')" class="sf-error-text">{{ errorFor('fiscal_commune') }}</p>
                        </div>
                    </fieldset>

                    <fieldset class="sf-fieldset" :disabled="readOnly">
                        <legend class="sf-legend">Responsable</legend>

                        <div class="sf-field">
                            <label class="sf-label" for="responsible_name">Nombre</label>
                            <input
                                id="responsible_name"
                                v-model="form.responsible_name"
                                type="text"
                                maxlength="150"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('responsible_name') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 150 caracteres.</p>
                            <p v-if="errorFor('responsible_name')" class="sf-error-text">{{ errorFor('responsible_name') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="responsible_email">Correo</label>
                            <input
                                id="responsible_email"
                                v-model="form.responsible_email"
                                type="email"
                                maxlength="255"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('responsible_email') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Si lo completa, debe ser un correo válido.</p>
                            <p v-if="errorFor('responsible_email')" class="sf-error-text">{{ errorFor('responsible_email') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="responsible_phone">Teléfono</label>
                            <input
                                id="responsible_phone"
                                v-model="form.responsible_phone"
                                type="text"
                                maxlength="50"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('responsible_phone') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Opcional. Máximo 50 caracteres.</p>
                            <p v-if="errorFor('responsible_phone')" class="sf-error-text">{{ errorFor('responsible_phone') }}</p>
                        </div>
                    </fieldset>

                    <div class="sf-actions">
                        <button
                            type="submit"
                            class="sf-button sf-button-primary"
                            :disabled="readOnly || form.processing"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar datos' }}
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

interface BusinessFlash {
    success?: string | null;
    warning?: string | null;
    error?: string | null;
}

interface BusinessPageProps {
    [key: string]: unknown;
    flash?: BusinessFlash;
}

const props = defineProps<{
    business_name: string;
    fiscal_id: string;
    fiscal_name: string;
    commercial_activity: string;
    fiscal_address: string;
    fiscal_commune: string;
    responsible_name: string;
    responsible_email: string;
    responsible_phone: string;
    read_only: boolean;
}>();

const page = usePage<BusinessPageProps>();
const flash = computed<BusinessFlash>(() => page.props.flash ?? {});
const readOnly = computed(() => props.read_only === true);

const form = useForm({
    business_name: props.business_name ?? '',
    fiscal_id: props.fiscal_id ?? '',
    fiscal_name: props.fiscal_name ?? '',
    commercial_activity: props.commercial_activity ?? '',
    fiscal_address: props.fiscal_address ?? '',
    fiscal_commune: props.fiscal_commune ?? '',
    responsible_name: props.responsible_name ?? '',
    responsible_email: props.responsible_email ?? '',
    responsible_phone: props.responsible_phone ?? '',
});

const readOnlyError = computed(() =>
    (form.errors as Record<string, string | undefined>).read_only ?? null
);

function errorFor(field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[field];
}

function submit(): void {
    if (readOnly.value) {
        return;
    }

    form.put('/settings/business');
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

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 760px;
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

.sf-fieldset {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin: 0;
    min-width: 0;
}

.sf-legend {
    padding: 0 0.35rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: #111827;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
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
    box-sizing: border-box;
}

.sf-input:focus,
.sf-input:focus-visible {
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

.sf-hint {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.4;
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

.sf-button:focus-visible {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
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

.sf-info {
    background: #eff6ff;
    color: #1e3a8a;
}

.sf-error {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 640px) {
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

    .sf-actions {
        flex-direction: column-reverse;
    }

    .sf-actions .sf-button {
        width: 100%;
    }
}
</style>
