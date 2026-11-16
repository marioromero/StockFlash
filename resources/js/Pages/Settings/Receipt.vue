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
                <h1 class="sf-title">Ajustes de comprobante</h1>
                <p class="sf-subtitle">
                    Configure el encabezado, el pie y el tamaño de papel del comprobante interno.
                    Este comprobante no es un documento tributario.
                </p>

                <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
                <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
                <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
                <div v-if="readOnly" class="sf-message sf-warning" role="status">
                    Modo solo lectura por licencia: no es posible modificar los ajustes hasta regularizar la licencia.
                </div>
                <div v-if="readOnlyError" class="sf-message sf-error" role="alert">{{ readOnlyError }}</div>

                <form class="sf-form" @submit.prevent="submit">
                    <fieldset class="sf-fieldset" :disabled="readOnly">
                        <legend class="sf-legend">Datos del comprobante</legend>

                        <div class="sf-field">
                            <label class="sf-label" for="receipt_header">Encabezado</label>
                            <textarea
                                id="receipt_header"
                                v-model="form.receipt_header"
                                rows="4"
                                maxlength="1000"
                                class="sf-input sf-textarea"
                                :class="{ 'sf-input-error': errorFor('receipt_header') }"
                                :disabled="readOnly"
                            ></textarea>
                            <p class="sf-hint">Opcional. Puede dejarlo vacío. Máximo 1000 caracteres.</p>
                            <p v-if="errorFor('receipt_header')" class="sf-error-text">{{ errorFor('receipt_header') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="receipt_footer">Pie</label>
                            <textarea
                                id="receipt_footer"
                                v-model="form.receipt_footer"
                                rows="4"
                                maxlength="1000"
                                class="sf-input sf-textarea"
                                :class="{ 'sf-input-error': errorFor('receipt_footer') }"
                                :disabled="readOnly"
                            ></textarea>
                            <p class="sf-hint">Opcional. Puede dejarlo vacío. Máximo 1000 caracteres.</p>
                            <p v-if="errorFor('receipt_footer')" class="sf-error-text">{{ errorFor('receipt_footer') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="receipt_paper">Tamaño de papel</label>
                            <select
                                id="receipt_paper"
                                v-model="form.receipt_paper"
                                class="sf-input"
                                :class="{ 'sf-input-error': errorFor('receipt_paper') }"
                                :disabled="readOnly"
                            >
                                <option value="58mm">58mm</option>
                                <option value="80mm">80mm</option>
                                <option value="A4">A4</option>
                            </select>
                            <p class="sf-hint">Formato de impresión del comprobante interno.</p>
                            <p v-if="errorFor('receipt_paper')" class="sf-error-text">{{ errorFor('receipt_paper') }}</p>
                        </div>
                    </fieldset>

                    <div class="sf-actions">
                        <button
                            type="submit"
                            class="sf-button sf-button-primary"
                            :disabled="readOnly || form.processing"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar ajustes' }}
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

interface ReceiptFlash {
    success?: string | null;
    warning?: string | null;
    error?: string | null;
}

interface ReceiptPageProps {
    [key: string]: unknown;
    flash?: ReceiptFlash;
}

const props = defineProps<{
    receipt_header: string;
    receipt_footer: string;
    receipt_paper: '58mm' | '80mm' | 'A4';
    read_only: boolean;
}>();

const page = usePage<ReceiptPageProps>();
const flash = computed<ReceiptFlash>(() => page.props.flash ?? {});
const readOnly = computed(() => props.read_only === true);

const form = useForm({
    receipt_header: props.receipt_header ?? '',
    receipt_footer: props.receipt_footer ?? '',
    receipt_paper: props.receipt_paper ?? '80mm',
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

    form.put('/settings/receipt');
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

.sf-textarea {
    resize: vertical;
    font-family: inherit;
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

    .sf-actions {
        flex-direction: column-reverse;
    }

    .sf-actions .sf-button {
        width: 100%;
    }
}
</style>
