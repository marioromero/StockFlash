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
                    <h1 class="sf-title">Productos</h1>
                    <p class="sf-subtitle">
                        Administre productos, sus variantes, códigos de barras, valores de atributo y etiquetas.
                        El catálogo es común a la instalación y los permisos se verifican siempre en el servidor.
                    </p>
                </div>
                <button
                    v-if="canManage && !readOnly"
                    type="button"
                    class="sf-button sf-button-primary"
                    @click="startProductCreate"
                >
                    Nuevo producto
                </button>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="flash.warning" class="sf-message sf-warning" role="status">{{ flash.warning }}</div>
            <div v-if="flash.error" class="sf-message sf-error" role="alert">{{ flash.error }}</div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>
            <div v-if="processing" class="sf-message sf-info" role="status">Procesando solicitud...</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Modo solo lectura por licencia: no es posible crear, editar ni eliminar productos hasta regularizar la licencia.
            </div>
            <div v-if="!canManage" class="sf-message sf-info" role="note">
                Su usuario puede consultar el catálogo, pero no modificarlo. La autorización se verifica siempre en el servidor.
            </div>

            <section class="sf-card" aria-labelledby="products-list-title">
                <h2 id="products-list-title" class="sf-section-title">Listado de productos</h2>
                <form class="sf-search" role="search" @submit.prevent="search">
                    <label class="sf-label" for="product-search">Buscar por nombre, SKU, código interno o código de barras</label>
                    <div class="sf-search-row">
                        <input
                            id="product-search"
                            v-model="searchTerm"
                            type="search"
                            maxlength="100"
                            class="sf-input"
                            placeholder="Ej.: arroz, SKU-123 o 7801234567890"
                        />
                        <button type="submit" class="sf-button sf-button-primary" :disabled="processing">
                            Buscar
                        </button>
                        <button
                            v-if="activeFilter !== ''"
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processing"
                            @click="clearSearch"
                        >
                            Limpiar
                        </button>
                    </div>
                </form>

                <p class="sf-text-muted" role="status">
                    {{ products.total }} producto(s) encontrado(s).
                    <template v-if="products.from !== null && products.to !== null">
                        Mostrando {{ products.from }}–{{ products.to }}.
                    </template>
                </p>

                <div v-if="products.data.length === 0" class="sf-empty-text">
                    No hay productos que coincidan con la búsqueda.
                </div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Código interno</th>
                                <th scope="col">Categoría / marca</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in products.data" :key="`product-${row.id}`">
                                <td data-label="Producto">
                                    <strong>{{ row.name }}</strong>
                                    <div class="sf-text-muted">
                                        <span v-for="tag in row.tags" :key="`row-tag-${row.id}-${tag.id}`" class="sf-chip">{{ tag.name }}</span>
                                    </div>
                                </td>
                                <td data-label="Tipo">{{ typeLabel(row.type) }}</td>
                                <td data-label="SKU">{{ row.skus.join(', ') || '—' }}</td>
                                <td data-label="Código interno">{{ row.internal_code ?? '—' }}</td>
                                <td data-label="Categoría / marca">
                                    {{ row.category_name ?? 'Sin categoría' }} / {{ row.brand_name ?? 'Sin marca' }}
                                </td>
                                <td data-label="Estado">{{ row.is_active ? 'Activo' : 'Inactivo' }}</td>
                                <td data-label="Acciones" class="sf-actions-cell">
                                    <Link :href="`/catalog/products/${row.id}`" class="sf-link">Ver detalle</Link>
                                    <button
                                        v-if="canManage && !readOnly"
                                        type="button"
                                        class="sf-link"
                                        :disabled="processing"
                                        @click="editProduct(row.id)"
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="products.last_page > 1" class="sf-pagination" aria-label="Paginación de productos">
                    <template v-for="link in products.links" :key="`page-${link.label}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="sf-page-link"
                            :class="{ 'sf-page-link-active': link.active }"
                            :aria-current="link.active ? 'page' : undefined"
                            preserve-scroll
                            preserve-state
                            v-html="link.label"
                        />
                        <span v-else class="sf-page-link sf-page-link-disabled" aria-disabled="true" v-html="link.label" />
                    </template>
                </nav>
            </section>

            <section v-if="selected" class="sf-card" aria-labelledby="product-detail-title">
                <div class="sf-titlebar">
                    <div>
                        <h2 id="product-detail-title" class="sf-section-title">{{ selected.name }}</h2>
                        <p class="sf-text-muted">
                            {{ typeLabel(selected.type) }} · {{ selected.is_active ? 'Activo' : 'Inactivo' }} ·
                            Origen: {{ originLabel(selected.origin) }} (informativo)
                        </p>
                    </div>
                    <div class="sf-actions">
                        <button
                            v-if="canManage && !readOnly"
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processing"
                            @click="startProductEdit"
                        >
                            Editar producto
                        </button>
                        <button
                            v-if="canManage && !readOnly"
                            type="button"
                            class="sf-button sf-button-danger"
                            :disabled="processing"
                            @click="removeProduct"
                        >
                            Eliminar producto
                        </button>
                    </div>
                </div>

                <dl class="sf-definition-grid">
                    <div>
                        <dt>Código interno</dt>
                        <dd>{{ selected.internal_code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Categoría</dt>
                        <dd>{{ catalogLabel(catalogs.categories, selected.category_id) }}</dd>
                    </div>
                    <div>
                        <dt>Marca</dt>
                        <dd>{{ catalogLabel(catalogs.brands, selected.brand_id) }}</dd>
                    </div>
                    <div>
                        <dt>Tasa de impuesto</dt>
                        <dd>{{ taxRateLabel(selected.tax_rate_id) }}</dd>
                    </div>
                    <div>
                        <dt>Controla stock</dt>
                        <dd>{{ selected.tracks_stock ? 'Sí' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt>Vendible</dt>
                        <dd>{{ selected.is_sellable ? 'Sí' : 'No' }}</dd>
                    </div>
                    <div class="sf-definition-wide">
                        <dt>Descripción</dt>
                        <dd>{{ selected.description ?? '—' }}</dd>
                    </div>
                    <div v-if="selected.image_url" class="sf-definition-wide">
                        <dt>URL de imagen</dt>
                        <dd class="sf-break">{{ selected.image_url }}</dd>
                    </div>
                    <div class="sf-definition-wide">
                        <dt>Etiquetas</dt>
                        <dd>
                            <span v-if="selected.tags.length === 0" class="sf-text-muted">Sin etiquetas.</span>
                            <span v-for="tag in selected.tags" :key="`detail-tag-${tag.id}`" class="sf-chip">{{ tag.name }}</span>
                        </dd>
                    </div>
                </dl>

                <h3 class="sf-form-title">Variantes</h3>
                <p class="sf-section-hint">
                    Un producto simple debe tener exactamente una variante; uno variable, al menos dos.
                    El costo se edita solo con el permiso de costos y nunca se reemplaza por un valor enviado sin ese permiso.
                </p>
                <div v-if="selected.variants.length === 0" class="sf-empty-text">Este producto no tiene variantes activas.</div>
                <div v-else class="sf-table-wrap">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th scope="col">SKU</th>
                                <th scope="col">Precio</th>
                                <th v-if="can_view_cost" scope="col">Costo</th>
                                <th v-if="can_view_cost" scope="col">Margen / markup</th>
                                <th scope="col">Unidad</th>
                                <th scope="col">Atributos</th>
                                <th scope="col">Códigos</th>
                                <th scope="col">Estado</th>
                                <th v-if="canManage" scope="col" class="sf-actions-heading">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="variant in selected.variants" :key="`variant-${variant.id}`">
                                <td data-label="SKU"><strong>{{ variant.sku }}</strong></td>
                                <td data-label="Precio">{{ money(variant.price) }}</td>
                                <td v-if="can_view_cost" data-label="Costo">
                                    {{ money(variant.cost_price ?? '0') }}
                                    <div class="sf-text-muted">Último costo: {{ variant.last_cost === null || variant.last_cost === undefined ? '—' : money(variant.last_cost) }}</div>
                                </td>
                                <td v-if="can_view_cost" data-label="Margen / markup">
                                    {{ money(variant.margin ?? '0') }}
                                    <div class="sf-text-muted">
                                        Margen {{ variant.margin_percent === null || variant.margin_percent === undefined ? '—' : `${variant.margin_percent}%` }}
                                        · markup {{ variant.markup_percent === null || variant.markup_percent === undefined ? '—' : `${variant.markup_percent}%` }}
                                    </div>
                                    <div class="sf-text-muted">
                                        Neto {{ money(variant.net_price ?? variant.price) }} ·
                                        tasa {{ variant.tax_is_exempt ? 'exenta' : `${variant.effective_tax_rate ?? '0.00'}%` }}
                                    </div>
                                </td>
                                <td data-label="Unidad">{{ unitLabel(variant.unit) }}</td>
                                <td data-label="Atributos">
                                    <span v-if="variant.attribute_values.length === 0" class="sf-text-muted">—</span>
                                    <span
                                        v-for="value in variant.attribute_values"
                                        :key="`attr-${variant.id}-${value.id}`"
                                        class="sf-chip"
                                    >
                                        {{ value.attribute_name ?? 'Atributo' }}: {{ value.value }}
                                    </span>
                                </td>
                                <td data-label="Códigos">
                                    <span v-if="variant.barcodes.length === 0" class="sf-text-muted">—</span>
                                    <span v-for="barcode in variant.barcodes" :key="`bc-${barcode.id}`" class="sf-chip">
                                        {{ barcode.barcode }}<template v-if="barcode.is_primary"> (principal)</template>
                                        <template v-if="canManage && !readOnly">
                                            <button type="button" class="sf-chip-action" :disabled="processing" @click="editBarcode(variant.id, barcode)">Editar</button>
                                            <button type="button" class="sf-chip-action sf-chip-danger" :disabled="processing" @click="removeBarcode(variant, barcode)">Quitar</button>
                                        </template>
                                    </span>
                                </td>
                                <td data-label="Estado">{{ variant.is_active ? 'Activa' : 'Inactiva' }}</td>
                                <td v-if="canManage" data-label="Acciones" class="sf-actions-cell">
                                    <button type="button" class="sf-link" :disabled="processing || readOnly" @click="editVariant(variant)">Editar</button>
                                    <button type="button" class="sf-link" :disabled="processing || readOnly" @click="startBarcodeCreate(variant.id)">Agregar código</button>
                                    <button type="button" class="sf-link sf-link-danger" :disabled="processing || readOnly" @click="removeVariant(variant)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    v-if="canManage && !readOnly && (selected.type !== 'simple' || variantEditingId !== null)"
                    class="sf-form"
                    @submit.prevent="submitVariant"
                >
                    <h4 class="sf-form-title">{{ variantEditingId === null ? 'Nueva variante' : 'Editar variante' }}</h4>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="variant-sku">SKU</label>
                            <input id="variant-sku" v-model="variantForm.sku" type="text" maxlength="100" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="variant-price">Precio base</label>
                            <input id="variant-price" v-model="variantForm.price" type="number" min="0" step="0.01" class="sf-input" required />
                        </div>
                        <div v-if="can_view_cost" class="sf-field">
                            <label class="sf-label" for="variant-cost">Costo neto</label>
                            <input id="variant-cost" v-model="variantForm.cost_price" type="number" min="0" step="0.0001" class="sf-input" />
                            <p class="sf-hint">El costo anterior (`last_cost`) solo se muestra como referencia y no se edita aquí.</p>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="variant-unit">Unidad</label>
                            <select id="variant-unit" v-model="variantForm.unit" class="sf-input">
                                <option v-for="unit in units" :key="`unit-${unit.value}`" :value="unit.value">{{ unit.label }}</option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="variant-tax">Tasa de impuesto</label>
                            <select id="variant-tax" v-model="variantForm.tax_rate_id" class="sf-input">
                                <option value="">Heredar del producto / predeterminada</option>
                                <option v-for="tax in catalogs.tax_rates" :key="`variant-tax-${tax.id}`" :value="String(tax.id)">
                                    {{ tax.code }} — {{ tax.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="can_view_cost" class="sf-field sf-field-wide">
                            <div class="sf-calculator">
                                <p class="sf-calculator-title">Calculadora de precio (sugerencia)</p>
                                <p class="sf-section-hint">
                                    Usa el costo neto y la tasa efectiva. El resultado reemplaza el campo Precio base;
                                    usted puede ajustarlo antes de guardar. El servidor valida el precio al guardar.
                                </p>
                                <div class="sf-grid">
                                    <div class="sf-field">
                                        <label class="sf-label" for="variant-calc-mode">Calcular por</label>
                                        <select id="variant-calc-mode" v-model="variantForm.calculator_mode" class="sf-input">
                                            <option value="margin">Margen (%)</option>
                                            <option value="markup">Markup (%)</option>
                                        </select>
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="variant-calc-percent">Objetivo (%)</label>
                                        <input id="variant-calc-percent" v-model="variantForm.calculator_percent" type="number" min="0" step="0.01" class="sf-input" />
                                    </div>
                                    <div class="sf-field sf-calculator-action">
                                        <button type="button" class="sf-button sf-button-secondary" @click="calculateVariantPrice">
                                            Calcular precio
                                        </button>
                                    </div>
                                </div>
                                <p class="sf-hint">{{ taxHint(variantForm.tax_rate_id, selectedProductTaxRateId) }}</p>
                                <p v-if="variantCalculatorError" class="sf-error-text" role="alert">{{ variantCalculatorError }}</p>
                            </div>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="variant-discount">Descuento máximo (%)</label>
                            <input id="variant-discount" v-model="variantForm.max_discount_percent" type="number" min="0" max="100" step="0.01" class="sf-input" />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="variant-min-price">Precio mínimo</label>
                            <input id="variant-min-price" v-model="variantForm.min_price" type="number" min="0" step="0.01" class="sf-input" />
                        </div>
                    </div>
                    <div class="sf-checks">
                        <label class="sf-check"><input v-model="variantForm.sold_by_weight" type="checkbox" /> Se vende por peso</label>
                        <label class="sf-check"><input v-model="variantForm.allow_price_override" type="checkbox" /> Permite cambiar precio</label>
                        <label class="sf-check"><input v-model="variantForm.combo_deducts_components" type="checkbox" /> Deduce componentes (solo dato contractual)</label>
                        <label class="sf-check"><input v-model="variantForm.is_active" type="checkbox" /> Activa</label>
                    </div>
                    <fieldset class="sf-fieldset">
                        <legend class="sf-label">Valores de atributo (máximo uno por atributo)</legend>
                        <div v-for="attribute in catalogs.attributes" :key="`attr-group-${attribute.id}`" class="sf-attribute-group">
                            <p class="sf-attribute-title">{{ attribute.name }}</p>
                            <label v-for="value in attribute.values" :key="`attr-value-${attribute.id}-${value.id}`" class="sf-check">
                                <input
                                    type="checkbox"
                                    :checked="variantForm.attribute_value_ids.includes(value.id)"
                                    @change="toggleAttributeValue(attribute, value.id, $event)"
                                />
                                {{ value.value }}
                            </label>
                        </div>
                    </fieldset>
                    <div class="sf-actions">
                        <button v-if="variantEditingId !== null" type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetVariantForm">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="mutationDisabled">
                            {{ variantEditingId === null ? 'Agregar variante' : 'Guardar variante' }}
                        </button>
                    </div>
                </form>

                <form
                    v-if="canManage && !readOnly && barcodeTargetVariantId !== null"
                    class="sf-form"
                    @submit.prevent="submitBarcode"
                >
                    <h4 class="sf-form-title">{{ barcodeEditingId === null ? 'Nuevo código de barras' : 'Editar código de barras' }}</h4>
                    <p class="sf-section-hint">Código único global. Al marcar uno como principal, los demás de la misma variante se desplazan en la misma transacción.</p>
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="barcode-value">Código de barras</label>
                            <input id="barcode-value" v-model="barcodeForm.barcode" type="text" maxlength="64" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="barcode-type">Tipo</label>
                            <select id="barcode-type" v-model="barcodeForm.type" class="sf-input">
                                <option v-for="type in barcodeTypes" :key="`barcode-type-${type.value}`" :value="type.value">{{ type.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="sf-checks">
                        <label class="sf-check"><input v-model="barcodeForm.is_primary" type="checkbox" /> Código principal de la variante</label>
                    </div>
                    <div class="sf-actions">
                        <button type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="resetBarcodeForm">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="mutationDisabled">
                            {{ barcodeEditingId === null ? 'Agregar código' : 'Guardar código' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="canManage && !readOnly && productMode !== null" class="sf-card" aria-labelledby="product-form-title">
                <h2 id="product-form-title" class="sf-section-title">
                    {{ productMode === 'create' ? 'Nuevo producto' : 'Editar producto' }}
                </h2>
                <form class="sf-form sf-form-plain" @submit.prevent="submitProduct">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="product-name">Nombre</label>
                            <input id="product-name" v-model="productForm.name" type="text" maxlength="255" class="sf-input" required />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="product-type">Tipo</label>
                            <select id="product-type" v-model="productForm.type" class="sf-input" @change="onProductTypeChange">
                                <option value="simple">Simple</option>
                                <option value="variable">Variable</option>
                                <option value="combo">Combo (solo dato contractual)</option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="product-internal-code">Código interno</label>
                            <input id="product-internal-code" v-model="productForm.internal_code" type="text" maxlength="60" class="sf-input" />
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="product-category">Categoría</label>
                            <select id="product-category" v-model="productForm.category_id" class="sf-input">
                                <option value="">Sin categoría</option>
                                <option v-for="category in catalogs.categories" :key="`product-cat-${category.id}`" :value="String(category.id)">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="product-brand">Marca</label>
                            <select id="product-brand" v-model="productForm.brand_id" class="sf-input">
                                <option value="">Sin marca</option>
                                <option v-for="brand in catalogs.brands" :key="`product-brand-${brand.id}`" :value="String(brand.id)">
                                    {{ brand.name }}
                                </option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="product-tax">Tasa de impuesto</label>
                            <select id="product-tax" v-model="productForm.tax_rate_id" class="sf-input">
                                <option value="">Sin tasa</option>
                                <option v-for="tax in catalogs.tax_rates" :key="`product-tax-${tax.id}`" :value="String(tax.id)">
                                    {{ tax.code }} — {{ tax.name }}
                                </option>
                            </select>
                        </div>
                        <div class="sf-field">
                            <label class="sf-label" for="product-image">URL de imagen</label>
                            <input id="product-image" v-model="productForm.image_url" type="text" maxlength="500" class="sf-input" />
                        </div>
                        <div class="sf-field sf-field-wide">
                            <label class="sf-label" for="product-description">Descripción</label>
                            <textarea id="product-description" v-model="productForm.description" maxlength="5000" class="sf-input" rows="3" />
                        </div>
                    </div>
                    <div class="sf-checks">
                        <label class="sf-check"><input v-model="productForm.tracks_stock" type="checkbox" /> Controla stock (dato de catálogo, no crea inventario)</label>
                        <label class="sf-check"><input v-model="productForm.is_sellable" type="checkbox" /> Vendible</label>
                        <label class="sf-check"><input v-model="productForm.is_active" type="checkbox" /> Activo</label>
                    </div>
                    <fieldset class="sf-fieldset">
                        <legend class="sf-label">Etiquetas</legend>
                        <span v-if="catalogs.tags.length === 0" class="sf-text-muted">No hay etiquetas registradas.</span>
                        <label v-for="tag in catalogs.tags" :key="`product-tag-${tag.id}`" class="sf-check">
                            <input v-model="productForm.tag_ids" type="checkbox" :value="tag.id" /> {{ tag.name }}
                        </label>
                    </fieldset>

                    <fieldset v-if="productMode === 'create'" class="sf-fieldset">
                        <legend class="sf-label">Variantes iniciales</legend>
                        <p class="sf-section-hint">
                            Simple: exactamente una. Variable: al menos dos. Combo: al menos una, sin composición funcional P1.
                        </p>
                        <div v-for="(variant, index) in productForm.variants" :key="`draft-${index}`" class="sf-draft-variant">
                            <div class="sf-grid">
                                <div class="sf-field">
                                    <label class="sf-label" :for="`draft-sku-${index}`">SKU</label>
                                    <input :id="`draft-sku-${index}`" v-model="variant.sku" type="text" maxlength="100" class="sf-input" required />
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" :for="`draft-price-${index}`">Precio base</label>
                                    <input :id="`draft-price-${index}`" v-model="variant.price" type="number" min="0" step="0.01" class="sf-input" required />
                                </div>
                                <div v-if="can_view_cost" class="sf-field">
                                    <label class="sf-label" :for="`draft-cost-${index}`">Costo neto</label>
                                    <input :id="`draft-cost-${index}`" v-model="variant.cost_price" type="number" min="0" step="0.0001" class="sf-input" />
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" :for="`draft-unit-${index}`">Unidad</label>
                                    <select :id="`draft-unit-${index}`" v-model="variant.unit" class="sf-input">
                                        <option v-for="unit in units" :key="`draft-unit-${index}-${unit.value}`" :value="unit.value">{{ unit.label }}</option>
                                    </select>
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" :for="`draft-tax-${index}`">Tasa</label>
                                    <select :id="`draft-tax-${index}`" v-model="variant.tax_rate_id" class="sf-input">
                                        <option value="">Heredar del producto / predeterminada</option>
                                        <option v-for="tax in catalogs.tax_rates" :key="`draft-tax-${index}-${tax.id}`" :value="String(tax.id)">
                                            {{ tax.code }} — {{ tax.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="can_view_cost" class="sf-calculator">
                                <p class="sf-calculator-title">Calculadora de precio (sugerencia)</p>
                                <div class="sf-grid">
                                    <div class="sf-field">
                                        <label class="sf-label" :for="`draft-calc-mode-${index}`">Calcular por</label>
                                        <select :id="`draft-calc-mode-${index}`" v-model="variant.calculator_mode" class="sf-input">
                                            <option value="margin">Margen (%)</option>
                                            <option value="markup">Markup (%)</option>
                                        </select>
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" :for="`draft-calc-percent-${index}`">Objetivo (%)</label>
                                        <input :id="`draft-calc-percent-${index}`" v-model="variant.calculator_percent" type="number" min="0" step="0.01" class="sf-input" />
                                    </div>
                                    <div class="sf-field sf-calculator-action">
                                        <button type="button" class="sf-button sf-button-secondary" @click="calculateDraftPrice(index)">
                                            Calcular precio
                                        </button>
                                    </div>
                                </div>
                                <p class="sf-hint">{{ taxHint(variant.tax_rate_id, productForm.tax_rate_id) }}</p>
                                <p v-if="draftCalculatorErrors[index]" class="sf-error-text" role="alert">{{ draftCalculatorErrors[index] }}</p>
                            </div>
                            <div class="sf-checks">
                                <label class="sf-check"><input v-model="variant.sold_by_weight" type="checkbox" /> Por peso</label>
                                <label class="sf-check"><input v-model="variant.allow_price_override" type="checkbox" /> Permite cambiar precio</label>
                                <label class="sf-check"><input v-model="variant.is_active" type="checkbox" /> Activa</label>
                            </div>
                            <button
                                v-if="productForm.variants.length > minimumDraftVariants"
                                type="button"
                                class="sf-link sf-link-danger"
                                @click="removeDraftVariant(index)"
                            >
                                Quitar variante
                            </button>
                        </div>
                        <button type="button" class="sf-button sf-button-secondary" @click="addDraftVariant">Agregar otra variante</button>
                    </fieldset>

                    <div class="sf-actions">
                        <button type="button" class="sf-button sf-button-secondary" :disabled="processing" @click="cancelProductForm">Cancelar</button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="mutationDisabled">
                            {{ productMode === 'create' ? 'Crear producto' : 'Guardar cambios' }}
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TagOption {
    id: number;
    name: string;
}

interface ProductRow {
    id: number;
    name: string;
    internal_code: string | null;
    type: string;
    category_id: number | null;
    category_name: string | null;
    brand_id: number | null;
    brand_name: string | null;
    is_active: boolean;
    is_sellable: boolean;
    tracks_stock: boolean;
    origin: string;
    variant_count: number;
    skus: string[];
    tags: TagOption[];
}

interface BarcodeRow {
    id: number;
    barcode: string;
    type: string;
    is_primary: boolean;
}

interface VariantRow {
    id: number;
    sku: string;
    price: string;
    unit: string;
    sold_by_weight: boolean;
    max_discount_percent: string | null;
    min_price: string | null;
    allow_price_override: boolean;
    tax_rate_id: number | null;
    combo_deducts_components: boolean;
    is_active: boolean;
    attribute_value_ids: number[];
    attribute_values: {
        id: number;
        attribute_id: number;
        attribute_name: string | null;
        value: string;
    }[];
    barcodes: BarcodeRow[];
    cost_price?: string;
    last_cost?: string | null;
    net_price?: string;
    margin?: string;
    margin_percent?: string | null;
    markup_percent?: string | null;
    effective_tax_rate?: string;
    tax_is_exempt?: boolean;
    tax_source?: string;
}

interface ProductDetail {
    id: number;
    category_id: number | null;
    brand_id: number | null;
    type: string;
    name: string;
    description: string | null;
    internal_code: string | null;
    image_url: string | null;
    tax_rate_id: number | null;
    tracks_stock: boolean;
    is_sellable: boolean;
    is_active: boolean;
    origin: string;
    tag_ids: number[];
    tags: TagOption[];
    variants: VariantRow[];
}

interface AttributeValueOption {
    id: number;
    value: string;
}

interface AttributeOption {
    id: number;
    name: string;
    values: AttributeValueOption[];
}

interface TaxRateOption {
    id: number;
    code: string;
    name: string;
    rate: string;
    is_exempt: boolean;
    is_default: boolean;
}

interface Catalogs {
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
    tax_rates: TaxRateOption[];
    tags: TagOption[];
    attributes: AttributeOption[];
}

interface ProductPage {
    data: ProductRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface DraftVariantForm {
    sku: string;
    price: string;
    cost_price: string;
    unit: string;
    sold_by_weight: boolean;
    max_discount_percent: string;
    min_price: string;
    allow_price_override: boolean;
    tax_rate_id: string;
    combo_deducts_components: boolean;
    is_active: boolean;
    calculator_mode: 'margin' | 'markup';
    calculator_percent: string;
}

interface VariantFormState extends DraftVariantForm {
    attribute_value_ids: number[];
}

interface ProductFormState {
    name: string;
    type: string;
    internal_code: string;
    category_id: string;
    brand_id: string;
    tax_rate_id: string;
    image_url: string;
    description: string;
    tracks_stock: boolean;
    is_sellable: boolean;
    is_active: boolean;
    tag_ids: number[];
    variants: DraftVariantForm[];
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

type Payload = Record<string, any>;

const props = defineProps<{
    products: ProductPage;
    filters: { q: string };
    selected: ProductDetail | null;
    catalogs: Catalogs;
    can_manage: boolean;
    can_view_cost: boolean;
    pricing: { prices_include_tax: boolean };
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const processing = ref(false);
const actionError = ref<string | null>(null);
const searchTerm = ref(props.filters.q);
const productMode = ref<'create' | 'edit' | null>(null);
const variantEditingId = ref<number | null>(null);
const barcodeTargetVariantId = ref<number | null>(null);
const barcodeEditingId = ref<number | null>(null);
const variantCalculatorError = ref('');
const draftCalculatorErrors = ref<Record<number, string>>({});

const units = [
    { value: 'unit', label: 'Unidad' },
    { value: 'kg', label: 'Kilogramo' },
    { value: 'g', label: 'Gramo' },
    { value: 'lt', label: 'Litro' },
    { value: 'ml', label: 'Mililitro' },
    { value: 'm', label: 'Metro' },
    { value: 'box', label: 'Caja' },
    { value: 'other', label: 'Otra' },
];

const barcodeTypes = [
    { value: 'ean13', label: 'EAN-13' },
    { value: 'ean8', label: 'EAN-8' },
    { value: 'upc', label: 'UPC' },
    { value: 'code128', label: 'Code 128' },
    { value: 'internal', label: 'Interno' },
    { value: 'other', label: 'Otro' },
];

const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const canManage = computed(() => props.can_manage === true);
const canViewCost = computed(() => props.can_view_cost === true);
const readOnly = computed(() => props.read_only === true);
const mutationDisabled = computed(() => processing.value || readOnly.value);
const activeFilter = computed(() => props.filters.q ?? '');
const errorMessage = computed(() => {
    if (actionError.value) {
        return actionError.value;
    }

    const validationErrors = page.props.errors ?? {};

    return Object.values(validationErrors)[0] ?? flash.value.error ?? null;
});

const minimumDraftVariants = computed(() => (productForm.type === 'variable' ? 2 : 1));

const selectedProductTaxRateId = computed(() =>
    props.selected?.tax_rate_id === null || props.selected?.tax_rate_id === undefined
        ? ''
        : String(props.selected.tax_rate_id),
);

const productForm = reactive<ProductFormState>(emptyProductForm());

const variantForm = reactive<VariantFormState>(emptyVariantForm());

const barcodeForm = reactive({
    barcode: '',
    type: 'ean13',
    is_primary: false,
});

watch(
    () => props.filters.q,
    (value) => {
        searchTerm.value = value ?? '';
    },
);

watch(
    () => props.selected?.id,
    () => {
        productMode.value = null;
        resetVariantForm();
        resetBarcodeForm();
    },
);

function emptyDraftVariant(): DraftVariantForm {
    return {
        sku: '',
        price: '',
        cost_price: '',
        unit: 'unit',
        sold_by_weight: false,
        max_discount_percent: '',
        min_price: '',
        allow_price_override: true,
        tax_rate_id: '',
        combo_deducts_components: true,
        is_active: true,
        calculator_mode: 'margin',
        calculator_percent: '',
    };
}

function emptyProductForm(): ProductFormState {
    return {
        name: '',
        type: 'simple',
        internal_code: '',
        category_id: '',
        brand_id: '',
        tax_rate_id: '',
        image_url: '',
        description: '',
        tracks_stock: true,
        is_sellable: true,
        is_active: true,
        tag_ids: [],
        variants: [emptyDraftVariant()],
    };
}

function emptyVariantForm(): VariantFormState {
    return {
        ...emptyDraftVariant(),
        attribute_value_ids: [],
    };
}

function resetProductForm(): void {
    Object.assign(productForm, emptyProductForm());
    draftCalculatorErrors.value = {};
}

function resetVariantForm(): void {
    variantEditingId.value = null;
    variantCalculatorError.value = '';
    Object.assign(variantForm, emptyVariantForm());
}

function resetBarcodeForm(): void {
    barcodeTargetVariantId.value = null;
    barcodeEditingId.value = null;
    Object.assign(barcodeForm, { barcode: '', type: 'ean13', is_primary: false });
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

function search(): void {
    router.get(
        '/catalog/products',
        { q: searchTerm.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearSearch(): void {
    searchTerm.value = '';
    search();
}

function startProductCreate(): void {
    resetProductForm();
    productMode.value = 'create';
}

function editProduct(id: number): void {
    router.visit(`/catalog/products/${id}`);
}

function startProductEdit(): void {
    if (!props.selected) {
        return;
    }

    const selected = props.selected;

    draftCalculatorErrors.value = {};
    Object.assign(productForm, {
        name: selected.name,
        type: selected.type,
        internal_code: selected.internal_code ?? '',
        category_id: selected.category_id === null ? '' : String(selected.category_id),
        brand_id: selected.brand_id === null ? '' : String(selected.brand_id),
        tax_rate_id: selected.tax_rate_id === null ? '' : String(selected.tax_rate_id),
        image_url: selected.image_url ?? '',
        description: selected.description ?? '',
        tracks_stock: selected.tracks_stock,
        is_sellable: selected.is_sellable,
        is_active: selected.is_active,
        tag_ids: [...selected.tag_ids],
        variants: [emptyDraftVariant()],
    });
    productMode.value = 'edit';
}

function cancelProductForm(): void {
    productMode.value = null;
    resetProductForm();
}

function onProductTypeChange(): void {
    syncDraftVariants();
}

function syncDraftVariants(): void {
    const minimum = minimumDraftVariants.value;

    while (productForm.variants.length < minimum) {
        productForm.variants.push(emptyDraftVariant());
    }

    if (productForm.type === 'simple' && productForm.variants.length > 1) {
        productForm.variants.splice(1);
    }
}

function addDraftVariant(): void {
    productForm.variants.push(emptyDraftVariant());
    draftCalculatorErrors.value = {};
}

function removeDraftVariant(index: number): void {
    if (productForm.variants.length <= minimumDraftVariants.value) {
        return;
    }

    productForm.variants.splice(index, 1);
    draftCalculatorErrors.value = {};
}

function draftVariantPayload(variant: DraftVariantForm): Payload {
    const payload: Payload = {
        sku: variant.sku,
        price: variant.price,
        unit: variant.unit,
        sold_by_weight: variant.sold_by_weight,
        max_discount_percent: variant.max_discount_percent === '' ? null : variant.max_discount_percent,
        min_price: variant.min_price === '' ? null : variant.min_price,
        allow_price_override: variant.allow_price_override,
        tax_rate_id: variant.tax_rate_id === '' ? null : Number(variant.tax_rate_id),
        combo_deducts_components: variant.combo_deducts_components,
        is_active: variant.is_active,
    };

    if (canViewCost.value && variant.cost_price !== '') {
        payload.cost_price = variant.cost_price;
    }

    return payload;
}

function productPayload(includeVariants: boolean): Payload {
    const payload: Payload = {
        name: productForm.name,
        type: productForm.type,
        internal_code: productForm.internal_code === '' ? null : productForm.internal_code,
        category_id: productForm.category_id === '' ? null : Number(productForm.category_id),
        brand_id: productForm.brand_id === '' ? null : Number(productForm.brand_id),
        tax_rate_id: productForm.tax_rate_id === '' ? null : Number(productForm.tax_rate_id),
        image_url: productForm.image_url === '' ? null : productForm.image_url,
        description: productForm.description === '' ? null : productForm.description,
        tracks_stock: productForm.tracks_stock,
        is_sellable: productForm.is_sellable,
        is_active: productForm.is_active,
        tag_ids: [...productForm.tag_ids],
    };

    if (includeVariants) {
        payload.variants = productForm.variants.map((variant) => draftVariantPayload(variant));
    }

    return payload;
}

function submitProduct(): void {
    const creating = productMode.value === 'create';
    const url = creating
        ? '/catalog/products'
        : `/catalog/products/${props.selected?.id ?? 0}`;

    send(
        creating ? 'post' : 'put',
        url,
        productPayload(creating),
        () => {
            productMode.value = null;
            resetProductForm();
        },
    );
}

function removeProduct(): void {
    if (!props.selected || !window.confirm(`¿Eliminar lógicamente el producto "${props.selected.name}"?`)) {
        return;
    }

    send('delete', `/catalog/products/${props.selected.id}`, null);
}

function editVariant(variant: VariantRow): void {
    variantEditingId.value = variant.id;
    Object.assign(variantForm, {
        sku: variant.sku,
        price: variant.price,
        cost_price: variant.cost_price ?? '',
        unit: variant.unit,
        sold_by_weight: variant.sold_by_weight,
        max_discount_percent: variant.max_discount_percent ?? '',
        min_price: variant.min_price ?? '',
        allow_price_override: variant.allow_price_override,
        tax_rate_id: variant.tax_rate_id === null ? '' : String(variant.tax_rate_id),
        combo_deducts_components: variant.combo_deducts_components,
        is_active: variant.is_active,
        calculator_mode: 'margin',
        calculator_percent: '',
        attribute_value_ids: [...variant.attribute_value_ids],
    });
    variantCalculatorError.value = '';
}

function variantPayload(): Payload {
    const payload: Payload = {
        sku: variantForm.sku,
        price: variantForm.price,
        unit: variantForm.unit,
        sold_by_weight: variantForm.sold_by_weight,
        max_discount_percent: variantForm.max_discount_percent === '' ? null : variantForm.max_discount_percent,
        min_price: variantForm.min_price === '' ? null : variantForm.min_price,
        allow_price_override: variantForm.allow_price_override,
        tax_rate_id: variantForm.tax_rate_id === '' ? null : Number(variantForm.tax_rate_id),
        combo_deducts_components: variantForm.combo_deducts_components,
        is_active: variantForm.is_active,
        attribute_value_ids: [...variantForm.attribute_value_ids],
    };

    if (canViewCost.value && variantForm.cost_price !== '') {
        payload.cost_price = variantForm.cost_price;
    }

    return payload;
}

function submitVariant(): void {
    if (!props.selected) {
        return;
    }

    const editing = variantEditingId.value !== null;
    const url = editing
        ? `/catalog/products/${props.selected.id}/variants/${variantEditingId.value}`
        : `/catalog/products/${props.selected.id}/variants`;

    send(editing ? 'put' : 'post', url, variantPayload(), resetVariantForm);
}

function removeVariant(variant: VariantRow): void {
    if (!props.selected || !window.confirm(`¿Eliminar lógicamente la variante "${variant.sku}"?`)) {
        return;
    }

    send('delete', `/catalog/products/${props.selected.id}/variants/${variant.id}`, null, resetVariantForm);
}

function toggleAttributeValue(attribute: AttributeOption, valueId: number, event: Event): void {
    const target = event.target as HTMLInputElement;
    const selectedInAttribute = new Set(attribute.values.map((value) => value.id));

    variantForm.attribute_value_ids = variantForm.attribute_value_ids.filter(
        (id) => !selectedInAttribute.has(id),
    );

    if (target.checked) {
        variantForm.attribute_value_ids.push(valueId);
    }
}

function startBarcodeCreate(variantId: number): void {
    barcodeTargetVariantId.value = variantId;
    barcodeEditingId.value = null;
    Object.assign(barcodeForm, { barcode: '', type: 'ean13', is_primary: false });
}

function editBarcode(variantId: number, barcode: BarcodeRow): void {
    barcodeTargetVariantId.value = variantId;
    barcodeEditingId.value = barcode.id;
    Object.assign(barcodeForm, {
        barcode: barcode.barcode,
        type: barcode.type,
        is_primary: barcode.is_primary,
    });
}

function submitBarcode(): void {
    if (!props.selected || barcodeTargetVariantId.value === null) {
        return;
    }

    const editing = barcodeEditingId.value !== null;
    const base = `/catalog/products/${props.selected.id}/variants/${barcodeTargetVariantId.value}/barcodes`;
    const url = editing ? `${base}/${barcodeEditingId.value}` : base;

    send(
        editing ? 'put' : 'post',
        url,
        {
            barcode: barcodeForm.barcode,
            type: barcodeForm.type,
            is_primary: barcodeForm.is_primary,
        },
        resetBarcodeForm,
    );
}

function removeBarcode(variant: VariantRow, barcode: BarcodeRow): void {
    if (!props.selected || !window.confirm(`¿Eliminar el código de barras "${barcode.barcode}"?`)) {
        return;
    }

    send(
        'delete',
        `/catalog/products/${props.selected.id}/variants/${variant.id}/barcodes/${barcode.id}`,
        null,
        resetBarcodeForm,
    );
}

interface EffectiveTax {
    rate: number;
    isExempt: boolean;
    source: 'variant' | 'product' | 'default' | 'none';
}

function resolveEffectiveTax(variantTaxId: string, productTaxId: string): EffectiveTax {
    const variantId = variantTaxId === '' ? null : Number(variantTaxId);
    const productId = productTaxId === '' ? null : Number(productTaxId);
    const candidate = variantId ?? productId;
    let source: EffectiveTax['source'] = variantId !== null ? 'variant' : (productId !== null ? 'product' : 'none');
    let tax = candidate === null
        ? undefined
        : props.catalogs.tax_rates.find((option) => option.id === candidate);

    if (!tax) {
        tax = props.catalogs.tax_rates.find((option) => option.is_default);
        source = 'default';
    }

    if (!tax) {
        return { rate: 0, isExempt: false, source: 'none' };
    }

    const rate = Number(tax.rate);
    const safeRate = Number.isFinite(rate) ? Math.max(0, Math.min(100, rate)) : 0;

    return {
        rate: tax.is_exempt ? 0 : safeRate,
        isExempt: tax.is_exempt,
        source,
    };
}

function taxHint(variantTaxId: string, productTaxId: string): string {
    const tax = resolveEffectiveTax(variantTaxId, productTaxId);
    const mode = props.pricing.prices_include_tax
        ? 'El precio guardado incluye impuesto.'
        : 'El precio guardado no incluye impuesto.';
    const rate = tax.isExempt
        ? 'exenta (0%)'
        : `${tax.rate.toFixed(2).replace('.', ',')}%`;
    const source = {
        variant: 'de la variante',
        product: 'del producto',
        default: 'predeterminada',
        none: 'sin tasa configurada',
    }[tax.source];

    return `${mode} Tasa efectiva: ${rate}, ${source}. El resultado es una sugerencia editable, no una regla automática.`;
}

function suggestedPriceFor(
    target: DraftVariantForm,
    productTaxId: string,
): { price?: string; error?: string } {
    const cost = Number(target.cost_price);

    if (target.cost_price.trim() === '' || !Number.isFinite(cost) || cost <= 0) {
        return { error: 'Ingrese un costo neto mayor que 0 para calcular el precio.' };
    }

    const percent = Number(target.calculator_percent);

    if (target.calculator_percent.trim() === '' || !Number.isFinite(percent) || percent < 0) {
        return { error: 'Ingrese un objetivo porcentual válido (0 o mayor).' };
    }

    if (target.calculator_mode === 'margin' && percent >= 100) {
        return { error: 'El margen objetivo debe ser menor que 100%.' };
    }

    if (percent > 10000) {
        return { error: 'El objetivo porcentual es demasiado alto.' };
    }

    const net = target.calculator_mode === 'margin'
        ? cost / (1 - percent / 100)
        : cost * (1 + percent / 100);

    if (!Number.isFinite(net) || net < 0) {
        return { error: 'No fue posible calcular un precio neto válido con esos datos.' };
    }

    const tax = resolveEffectiveTax(target.tax_rate_id, productTaxId);
    const gross = props.pricing.prices_include_tax && !tax.isExempt
        ? net * (1 + tax.rate / 100)
        : net;
    const rounded = Math.round((gross + Number.EPSILON) * 100) / 100;

    if (!Number.isFinite(rounded) || rounded < 0 || rounded > 999999999999.99) {
        return { error: 'El precio sugerido excede el máximo permitido; ajuste costo u objetivo.' };
    }

    return { price: rounded.toFixed(2) };
}

function calculateDraftPrice(index: number): void {
    const variant = productForm.variants[index];

    if (!variant) {
        return;
    }

    const result = suggestedPriceFor(variant, productForm.tax_rate_id);

    if (result.error) {
        draftCalculatorErrors.value = { ...draftCalculatorErrors.value, [index]: result.error };

        return;
    }

    variant.price = result.price ?? variant.price;
    const nextErrors = { ...draftCalculatorErrors.value };
    delete nextErrors[index];
    draftCalculatorErrors.value = nextErrors;
}

function calculateVariantPrice(): void {
    const result = suggestedPriceFor(variantForm, selectedProductTaxRateId.value);

    if (result.error) {
        variantCalculatorError.value = result.error;

        return;
    }

    variantForm.price = result.price ?? variantForm.price;
    variantCalculatorError.value = '';
}

function catalogLabel(options: { id: number; name: string }[], id: number | null): string {
    if (id === null) {
        return '—';
    }

    return options.find((option) => option.id === id)?.name ?? `#${id}`;
}

function taxRateLabel(id: number | null): string {
    if (id === null) {
        return 'Sin tasa';
    }

    const tax = props.catalogs.tax_rates.find((option) => option.id === id);

    return tax ? `${tax.code} — ${tax.name}` : `#${id}`;
}

function typeLabel(type: string): string {
    return { simple: 'Simple', variable: 'Variable', combo: 'Combo' }[type] ?? type;
}

function unitLabel(unit: string): string {
    return units.find((option) => option.value === unit)?.label ?? unit;
}

function originLabel(origin: string): string {
    return { pos: 'POS (alta manual)', channel: 'Canal', import: 'Importación' }[origin] ?? origin;
}

function money(value: string): string {
    const number = Number(value);

    return Number.isFinite(number)
        ? number.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : value;
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

.sf-search {
    margin-top: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sf-search-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
}

.sf-search-row .sf-input {
    flex: 1 1 280px;
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
    margin: 0.75rem 0 1rem;
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
.sf-button:focus-visible,
.sf-input:focus-visible,
.sf-page-link:focus-visible {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
}

.sf-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.75rem;
}

.sf-page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    padding: 0.35rem 0.55rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    color: #1d4ed8;
    font-size: 0.8125rem;
    text-decoration: none;
}

.sf-page-link-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    font-weight: 700;
}

.sf-page-link-disabled {
    color: #9ca3af;
    cursor: not-allowed;
}

.sf-definition-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
    margin: 1rem 0 1.25rem;
}

.sf-definition-grid dt {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
}

.sf-definition-grid dd {
    margin: 0.2rem 0 0;
    color: #111827;
    font-size: 0.9rem;
}

.sf-definition-wide {
    grid-column: 1 / -1;
}

.sf-break {
    overflow-wrap: anywhere;
}

.sf-form {
    border-top: 1px solid #e5e7eb;
    margin-top: 1rem;
    padding-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.sf-form-plain {
    border-top: none;
    margin-top: 0;
    padding-top: 0;
}

.sf-form-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    margin-top: 1rem;
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

.sf-field-wide {
    grid-column: 1 / -1;
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

.sf-fieldset {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.75rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.sf-attribute-group {
    min-width: 180px;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.sf-attribute-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #374151;
}

.sf-draft-variant {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.sf-calculator {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sf-calculator-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #1e293b;
}

.sf-calculator-action {
    justify-content: flex-end;
}

.sf-error-text {
    color: #b91c1c;
    font-size: 0.75rem;
    line-height: 1.4;
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

.sf-button-danger {
    background: #b91c1c;
    color: #ffffff;
}

.sf-button-danger:hover:not(:disabled) {
    background: #991b1b;
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

.sf-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
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

.sf-chip-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sf-chip-danger {
    color: #b91c1c;
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
