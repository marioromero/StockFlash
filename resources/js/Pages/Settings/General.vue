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
                <h1 class="sf-title">Preferencias generales</h1>
                <p class="sf-subtitle">
                    Defina la región, la moneda y cómo se presentan los precios del comercio.
                    Esta pantalla no emite documentos tributarios ni recalcula ventas ya registradas.
                </p>

                <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
                <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
                <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
                <div v-if="readOnly" class="sf-message sf-warning" role="status">
                    Modo solo lectura por licencia: no es posible modificar las preferencias hasta regularizar la licencia.
                </div>
                <div v-if="readOnlyError" class="sf-message sf-error" role="alert">{{ readOnlyError }}</div>
                <div v-if="errorFor('payload')" class="sf-message sf-error" role="alert">{{ errorFor('payload') }}</div>

                <form class="sf-form" @submit.prevent="submit">
                    <fieldset class="sf-fieldset" :disabled="readOnly">
                        <legend class="sf-legend">Región y moneda</legend>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="country">País (ISO de dos letras)</label>
                            <input
                                id="country"
                                v-model="form.country"
                                type="text"
                                maxlength="2"
                                autocomplete="country"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': errorFor('country') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Código de país de dos letras, por ejemplo CL.</p>
                            <p v-if="errorFor('country')" class="sf-error-text">{{ errorFor('country') }}</p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="currency_code">Moneda (ISO de tres letras)</label>
                            <input
                                id="currency_code"
                                v-model="form.currency_code"
                                type="text"
                                maxlength="3"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': errorFor('currency_code') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Código de moneda de tres letras, por ejemplo CLP.</p>
                            <p v-if="errorFor('currency_code')" class="sf-error-text">{{ errorFor('currency_code') }}</p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="currency_decimals">Decimales de moneda</label>
                            <input
                                id="currency_decimals"
                                v-model.number="form.currency_decimals"
                                type="number"
                                min="0"
                                max="4"
                                step="1"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('currency_decimals') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Valor entero entre 0 y 4.</p>
                            <p v-if="errorFor('currency_decimals')" class="sf-error-text">{{ errorFor('currency_decimals') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="timezone">Zona horaria</label>
                            <input
                                id="timezone"
                                v-model="form.timezone"
                                type="text"
                                maxlength="64"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('timezone') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Identificador de zona horaria, por ejemplo America/Santiago.</p>
                            <p v-if="errorFor('timezone')" class="sf-error-text">{{ errorFor('timezone') }}</p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="locale">Idioma</label>
                            <input
                                id="locale"
                                v-model="form.locale"
                                type="text"
                                maxlength="5"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': errorFor('locale') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">Formato idioma-región, por ejemplo es-CL.</p>
                            <p v-if="errorFor('locale')" class="sf-error-text">{{ errorFor('locale') }}</p>
                        </div>
                    </fieldset>

                    <fieldset class="sf-fieldset" :disabled="readOnly">
                        <legend class="sf-legend">Precios y efectivo</legend>

                        <div class="sf-field">
                            <label class="sf-label" for="prices_include_tax">Precios con impuesto</label>
                            <select
                                id="prices_include_tax"
                                v-model="form.prices_include_tax"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('prices_include_tax') }"
                                :disabled="readOnly"
                            >
                                <option :value="true">Sí, los precios incluyen impuesto</option>
                                <option :value="false">No, los precios no incluyen impuesto</option>
                            </select>
                            <p class="sf-hint">
                                Indica cómo se interpretan los precios mostrados. No modifica ventas ya registradas.
                            </p>
                            <p v-if="errorFor('prices_include_tax')" class="sf-error-text">{{ errorFor('prices_include_tax') }}</p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="cash_rounding_step">Paso de redondeo del efectivo</label>
                            <input
                                id="cash_rounding_step"
                                v-model.number="form.cash_rounding_step"
                                type="number"
                                min="1"
                                step="1"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('cash_rounding_step') }"
                                :disabled="readOnly"
                            />
                            <p class="sf-hint">
                                Valor entero mínimo 1. En Chile es habitual usar 10.
                            </p>
                            <p v-if="errorFor('cash_rounding_step')" class="sf-error-text">{{ errorFor('cash_rounding_step') }}</p>
                        </div>
                    </fieldset>

                    <div class="sf-actions">
                        <button
                            type="submit"
                            class="sf-button sf-button-primary"
                            :disabled="readOnly || form.processing"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar preferencias' }}
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

interface GeneralFlash {
    success?: string | null;
    warning?: string | null;
    error?: string | null;
}

interface GeneralPageProps {
    [key: string]: unknown;
    flash?: GeneralFlash;
}

const props = defineProps<{
    country: string;
    currency_code: string;
    currency_decimals: number;
    timezone: string;
    locale: string;
    prices_include_tax: boolean;
    cash_rounding_step: number;
    read_only: boolean;
}>();

const page = usePage<GeneralPageProps>();
const flash = computed<GeneralFlash>(() => page.props.flash ?? {});
const readOnly = computed(() => props.read_only === true);

const form = useForm({
    country: props.country ?? 'CL',
    currency_code: props.currency_code ?? 'CLP',
    currency_decimals: props.currency_decimals ?? 0,
    timezone: props.timezone ?? 'America/Santiago',
    locale: props.locale ?? 'es-CL',
    prices_include_tax: props.prices_include_tax ?? true,
    cash_rounding_step: props.cash_rounding_step ?? 1,
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

    form.put('/settings/general');
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
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
    grid-column: 1 / -1;
}

.sf-field-half {
    grid-column: auto;
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

.sf-input-code {
    text-transform: uppercase;
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

    .sf-fieldset {
        grid-template-columns: minmax(0, 1fr);
    }

    .sf-actions {
        flex-direction: column-reverse;
    }

    .sf-actions .sf-button {
        width: 100%;
    }
}
</style>
