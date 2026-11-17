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
                    <h1 class="sf-title">Maestros de catálogo</h1>
                    <p class="sf-subtitle">
                        Administre impuestos, categorías, marcas, etiquetas y atributos comunes a la instalación.
                        Estas tablas se usan después para clasificar productos y variantes.
                    </p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>
            <div v-if="processing" class="sf-message sf-info" role="status">Procesando solicitud...</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Modo solo lectura por licencia: no es posible crear, editar ni eliminar maestros hasta regularizar la licencia.
            </div>
            <div v-if="!canManage" class="sf-message sf-info" role="note">
                Su usuario puede consultar los maestros, pero no modificarlos. La autorización se verifica siempre en el servidor.
            </div>

            <section class="sf-card">
                <h2 class="sf-section-title">Impuestos</h2>
                <p class="sf-section-hint">
                    El porcentaje se guarda en la columna contractual «rate» (0 a 100). Marcar una tasa como
                    predeterminada desmarca automáticamente las demás, en una sola transacción.
                </p>
                <div v-if="taxRates.length === 0" class="sf-empty-text">Aún no hay tasas de impuesto registradas.</div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Porcentaje</th>
                                <th scope="col">Exento</th>
                                <th scope="col">Predeterminada</th>
                                <th scope="col">Código fiscal</th>
                                <th scope="col">Estado</th>
                                <th v-if="canManage" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in taxRates" :key="`tax-${row.id}`">
                                <td data-label="Código"><strong>{{ row.code }}</strong></td>
                                <td data-label="Nombre">{{ row.name }}</td>
                                <td data-label="Porcentaje">{{ row.rate }}%</td>
                                <td data-label="Exento">{{ row.is_exempt ? 'Sí' : 'No' }}</td>
                                <td data-label="Predeterminada">{{ row.is_default ? 'Sí' : 'No' }}</td>
                                <td data-label="Código fiscal">{{ row.fiscal_code ?? '—' }}</td>
                                <td data-label="Estado">{{ row.is_active ? 'Activa' : 'Inactiva' }}</td>
                                <td v-if="canManage" data-label="Acciones" class="sf-actions-cell">
                                    <button type="button" class="sf-link" :disabled="readOnly || processing" @click="editTaxRate(row)">Editar</button>
                                    <button type="button" class="sf-link sf-link-danger" :disabled="readOnly || processing" @click="removeTaxRate(row)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="canManage" class="sf-form" @submit.prevent="submitTaxRate">
                    <h3 class="sf-form-title">{{ editing.taxRate ? 'Editar tasa de impuesto' : 'Nueva tasa de impuesto' }}</h3>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="tax-code">Código</label>
                            <input id="tax-code" v-model="taxRateForm.code" type="text" maxlength="20" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="tax-name">Nombre</label>
                            <input id="tax-name" v-model="taxRateForm.name" type="text" maxlength="80" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="tax-rate">Porcentaje (0–100)</label>
                            <input id="tax-rate" v-model="taxRateForm.rate" type="number" min="0" max="100" step="0.01" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="tax-fiscal">Código fiscal (opcional)</label>
                            <input id="tax-fiscal" v-model="taxRateForm.fiscal_code" type="text" maxlength="10" class="sf-input" />
                        </div>
                    </div>
                    <div class="sf-checks">
                        <label class="sf-check"><input v-model="taxRateForm.is_exempt" type="checkbox" /> Exento</label>
                        <label class="sf-check"><input v-model="taxRateForm.is_default" type="checkbox" /> Predeterminada</label>
                        <label class="sf-check"><input v-model="taxRateForm.is_active" type="checkbox" /> Activa</label>
                    </div>
                    <div class="sf-actions">
                        <button v-if="editing.taxRate" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetTaxRate">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing || readOnly">
                            {{ editing.taxRate ? 'Guardar cambios' : 'Crear tasa' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card">
                <h2 class="sf-section-title">Categorías</h2>
                <p class="sf-section-hint">
                    Jerarquía opcional con padre, slug único, tope de descuento e impuesto predeterminado.
                    No se permiten ciclos ni ser padre de sí misma.
                </p>
                <div v-if="categories.length === 0" class="sf-empty-text">Aún no hay categorías registradas.</div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Padre</th>
                                <th scope="col">Descuento</th>
                                <th scope="col">Impuesto</th>
                                <th scope="col">Orden</th>
                                <th scope="col">Estado</th>
                                <th v-if="canManage" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in categories" :key="`category-${row.id}`">
                                <td data-label="Nombre"><strong>{{ row.name }}</strong></td>
                                <td data-label="Slug">{{ row.slug }}</td>
                                <td data-label="Padre">{{ categoryLabel(row.parent_id) }}</td>
                                <td data-label="Descuento">{{ row.max_discount_percent === null ? '—' : `${row.max_discount_percent}%` }}</td>
                                <td data-label="Impuesto">{{ row.default_tax_rate ? `${row.default_tax_rate.code} — ${row.default_tax_rate.name}` : '—' }}</td>
                                <td data-label="Orden">{{ row.sort_order }}</td>
                                <td data-label="Estado">{{ row.is_active ? 'Activa' : 'Inactiva' }}</td>
                                <td v-if="canManage" data-label="Acciones" class="sf-actions-cell">
                                    <button type="button" class="sf-link" :disabled="readOnly || processing" @click="editCategory(row)">Editar</button>
                                    <button type="button" class="sf-link sf-link-danger" :disabled="readOnly || processing" @click="removeCategory(row)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="canManage" class="sf-form" @submit.prevent="submitCategory">
                    <h3 class="sf-form-title">{{ editing.category ? 'Editar categoría' : 'Nueva categoría' }}</h3>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="category-name">Nombre</label>
                            <input id="category-name" v-model="categoryForm.name" type="text" maxlength="150" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="category-slug">Slug (opcional)</label>
                            <input id="category-slug" v-model="categoryForm.slug" type="text" maxlength="170" class="sf-input" />
                            <p class="sf-hint">Si se omite al crear, se genera desde el nombre. Al editar no se renombra solo.</p>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="category-parent">Categoría padre</label>
                            <select id="category-parent" v-model="categoryForm.parent_id" class="sf-input">
                                <option value="">Sin padre</option>
                                <option v-for="option in categories" :key="`parent-${option.id}`" :value="String(option.id)">
                                    {{ option.name }}
                                </option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="category-tax">Impuesto predeterminado</label>
                            <select id="category-tax" v-model="categoryForm.default_tax_rate_id" class="sf-input">
                                <option value="">Sin impuesto</option>
                                <option v-for="tax in taxRates" :key="`cat-tax-${tax.id}`" :value="String(tax.id)">
                                    {{ tax.code }} — {{ tax.name }}
                                </option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="category-discount">Tope de descuento (0–100)</label>
                            <input id="category-discount" v-model="categoryForm.max_discount_percent" type="number" min="0" max="100" step="0.01" class="sf-input" />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="category-order">Orden (0–65535)</label>
                            <input id="category-order" v-model="categoryForm.sort_order" type="number" min="0" max="65535" step="1" class="sf-input" />
                        </div>
                    </div>
                    <div class="sf-checks">
                        <label class="sf-check"><input v-model="categoryForm.is_active" type="checkbox" /> Activa</label>
                    </div>
                    <div class="sf-actions">
                        <button v-if="editing.category" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetCategory">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing || readOnly">
                            {{ editing.category ? 'Guardar cambios' : 'Crear categoría' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card">
                <h2 class="sf-section-title">Marcas</h2>
                <p class="sf-section-hint">Marca con slug único, descripción y logo opcionales.</p>
                <div v-if="brands.length === 0" class="sf-empty-text">Aún no hay marcas registradas.</div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Descripción</th>
                                <th scope="col">Estado</th>
                                <th v-if="canManage" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in brands" :key="`brand-${row.id}`">
                                <td data-label="Nombre"><strong>{{ row.name }}</strong></td>
                                <td data-label="Slug">{{ row.slug }}</td>
                                <td data-label="Descripción">{{ row.description ?? '—' }}</td>
                                <td data-label="Estado">{{ row.is_active ? 'Activa' : 'Inactiva' }}</td>
                                <td v-if="canManage" data-label="Acciones" class="sf-actions-cell">
                                    <button type="button" class="sf-link" :disabled="readOnly || processing" @click="editBrand(row)">Editar</button>
                                    <button type="button" class="sf-link sf-link-danger" :disabled="readOnly || processing" @click="removeBrand(row)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="canManage" class="sf-form" @submit.prevent="submitBrand">
                    <h3 class="sf-form-title">{{ editing.brand ? 'Editar marca' : 'Nueva marca' }}</h3>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="brand-name">Nombre</label>
                            <input id="brand-name" v-model="brandForm.name" type="text" maxlength="150" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="brand-slug">Slug (opcional)</label>
                            <input id="brand-slug" v-model="brandForm.slug" type="text" maxlength="170" class="sf-input" />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="brand-description">Descripción (opcional)</label>
                            <input id="brand-description" v-model="brandForm.description" type="text" maxlength="255" class="sf-input" />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="brand-logo">URL del logo (opcional)</label>
                            <input id="brand-logo" v-model="brandForm.logo_url" type="text" maxlength="500" class="sf-input" />
                        </div>
                    </div>
                    <div class="sf-checks">
                        <label class="sf-check"><input v-model="brandForm.is_active" type="checkbox" /> Activa</label>
                    </div>
                    <div class="sf-actions">
                        <button v-if="editing.brand" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetBrand">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing || readOnly">
                            {{ editing.brand ? 'Guardar cambios' : 'Crear marca' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card">
                <h2 class="sf-section-title">Etiquetas</h2>
                <p class="sf-section-hint">Etiquetas simples con slug único.</p>
                <div v-if="tags.length === 0" class="sf-empty-text">Aún no hay etiquetas registradas.</div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Slug</th>
                                <th v-if="canManage" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in tags" :key="`tag-${row.id}`">
                                <td data-label="Nombre"><strong>{{ row.name }}</strong></td>
                                <td data-label="Slug">{{ row.slug }}</td>
                                <td v-if="canManage" data-label="Acciones" class="sf-actions-cell">
                                    <button type="button" class="sf-link" :disabled="readOnly || processing" @click="editTag(row)">Editar</button>
                                    <button type="button" class="sf-link sf-link-danger" :disabled="readOnly || processing" @click="removeTag(row)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="canManage" class="sf-form" @submit.prevent="submitTag">
                    <h3 class="sf-form-title">{{ editing.tag ? 'Editar etiqueta' : 'Nueva etiqueta' }}</h3>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="tag-name">Nombre</label>
                            <input id="tag-name" v-model="tagForm.name" type="text" maxlength="100" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="tag-slug">Slug (opcional)</label>
                            <input id="tag-slug" v-model="tagForm.slug" type="text" maxlength="120" class="sf-input" />
                        </div>
                    </div>
                    <div class="sf-actions">
                        <button v-if="editing.tag" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetTag">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing || readOnly">
                            {{ editing.tag ? 'Guardar cambios' : 'Crear etiqueta' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="sf-card">
                <h2 class="sf-section-title">Atributos y valores</h2>
                <p class="sf-section-hint">
                    Cada atributo tiene un nombre único y sus valores son únicos dentro del atributo.
                    Eliminar un atributo con valores o referencias se bloquea para no romper datos.
                </p>
                <div v-if="attributes.length === 0" class="sf-empty-text">Aún no hay atributos registrados.</div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Atributo</th>
                                <th scope="col">Valores</th>
                                <th v-if="canManage" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in attributes" :key="`attribute-${row.id}`">
                                <td data-label="Atributo"><strong>{{ row.name }}</strong></td>
                                <td data-label="Valores">
                                    <span v-if="row.values.length === 0" class="sf-text-muted">Sin valores</span>
                                    <template v-else>
                                        <span v-for="value in row.values" :key="`value-${value.id}`" class="sf-value-chip">
                                            {{ value.value }}
                                            <template v-if="canManage">
                                                <button type="button" class="sf-chip-action" :disabled="readOnly || processing" @click="editAttributeValue(row.id, value)">Editar</button>
                                                <button type="button" class="sf-chip-action sf-chip-danger" :disabled="readOnly || processing" @click="removeAttributeValue(value)">Quitar</button>
                                            </template>
                                        </span>
                                    </template>
                                </td>
                                <td v-if="canManage" data-label="Acciones" class="sf-actions-cell">
                                    <button type="button" class="sf-link" :disabled="readOnly || processing" @click="editAttribute(row)">Editar</button>
                                    <button type="button" class="sf-link sf-link-danger" :disabled="readOnly || processing" @click="removeAttribute(row)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="canManage" class="sf-form" @submit.prevent="submitAttribute">
                    <h3 class="sf-form-title">{{ editing.attribute ? 'Editar atributo' : 'Nuevo atributo' }}</h3>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="attribute-name">Nombre del atributo</label>
                            <input id="attribute-name" v-model="attributeForm.name" type="text" maxlength="100" class="sf-input" required />
                        </div>
                    </div>
                    <div class="sf-actions">
                        <button v-if="editing.attribute" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetAttribute">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing || readOnly">
                            {{ editing.attribute ? 'Guardar cambios' : 'Crear atributo' }}
                        </button>
                    </div>
                </form>

                <form v-if="canManage" class="sf-form" @submit.prevent="submitAttributeValue">
                    <h3 class="sf-form-title">{{ editing.attributeValue ? 'Editar valor de atributo' : 'Nuevo valor de atributo' }}</h3>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="attribute-value-attribute">Atributo</label>
                            <select id="attribute-value-attribute" v-model="attributeValueForm.attribute_id" class="sf-input" required>
                                <option value="">Seleccione un atributo</option>
                                <option v-for="attribute in attributes" :key="`attr-value-${attribute.id}`" :value="String(attribute.id)">
                                    {{ attribute.name }}
                                </option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="attribute-value-value">Valor</label>
                            <input id="attribute-value-value" v-model="attributeValueForm.value" type="text" maxlength="100" class="sf-input" required />
                        </div>
                    </div>
                    <div class="sf-actions">
                        <button v-if="editing.attributeValue" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetAttributeValue">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing || readOnly">
                            {{ editing.attributeValue ? 'Guardar cambios' : 'Crear valor' }}
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface TaxRateRow {
    id: number;
    code: string;
    name: string;
    rate: string;
    is_exempt: boolean;
    is_default: boolean;
    fiscal_code: string | null;
    is_active: boolean;
}

interface CategoryRow {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    max_discount_percent: string | null;
    default_tax_rate_id: number | null;
    default_tax_rate: { id: number; code: string; name: string } | null;
    sort_order: number;
    is_active: boolean;
}

interface BrandRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    is_active: boolean;
}

interface TagRow {
    id: number;
    name: string;
    slug: string;
}

interface AttributeValueRow {
    id: number;
    value: string;
}

interface AttributeRow {
    id: number;
    name: string;
    values: AttributeValueRow[];
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
    errors?: Record<string, string>;
}

type Payload = Record<string, string | number | boolean | null>;

const props = defineProps<{
    tax_rates: TaxRateRow[];
    categories: CategoryRow[];
    brands: BrandRow[];
    tags: TagRow[];
    attributes: AttributeRow[];
    can_manage: boolean;
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const processing = ref(false);
const actionError = ref<string | null>(null);

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const canManage = computed(() => props.can_manage === true);
const readOnly = computed(() => props.read_only === true);
const errorMessage = computed(() => {
    if (actionError.value) {
        return actionError.value;
    }

    const validationErrors = page.props.errors ?? {};

    return Object.values(validationErrors)[0] ?? flash.value.error ?? null;
});

const taxRates = computed(() => props.tax_rates);
const categories = computed(() => props.categories);
const brands = computed(() => props.brands);
const tags = computed(() => props.tags);
const attributes = computed(() => props.attributes);

const editing = reactive<{
    taxRate: number | null;
    category: number | null;
    brand: number | null;
    tag: number | null;
    attribute: number | null;
    attributeValue: number | null;
}>({
    taxRate: null,
    category: null,
    brand: null,
    tag: null,
    attribute: null,
    attributeValue: null,
});

const taxRateForm = reactive({
    code: '',
    name: '',
    rate: '',
    is_exempt: false,
    is_default: false,
    fiscal_code: '',
    is_active: true,
});

const categoryForm = reactive({
    parent_id: '',
    name: '',
    slug: '',
    max_discount_percent: '',
    default_tax_rate_id: '',
    sort_order: '0',
    is_active: true,
});

const brandForm = reactive({
    name: '',
    slug: '',
    description: '',
    logo_url: '',
    is_active: true,
});

const tagForm = reactive({
    name: '',
    slug: '',
});

const attributeForm = reactive({
    name: '',
});

const attributeValueForm = reactive({
    attribute_id: '',
    value: '',
});

function categoryLabel(parentId: number | null): string {
    if (parentId === null) {
        return '—';
    }

    return categories.value.find((category) => category.id === parentId)?.name ?? `#${parentId}`;
}

function send(method: 'post' | 'put' | 'delete', url: string, data: Payload | null, onDone?: () => void): void {
    actionError.value = '';
    processing.value = true;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            onDone?.();
        },
        onError: (errors: Record<string, string>) => {
            actionError.value = Object.values(errors)[0] ?? 'No se pudo completar la operación.';
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (method === 'post') {
        router.post(url, data ?? {}, options);
    } else if (method === 'put') {
        router.put(url, data ?? {}, options);
    } else {
        router.delete(url, options);
    }
}

function resetTaxRate(): void {
    editing.taxRate = null;
    Object.assign(taxRateForm, {
        code: '', name: '', rate: '', is_exempt: false, is_default: false, fiscal_code: '', is_active: true,
    });
}

function editTaxRate(row: TaxRateRow): void {
    editing.taxRate = row.id;
    Object.assign(taxRateForm, {
        code: row.code,
        name: row.name,
        rate: row.rate,
        is_exempt: row.is_exempt,
        is_default: row.is_default,
        fiscal_code: row.fiscal_code ?? '',
        is_active: row.is_active,
    });
}

function submitTaxRate(): void {
    const payload: Payload = {
        code: taxRateForm.code,
        name: taxRateForm.name,
        rate: taxRateForm.rate,
        is_exempt: taxRateForm.is_exempt,
        is_default: taxRateForm.is_default,
        fiscal_code: taxRateForm.fiscal_code === '' ? null : taxRateForm.fiscal_code,
        is_active: taxRateForm.is_active,
    };
    const url = editing.taxRate
        ? `/catalog/masters/tax-rates/${editing.taxRate}`
        : '/catalog/masters/tax-rates';

    send(editing.taxRate ? 'put' : 'post', url, payload, resetTaxRate);
}

function removeTaxRate(row: TaxRateRow): void {
    if (!window.confirm(`¿Eliminar la tasa "${row.code} — ${row.name}"?`)) {
        return;
    }

    send('delete', `/catalog/masters/tax-rates/${row.id}`, null, resetTaxRate);
}

function resetCategory(): void {
    editing.category = null;
    Object.assign(categoryForm, {
        parent_id: '', name: '', slug: '', max_discount_percent: '', default_tax_rate_id: '', sort_order: '0', is_active: true,
    });
}

function editCategory(row: CategoryRow): void {
    editing.category = row.id;
    Object.assign(categoryForm, {
        parent_id: row.parent_id === null ? '' : String(row.parent_id),
        name: row.name,
        slug: row.slug,
        max_discount_percent: row.max_discount_percent ?? '',
        default_tax_rate_id: row.default_tax_rate_id === null ? '' : String(row.default_tax_rate_id),
        sort_order: String(row.sort_order),
        is_active: row.is_active,
    });
}

function submitCategory(): void {
    const payload: Payload = {
        parent_id: categoryForm.parent_id === '' ? null : Number(categoryForm.parent_id),
        name: categoryForm.name,
        slug: categoryForm.slug === '' ? null : categoryForm.slug,
        max_discount_percent: categoryForm.max_discount_percent === '' ? null : Number(categoryForm.max_discount_percent),
        default_tax_rate_id: categoryForm.default_tax_rate_id === '' ? null : Number(categoryForm.default_tax_rate_id),
        sort_order: categoryForm.sort_order === '' ? 0 : Number(categoryForm.sort_order),
        is_active: categoryForm.is_active,
    };
    const url = editing.category
        ? `/catalog/masters/categories/${editing.category}`
        : '/catalog/masters/categories';

    send(editing.category ? 'put' : 'post', url, payload, resetCategory);
}

function removeCategory(row: CategoryRow): void {
    if (!window.confirm(`¿Eliminar la categoría "${row.name}"?`)) {
        return;
    }

    send('delete', `/catalog/masters/categories/${row.id}`, null, resetCategory);
}

function resetBrand(): void {
    editing.brand = null;
    Object.assign(brandForm, { name: '', slug: '', description: '', logo_url: '', is_active: true });
}

function editBrand(row: BrandRow): void {
    editing.brand = row.id;
    Object.assign(brandForm, {
        name: row.name,
        slug: row.slug,
        description: row.description ?? '',
        logo_url: row.logo_url ?? '',
        is_active: row.is_active,
    });
}

function submitBrand(): void {
    const payload: Payload = {
        name: brandForm.name,
        slug: brandForm.slug === '' ? null : brandForm.slug,
        description: brandForm.description === '' ? null : brandForm.description,
        logo_url: brandForm.logo_url === '' ? null : brandForm.logo_url,
        is_active: brandForm.is_active,
    };
    const url = editing.brand
        ? `/catalog/masters/brands/${editing.brand}`
        : '/catalog/masters/brands';

    send(editing.brand ? 'put' : 'post', url, payload, resetBrand);
}

function removeBrand(row: BrandRow): void {
    if (!window.confirm(`¿Eliminar la marca "${row.name}"?`)) {
        return;
    }

    send('delete', `/catalog/masters/brands/${row.id}`, null, resetBrand);
}

function resetTag(): void {
    editing.tag = null;
    Object.assign(tagForm, { name: '', slug: '' });
}

function editTag(row: TagRow): void {
    editing.tag = row.id;
    Object.assign(tagForm, { name: row.name, slug: row.slug });
}

function submitTag(): void {
    const payload: Payload = {
        name: tagForm.name,
        slug: tagForm.slug === '' ? null : tagForm.slug,
    };
    const url = editing.tag
        ? `/catalog/masters/tags/${editing.tag}`
        : '/catalog/masters/tags';

    send(editing.tag ? 'put' : 'post', url, payload, resetTag);
}

function removeTag(row: TagRow): void {
    if (!window.confirm(`¿Eliminar la etiqueta "${row.name}"?`)) {
        return;
    }

    send('delete', `/catalog/masters/tags/${row.id}`, null, resetTag);
}

function resetAttribute(): void {
    editing.attribute = null;
    Object.assign(attributeForm, { name: '' });
}

function editAttribute(row: AttributeRow): void {
    editing.attribute = row.id;
    Object.assign(attributeForm, { name: row.name });
}

function submitAttribute(): void {
    const url = editing.attribute
        ? `/catalog/masters/attributes/${editing.attribute}`
        : '/catalog/masters/attributes';

    send(editing.attribute ? 'put' : 'post', url, { name: attributeForm.name }, resetAttribute);
}

function removeAttribute(row: AttributeRow): void {
    if (!window.confirm(`¿Eliminar el atributo "${row.name}"?`)) {
        return;
    }

    send('delete', `/catalog/masters/attributes/${row.id}`, null, resetAttribute);
}

function resetAttributeValue(): void {
    editing.attributeValue = null;
    Object.assign(attributeValueForm, { attribute_id: '', value: '' });
}

function editAttributeValue(attributeId: number, value: AttributeValueRow): void {
    editing.attributeValue = value.id;
    Object.assign(attributeValueForm, { attribute_id: String(attributeId), value: value.value });
}

function submitAttributeValue(): void {
    const payload: Payload = {
        attribute_id: attributeValueForm.attribute_id === '' ? null : Number(attributeValueForm.attribute_id),
        value: attributeValueForm.value,
    };
    const url = editing.attributeValue
        ? `/catalog/masters/attribute-values/${editing.attributeValue}`
        : '/catalog/masters/attribute-values';

    send(editing.attributeValue ? 'put' : 'post', url, payload, resetAttributeValue);
}

function removeAttributeValue(value: AttributeValueRow): void {
    if (!window.confirm(`¿Eliminar el valor "${value.value}"?`)) {
        return;
    }

    send('delete', `/catalog/masters/attribute-values/${value.id}`, null, resetAttributeValue);
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
    max-width: 1180px;
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
}

.sf-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sf-section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #111827;
}

.sf-section-hint {
    margin: 0.35rem 0 1rem;
    font-size: 0.8125rem;
    color: #6b7280;
    line-height: 1.5;
}

.sf-empty-text {
    padding: 1rem;
    border: 1px dashed #d1d5db;
    border-radius: 0.5rem;
    color: #6b7280;
    font-size: 0.875rem;
}

.sf-table-wrap {
    overflow-x: auto;
    margin-bottom: 1.25rem;
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

.sf-actions-heading,
.sf-actions-cell {
    white-space: nowrap;
}

.sf-actions-cell .sf-link + .sf-link {
    margin-left: 0.65rem;
}

.sf-link {
    background: none;
    border: none;
    padding: 0;
    color: #1d4ed8;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}

.sf-link:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sf-link-danger {
    color: #b91c1c;
}

.sf-link:focus-visible,
.sf-chip-action:focus-visible,
.sf-button:focus-visible,
.sf-input:focus-visible {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
}

.sf-form {
    border-top: 1px solid #e5e7eb;
    padding-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.sf-form-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
}

.sf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.9rem;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
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

.sf-input:focus {
    border-color: #2563eb;
    outline: 2px solid #bfdbfe;
    outline-offset: 0;
}

.sf-hint {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.4;
}

.sf-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.sf-check {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8125rem;
    color: #374151;
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

.sf-text-muted {
    color: #6b7280;
    font-size: 0.8125rem;
}

.sf-value-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0 0.35rem 0.35rem 0;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 0.75rem;
}

.sf-chip-action {
    border: none;
    background: none;
    padding: 0;
    color: #1d4ed8;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: underline;
}

.sf-chip-danger {
    color: #b91c1c;
}

@media (max-width: 720px) {
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

    .sf-actions {
        flex-direction: column-reverse;
    }

    .sf-actions .sf-button {
        width: 100%;
    }
}
</style>
