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
                    <h1 class="sf-title">Importar carga inicial de existencias</h1>
                    <p class="sf-subtitle">
                        Cargue un CSV para registrar la existencia inicial de cada variante en esta sucursal.
                        La vista previa valida el archivo completo y la confirmación es atómica: si una fila falla,
                        no se registra ninguna existencia, movimiento ni auditoría.
                    </p>
                </div>
                <div class="sf-titlebar-side">
                    <p class="sf-branch" aria-live="polite">
                        Sucursal: <strong>{{ branch.name }}</strong> <span class="sf-text-muted">({{ branch.code }})</span>
                    </p>
                    <Link
                        :href="`/inventory/stock/${branch.id}`"
                        class="sf-button sf-button-secondary"
                    >
                        Volver a existencias
                    </Link>
                    <a :href="template_url" class="sf-button sf-button-secondary" download>
                        Descargar plantilla CSV
                    </a>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: puede descargar la plantilla y revisar el formato, pero no se permite
                una vista previa ni confirmar importaciones hasta regularizar la licencia.
            </div>
            <div v-if="!canAdjust" class="sf-message sf-info" role="note">
                Su usuario puede consultar el inventario, pero no importar existencias en esta sucursal. La autorización
                se verifica siempre en el servidor.
            </div>

            <section class="sf-card" aria-labelledby="csv-format-title">
                <h2 id="csv-format-title" class="sf-section-title">Formato y límites del archivo</h2>
                <ul class="sf-help-list">
                    <li>Encabezado exacto de dos columnas: <code>sku</code> y <code>quantity</code> o <code>cantidad</code>.</li>
                    <li>Separador detectado automáticamente: punto y coma (<code>;</code>) si la primera línea lo contiene; en caso contrario coma (<code>,</code>). La plantilla usa <code>;</code> para Excel chileno.</li>
                    <li>Codificación UTF-8 con BOM opcional; saltos de línea CRLF o LF. Los campos pueden ir entre comillas dobles.</li>
                    <li>SKU exacto del catálogo, sin inferencias; hasta {{ limits.max_sku_length }} caracteres. No puede comenzar con <code>=</code>, <code>+</code>, <code>-</code> ni <code>@</code>.</li>
                    <li>Cantidad positiva exacta con hasta 9 enteros y 3 decimales (por ejemplo, <code>12.500</code>). Sin comas, exponentes, cero ni negativos.</li>
                    <li>Máximo {{ limits.max_rows }} filas de datos y {{ formatMaxBytes }}. Un SKU duplicado, desconocido, inactivo, sin control de existencias o con movimientos previos en la sucursal invalida la vista previa.</li>
                    <li>Solo se registra carga inicial. No se aceptan ajustes, costos, precios, datos personales ni sucursales indicadas en el archivo.</li>
                </ul>
            </section>

            <section v-if="canAdjust" class="sf-card" aria-labelledby="csv-upload-title">
                <h2 id="csv-upload-title" class="sf-section-title">1. Seleccionar y validar el archivo</h2>
                <p class="sf-section-hint">
                    El archivo se procesa una vez para la vista previa y se vuelve a validar completo al confirmar.
                    La vista previa enviada por el navegador nunca se reutiliza como fuente de verdad.
                </p>

                <div class="sf-field">
                    <label class="sf-label" for="csv-file">Archivo CSV de carga inicial</label>
                    <input
                        id="csv-file"
                        ref="fileInput"
                        type="file"
                        class="sf-input"
                        accept=".csv,text/csv,text/plain"
                        :disabled="readOnly || form.processing"
                        aria-describedby="csv-file-hint"
                        @change="onFileChange"
                    />
                    <p id="csv-file-hint" class="sf-text-muted">
                        Formatos permitidos: .csv o .txt con contenido CSV. Tamaño máximo {{ formatMaxBytes }}.
                    </p>
                    <p v-if="fileName !== ''" class="sf-text-muted">
                        Archivo seleccionado: <strong>{{ fileName }}</strong>
                    </p>
                    <p v-if="serverErrors.file" class="sf-error" role="alert">{{ serverErrors.file }}</p>
                </div>

                <div class="sf-form-actions">
                    <button
                        type="button"
                        class="sf-button sf-button-primary"
                        :disabled="readOnly || form.processing || form.file === null"
                        @click="submitPreview"
                    >
                        Validar y ver vista previa
                    </button>
                    <span v-if="readOnly" class="sf-text-muted">La licencia en solo lectura impide validar e importar.</span>
                </div>

                <p v-if="serverErrors.read_only" class="sf-error" role="alert">{{ serverErrors.read_only }}</p>
                <p v-if="serverErrors.csv" class="sf-error" role="alert">{{ serverErrors.csv }}</p>
            </section>

            <section v-if="preview" class="sf-card" aria-labelledby="csv-preview-title">
                <h2 id="csv-preview-title" class="sf-section-title">2. Revisar la vista previa</h2>

                <div
                    v-for="(message, index) in preview.global_errors"
                    :key="`global-${index}`"
                    class="sf-message sf-error"
                    role="alert"
                >
                    {{ message }}
                </div>

                <div class="sf-totals" role="status">
                    <span>Filas válidas: <strong>{{ preview.totals.valid }}</strong></span>
                    <span>Filas con error: <strong>{{ preview.totals.invalid }}</strong></span>
                    <span>
                        Cantidad total válida:
                        <strong>{{ preview.totals.total_quantity ?? 'No representable' }}</strong>
                    </span>
                </div>

                <div v-if="preview.rows.length === 0" class="sf-empty-text">
                    No hay filas para mostrar. Corrija los errores globales del archivo y vuelva a validar.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <caption class="sf-visually-hidden">
                            Filas del archivo CSV con línea, SKU, producto o variante, cantidad y estado.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Línea</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Producto / variante</th>
                                <th scope="col">Cantidad</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in preview.rows" :key="`preview-${row.line}`">
                                <td data-label="Línea">{{ row.line }}</td>
                                <td data-label="SKU">{{ row.sku || '—' }}</td>
                                <td data-label="Producto / variante">
                                    <template v-if="row.product">
                                        {{ row.product }}
                                        <span v-if="row.variant" class="sf-text-muted">({{ row.variant }})</span>
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td data-label="Cantidad">{{ row.quantity ?? '—' }}</td>
                                <td data-label="Estado">
                                    <span v-if="row.status === 'ok'" class="sf-badge sf-badge-ok">Válida</span>
                                    <span v-else class="sf-badge sf-badge-error">Error</span>
                                    <p v-if="row.message" class="sf-error" role="alert">{{ row.message }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="preview.has_errors" class="sf-message sf-warning" role="note">
                    Hay errores en el archivo. Para evitar cargas parciales, no se puede confirmar: corrija el CSV y
                    vuelva a generar la vista previa.
                </div>
                <div v-else class="sf-message sf-info" role="note">
                    La confirmación registrará un movimiento inicial por cada SKU y auditoría en una sola transacción.
                    Si cualquier fila o auditoría falla, se revierte todo el lote.
                </div>

                <div class="sf-form-actions">
                    <button
                        type="button"
                        class="sf-button sf-button-primary"
                        :disabled="!canConfirm || form.processing"
                        @click="confirmImport"
                    >
                        Confirmar importación
                    </button>
                    <span v-if="!canConfirm" class="sf-text-muted">
                        Confirme solo después de validar el mismo archivo sin errores.
                    </span>
                </div>
            </section>

            <section v-else-if="canAdjust" class="sf-card" aria-labelledby="csv-empty-title">
                <h2 id="csv-empty-title" class="sf-section-title">Vista previa</h2>
                <div class="sf-empty-text">
                    Aún no hay una vista previa. Seleccione un archivo CSV y use «Validar y ver vista previa».
                </div>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

interface PreviewRow {
    line: number;
    sku: string;
    product: string | null;
    variant: string | null;
    quantity: string | null;
    status: 'ok' | 'error';
    message: string | null;
}

interface Preview {
    rows: PreviewRow[];
    totals: {
        valid: number;
        invalid: number;
        total_quantity: string | null;
    };
    global_errors: string[];
    has_errors: boolean;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { name?: string } | null;
    };
    flash?: {
        success?: string | null;
        warning?: string | null;
        error?: string | null;
    };
}

const props = defineProps<{
    branch: { id: number; code: string; name: string };
    preview: Preview | null;
    can_adjust: boolean;
    can_view_inventory: boolean;
    read_only: boolean;
    limits: {
        max_rows: number;
        max_bytes: number;
        max_sku_length: number;
        max_quantity_length: number;
    };
    template_url: string;
}>();

const page = usePage<SharedPageProps>();
const form = useForm({
    file: null as File | null,
});
const fileInput = ref<HTMLInputElement | null>(null);
const fileName = ref('');
const previewedFile = ref<File | null>(null);

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const canAdjust = computed(() => props.can_adjust === true);
const readOnly = computed(() => props.read_only === true);
const serverErrors = computed(
    () => form.errors as unknown as Record<string, string | undefined>,
);
const formatMaxBytes = computed(() => `${Math.round(props.limits.max_bytes / 1024)} KB`);

const canConfirm = computed(
    () => canAdjust.value
        && !readOnly.value
        && props.preview !== null
        && !props.preview.has_errors
        && props.preview.totals.valid > 0
        && form.file !== null
        && previewedFile.value !== null
        && form.file === previewedFile.value,
);

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;

    form.clearErrors();
    form.file = selected;
    fileName.value = selected?.name ?? '';
    previewedFile.value = null;
}

function submitPreview(): void {
    form.clearErrors();
    form.post(`/inventory/stock/${props.branch.id}/import/preview`, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            previewedFile.value = form.file;
        },
    });
}

function confirmImport(): void {
    form.clearErrors();
    form.post(`/inventory/stock/${props.branch.id}/import/commit`, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
    });
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

.sf-user-name {
    font-size: 0.875rem;
    color: #374151;
}

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.sf-titlebar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}

.sf-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.sf-subtitle {
    margin-top: 0.35rem;
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.5;
    max-width: 820px;
}

.sf-branch {
    font-size: 0.9rem;
    color: #1f2937;
}

.sf-titlebar-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.sf-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sf-section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #111827;
}

.sf-section-hint {
    font-size: 0.8125rem;
    color: #6b7280;
    line-height: 1.5;
}

.sf-help-list {
    margin: 0;
    padding-left: 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.45;
}

.sf-help-list code {
    background: #f3f4f6;
    padding: 0.05rem 0.3rem;
    border-radius: 0.25rem;
}

.sf-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
}

.sf-input {
    width: 100%;
    padding: 0.5rem 0.65rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.9rem;
    background: #ffffff;
    color: #111827;
    box-sizing: border-box;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.sf-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
}

.sf-totals {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 0.65rem 0.85rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #1f2937;
}

.sf-table-wrap {
    overflow-x: auto;
}

.sf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.sf-table th,
.sf-table td {
    padding: 0.6rem 0.65rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
}

.sf-table th {
    background: #f9fafb;
    color: #374151;
    font-weight: 700;
}

.sf-badge {
    display: inline-block;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.sf-badge-ok {
    background: #dcfce7;
    color: #166534;
}

.sf-badge-error {
    background: #fee2e2;
    color: #991b1b;
}

.sf-empty-text {
    padding: 1rem;
    border: 1px dashed #d1d5db;
    border-radius: 0.5rem;
    color: #6b7280;
    font-size: 0.875rem;
}

.sf-message {
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
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
    color: #b91c1c;
    font-size: 0.8125rem;
    font-weight: 600;
}

.sf-text-muted {
    color: #6b7280;
    font-size: 0.8125rem;
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

.sf-button-primary {
    background: #2563eb;
    color: #ffffff;
}

.sf-button-primary:hover {
    background: #1d4ed8;
}

.sf-button-secondary {
    background: #e5e7eb;
    color: #111827;
}

.sf-button-secondary:hover {
    background: #d1d5db;
}

.sf-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sf-link:focus-visible,
.sf-button:focus-visible,
.sf-input:focus-visible {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
}

.sf-visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 760px) {
    .sf-header {
        padding: 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .sf-main {
        padding: 1rem;
    }

    .sf-titlebar-side {
        align-items: flex-start;
    }

    .sf-card {
        padding: 1rem;
    }

    .sf-table thead {
        display: none;
    }

    .sf-table,
    .sf-table tbody,
    .sf-table tr,
    .sf-table td {
        display: block;
        width: 100%;
    }

    .sf-table tr {
        border-bottom: 1px solid #e5e7eb;
        padding: 0.5rem 0;
    }

    .sf-table td {
        border: none;
        padding: 0.3rem 0;
    }

    .sf-table td::before {
        content: attr(data-label);
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
    }
}
</style>
