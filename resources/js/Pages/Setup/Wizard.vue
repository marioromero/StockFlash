<template>
    <div class="sf-setup-page">
        <header class="sf-setup-header">
            <div class="sf-brand" aria-label="StockFlash">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <p class="sf-header-note">Configuración inicial de la instalación</p>
        </header>

        <main class="sf-main">
            <section class="sf-card" aria-labelledby="setup-title">
                <h1 id="setup-title" class="sf-title">Asistente de primer inicio</h1>
                <p class="sf-subtitle">
                    Complete los datos del comercio, las preferencias generales, la sucursal inicial y
                    la cuenta Admin Central. La información se guarda una sola vez, de forma transaccional.
                </p>

                <div
                    v-if="hasErrors"
                    id="setup-error-summary"
                    ref="errorSummary"
                    class="sf-message sf-error"
                    role="alert"
                    aria-live="assertive"
                    tabindex="-1"
                >
                    Hay campos por corregir. Revise los mensajes indicados en cada sección.
                </div>

                <div
                    v-if="errorFor('payload') || errorFor('setup')"
                    class="sf-message sf-error"
                    role="alert"
                >
                    <p v-if="errorFor('payload')">{{ errorFor('payload') }}</p>
                    <p v-if="errorFor('setup')">{{ errorFor('setup') }}</p>
                </div>

                <nav class="sf-steps" aria-label="Pasos del asistente">
                    <ol class="sf-step-list">
                        <li
                            v-for="step in steps"
                            :key="step.id"
                            class="sf-step"
                            :class="{ 'sf-step-active': currentStep === step.id, 'sf-step-done': currentStep > step.id }"
                            :aria-current="currentStep === step.id ? 'step' : undefined"
                        >
                            <span class="sf-step-number" aria-hidden="true">{{ step.id }}</span>
                            <span class="sf-step-label">{{ step.label }}</span>
                        </li>
                    </ol>
                </nav>

                <h2 ref="stepHeading" class="sf-step-heading" tabindex="-1">
                    {{ currentStepHeading }}
                </h2>

                <form class="sf-form" novalidate @submit.prevent="submit">
                    <input v-model="form.setup_token" type="hidden" name="setup_token" />

                    <!-- Paso 1 · Comercio y responsable (CFG-002, campos opcionales) -->
                    <fieldset v-show="currentStep === 1" class="sf-fieldset">
                        <legend class="sf-legend">Paso 1 · Comercio y responsable</legend>
                        <p class="sf-hint">
                            Todos los campos de esta sección son opcionales y pueden completarse después
                            en Configuración. No se valida el RUT contra el SII ni se emiten documentos tributarios.
                        </p>

                        <div
                            v-for="(label, field) in businessFields"
                            :key="field"
                            class="sf-field"
                        >
                            <label class="sf-label" :for="`setup-${field}`">{{ label }}</label>
                            <input
                                :id="`setup-${field}`"
                                :name="field"
                                type="text"
                                maxlength="255"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor(String(field))) }"
                                :value="businessValue(String(field))"
                                :aria-invalid="Boolean(errorFor(String(field)))"
                                :aria-describedby="`setup-${field}-help${errorFor(String(field)) ? ` setup-${field}-error` : ''}`"
                                :disabled="form.processing"
                                @input="setBusinessValue(String(field), $event)"
                            />
                            <p :id="`setup-${field}-help`" class="sf-hint">
                                {{ fieldHelp(String(field)) }}
                            </p>
                            <p
                                v-if="errorFor(String(field))"
                                :id="`setup-${field}-error`"
                                class="sf-error-text"
                                role="alert"
                            >
                                {{ errorFor(String(field)) }}
                            </p>
                        </div>
                    </fieldset>

                    <!-- Paso 2 · Región, moneda y precios (CFG-003, obligatorios) -->
                    <fieldset v-show="currentStep === 2" class="sf-fieldset">
                        <legend class="sf-legend">Paso 2 · Región, moneda y precios</legend>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-country">País (ISO de dos letras)</label>
                            <input
                                id="setup-country"
                                v-model="form.country"
                                type="text"
                                maxlength="2"
                                autocomplete="country"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': Boolean(errorFor('country')) }"
                                :aria-invalid="Boolean(errorFor('country'))"
                                :aria-describedby="`setup-country-help${errorFor('country') ? ' setup-country-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-country-help" class="sf-hint">Código de país de dos letras, por ejemplo CL.</p>
                            <p v-if="errorFor('country')" id="setup-country-error" class="sf-error-text" role="alert">
                                {{ errorFor('country') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-currency">Moneda (ISO de tres letras)</label>
                            <input
                                id="setup-currency"
                                v-model="form.currency_code"
                                type="text"
                                maxlength="3"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': Boolean(errorFor('currency_code')) }"
                                :aria-invalid="Boolean(errorFor('currency_code'))"
                                :aria-describedby="`setup-currency-help${errorFor('currency_code') ? ' setup-currency-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-currency-help" class="sf-hint">Código de moneda de tres letras, por ejemplo CLP.</p>
                            <p v-if="errorFor('currency_code')" id="setup-currency-error" class="sf-error-text" role="alert">
                                {{ errorFor('currency_code') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-decimals">Decimales de moneda</label>
                            <input
                                id="setup-decimals"
                                v-model.number="form.currency_decimals"
                                type="number"
                                min="0"
                                max="4"
                                step="1"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('currency_decimals')) }"
                                :aria-invalid="Boolean(errorFor('currency_decimals'))"
                                :aria-describedby="`setup-decimals-help${errorFor('currency_decimals') ? ' setup-decimals-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-decimals-help" class="sf-hint">Entero entre 0 y 4.</p>
                            <p v-if="errorFor('currency_decimals')" id="setup-decimals-error" class="sf-error-text" role="alert">
                                {{ errorFor('currency_decimals') }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="setup-timezone">Zona horaria</label>
                            <input
                                id="setup-timezone"
                                v-model="form.timezone"
                                type="text"
                                maxlength="64"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('timezone')) }"
                                :aria-invalid="Boolean(errorFor('timezone'))"
                                :aria-describedby="`setup-timezone-help${errorFor('timezone') ? ' setup-timezone-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-timezone-help" class="sf-hint">Identificador IANA, por ejemplo America/Santiago.</p>
                            <p v-if="errorFor('timezone')" id="setup-timezone-error" class="sf-error-text" role="alert">
                                {{ errorFor('timezone') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-locale">Idioma</label>
                            <input
                                id="setup-locale"
                                v-model="form.locale"
                                type="text"
                                maxlength="5"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': Boolean(errorFor('locale')) }"
                                :aria-invalid="Boolean(errorFor('locale'))"
                                :aria-describedby="`setup-locale-help${errorFor('locale') ? ' setup-locale-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-locale-help" class="sf-hint">Formato idioma-región, por ejemplo es-CL.</p>
                            <p v-if="errorFor('locale')" id="setup-locale-error" class="sf-error-text" role="alert">
                                {{ errorFor('locale') }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="setup-tax">Precios con impuesto</label>
                            <select
                                id="setup-tax"
                                v-model="form.prices_include_tax"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('prices_include_tax')) }"
                                :aria-invalid="Boolean(errorFor('prices_include_tax'))"
                                :aria-describedby="`setup-tax-help${errorFor('prices_include_tax') ? ' setup-tax-error' : ''}`"
                                :disabled="form.processing"
                            >
                                <option :value="true">Sí, los precios incluyen impuesto</option>
                                <option :value="false">No, los precios no incluyen impuesto</option>
                            </select>
                            <p id="setup-tax-help" class="sf-hint">
                                Indica cómo se interpretan los precios mostrados; no modifica ventas ya registradas.
                            </p>
                            <p v-if="errorFor('prices_include_tax')" id="setup-tax-error" class="sf-error-text" role="alert">
                                {{ errorFor('prices_include_tax') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-rounding">Paso de redondeo del efectivo</label>
                            <input
                                id="setup-rounding"
                                v-model.number="form.cash_rounding_step"
                                type="number"
                                min="1"
                                step="1"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('cash_rounding_step')) }"
                                :aria-invalid="Boolean(errorFor('cash_rounding_step'))"
                                :aria-describedby="`setup-rounding-help${errorFor('cash_rounding_step') ? ' setup-rounding-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-rounding-help" class="sf-hint">Entero mínimo 1. En Chile es habitual usar 10.</p>
                            <p v-if="errorFor('cash_rounding_step')" id="setup-rounding-error" class="sf-error-text" role="alert">
                                {{ errorFor('cash_rounding_step') }}
                            </p>
                        </div>
                    </fieldset>

                    <!-- Paso 3 · Sucursal inicial -->
                    <fieldset v-show="currentStep === 3" class="sf-fieldset">
                        <legend class="sf-legend">Paso 3 · Sucursal inicial</legend>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-code">Código de sucursal</label>
                            <input
                                id="setup-branch-code"
                                v-model="form.branch_code"
                                type="text"
                                maxlength="30"
                                class="sf-input sf-input-code"
                                :class="{ 'sf-input-error': Boolean(errorFor('branch_code')) }"
                                :aria-invalid="Boolean(errorFor('branch_code'))"
                                :aria-describedby="`setup-branch-code-help${errorFor('branch_code') ? ' setup-branch-code-error' : ''}`"
                                :disabled="form.processing"
                                @input="uppercaseBranchCode"
                            />
                            <p id="setup-branch-code-help" class="sf-hint">
                                Solo mayúsculas, números y guiones; máximo 30 caracteres.
                            </p>
                            <p v-if="errorFor('branch_code')" id="setup-branch-code-error" class="sf-error-text" role="alert">
                                {{ errorFor('branch_code') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-name">Nombre de la sucursal</label>
                            <input
                                id="setup-branch-name"
                                v-model="form.branch_name"
                                type="text"
                                maxlength="150"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('branch_name')) }"
                                :aria-invalid="Boolean(errorFor('branch_name'))"
                                :aria-describedby="`setup-branch-name-help${errorFor('branch_name') ? ' setup-branch-name-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-branch-name-help" class="sf-hint">Máximo 150 caracteres.</p>
                            <p v-if="errorFor('branch_name')" id="setup-branch-name-error" class="sf-error-text" role="alert">
                                {{ errorFor('branch_name') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-type">Tipo de sucursal</label>
                            <select
                                id="setup-branch-type"
                                v-model="form.branch_type"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('branch_type')) }"
                                :aria-invalid="Boolean(errorFor('branch_type'))"
                                :aria-describedby="`setup-branch-type-help${errorFor('branch_type') ? ' setup-branch-type-error' : ''}`"
                                :disabled="form.processing"
                            >
                                <option v-for="type in branchTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <p id="setup-branch-type-help" class="sf-hint">Local, bodega u online.</p>
                            <p v-if="errorFor('branch_type')" id="setup-branch-type-error" class="sf-error-text" role="alert">
                                {{ errorFor('branch_type') }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="setup-branch-address">Dirección (opcional)</label>
                            <input
                                id="setup-branch-address"
                                v-model="form.branch_address"
                                type="text"
                                maxlength="255"
                                class="sf-input"
                                :disabled="form.processing"
                            />
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-commune">Comuna (opcional)</label>
                            <input
                                id="setup-branch-commune"
                                v-model="form.branch_commune"
                                type="text"
                                maxlength="100"
                                class="sf-input"
                                :disabled="form.processing"
                            />
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-city">Ciudad (opcional)</label>
                            <input
                                id="setup-branch-city"
                                v-model="form.branch_city"
                                type="text"
                                maxlength="100"
                                class="sf-input"
                                :disabled="form.processing"
                            />
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-region">Región (opcional)</label>
                            <input
                                id="setup-branch-region"
                                v-model="form.branch_region"
                                type="text"
                                maxlength="100"
                                class="sf-input"
                                :disabled="form.processing"
                            />
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-branch-phone">Teléfono (opcional)</label>
                            <input
                                id="setup-branch-phone"
                                v-model="form.branch_phone"
                                type="text"
                                maxlength="30"
                                class="sf-input"
                                :disabled="form.processing"
                            />
                        </div>
                    </fieldset>

                    <!-- Paso 4 · Admin Central -->
                    <fieldset v-show="currentStep === 4" class="sf-fieldset">
                        <legend class="sf-legend">Paso 4 · Cuenta Admin Central</legend>
                        <p class="sf-hint">
                            Se creará exactamente una cuenta activa con rol Admin Central. No se crean
                            usuarios adicionales ni se reinician cuentas existentes.
                        </p>

                        <div class="sf-field">
                            <label class="sf-label" for="setup-admin-name">Nombre del administrador</label>
                            <input
                                id="setup-admin-name"
                                v-model="form.admin_name"
                                type="text"
                                maxlength="150"
                                autocomplete="name"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('admin_name')) }"
                                :aria-invalid="Boolean(errorFor('admin_name'))"
                                :aria-describedby="`setup-admin-name-help${errorFor('admin_name') ? ' setup-admin-name-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-admin-name-help" class="sf-hint">Máximo 150 caracteres.</p>
                            <p v-if="errorFor('admin_name')" id="setup-admin-name-error" class="sf-error-text" role="alert">
                                {{ errorFor('admin_name') }}
                            </p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="setup-admin-email">Correo del administrador</label>
                            <input
                                id="setup-admin-email"
                                v-model="form.admin_email"
                                type="email"
                                maxlength="150"
                                autocomplete="email"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('admin_email')) }"
                                :aria-invalid="Boolean(errorFor('admin_email'))"
                                :aria-describedby="`setup-admin-email-help${errorFor('admin_email') ? ' setup-admin-email-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-admin-email-help" class="sf-hint">Correo único, máximo 150 caracteres.</p>
                            <p v-if="errorFor('admin_email')" id="setup-admin-email-error" class="sf-error-text" role="alert">
                                {{ errorFor('admin_email') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-admin-password">Contraseña</label>
                            <input
                                id="setup-admin-password"
                                v-model="form.admin_password"
                                type="password"
                                minlength="15"
                                maxlength="64"
                                autocomplete="new-password"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('admin_password')) }"
                                :aria-invalid="Boolean(errorFor('admin_password'))"
                                :aria-describedby="`setup-admin-password-help${errorFor('admin_password') ? ' setup-admin-password-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-admin-password-help" class="sf-hint">Entre 15 y 64 caracteres.</p>
                            <p v-if="errorFor('admin_password')" id="setup-admin-password-error" class="sf-error-text" role="alert">
                                {{ errorFor('admin_password') }}
                            </p>
                        </div>

                        <div class="sf-field sf-field-half">
                            <label class="sf-label" for="setup-admin-password-confirmation">Confirmar contraseña</label>
                            <input
                                id="setup-admin-password-confirmation"
                                v-model="form.admin_password_confirmation"
                                type="password"
                                minlength="15"
                                maxlength="64"
                                autocomplete="new-password"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor('admin_password_confirmation')) }"
                                :aria-invalid="Boolean(errorFor('admin_password_confirmation'))"
                                :aria-describedby="`setup-admin-password-confirmation-help${errorFor('admin_password_confirmation') ? ' setup-admin-password-confirmation-error' : ''}`"
                                :disabled="form.processing"
                            />
                            <p id="setup-admin-password-confirmation-help" class="sf-hint">
                                Debe coincidir con la contraseña.
                            </p>
                            <p
                                v-if="errorFor('admin_password_confirmation')"
                                id="setup-admin-password-confirmation-error"
                                class="sf-error-text"
                                role="alert"
                            >
                                {{ errorFor('admin_password_confirmation') }}
                            </p>
                        </div>
                    </fieldset>

                    <div class="sf-actions">
                        <button
                            v-if="currentStep > 1"
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="form.processing"
                            @click="previousStep"
                        >
                            Anterior
                        </button>

                        <button
                            v-if="currentStep < steps.length"
                            type="button"
                            class="sf-button sf-button-primary"
                            :disabled="form.processing"
                            @click="nextStep"
                        >
                            Siguiente
                        </button>

                        <button
                            v-if="currentStep === steps.length"
                            type="submit"
                            class="sf-button sf-button-primary"
                            :disabled="form.processing || !isPristineStep"
                        >
                            {{ form.processing ? 'Guardando instalación...' : 'Completar instalación' }}
                        </button>
                    </div>
                </form>

                <p class="sf-footnote">
                    Si la instalación ya contiene información, el asistente se bloquea y no reinicia datos.
                </p>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

interface Defaults {
    country: string;
    currency_code: string;
    currency_decimals: number;
    timezone: string;
    locale: string;
    prices_include_tax: boolean;
    cash_rounding_step: number;
}

interface BranchType {
    value: string;
    label: string;
}

const props = defineProps<{
    flow_token: string;
    defaults: Defaults;
    business_fields: Record<string, string>;
    branch_types: BranchType[];
}>();

const businessFields = computed<Record<string, string>>(() => props.business_fields ?? {});
const branchTypes = computed<BranchType[]>(() => props.branch_types ?? []);

const steps = [
    { id: 1, label: 'Comercio y responsable' },
    { id: 2, label: 'Región y precios' },
    { id: 3, label: 'Sucursal inicial' },
    { id: 4, label: 'Admin Central' },
];

const currentStep = ref<number>(1);
const localErrors = ref<Record<string, string>>({});
const stepHeading = ref<HTMLElement | null>(null);
const errorSummary = ref<HTMLElement | null>(null);

const currentStepLabel = computed(
    () => steps.find((step) => step.id === currentStep.value)?.label ?? '',
);
const currentStepHeading = computed(() => `Paso ${currentStep.value}: ${currentStepLabel.value}`);

const localFieldsByStep: Record<number, string[]> = {
    1: [
        'business_name',
        'fiscal_id',
        'fiscal_name',
        'commercial_activity',
        'fiscal_address',
        'fiscal_commune',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
    ],
    2: [
        'country',
        'currency_code',
        'currency_decimals',
        'timezone',
        'locale',
        'prices_include_tax',
        'cash_rounding_step',
    ],
    3: ['branch_code', 'branch_name', 'branch_type'],
    4: ['admin_name', 'admin_email', 'admin_password', 'admin_password_confirmation'],
};

const form = useForm({
    setup_token: props.flow_token ?? '',
    business_name: '',
    fiscal_id: '',
    fiscal_name: '',
    commercial_activity: '',
    fiscal_address: '',
    fiscal_commune: '',
    responsible_name: '',
    responsible_email: '',
    responsible_phone: '',
    country: props.defaults?.country ?? 'CL',
    currency_code: props.defaults?.currency_code ?? 'CLP',
    currency_decimals: props.defaults?.currency_decimals ?? 0,
    timezone: props.defaults?.timezone ?? 'America/Santiago',
    locale: props.defaults?.locale ?? 'es-CL',
    prices_include_tax: props.defaults?.prices_include_tax ?? true,
    cash_rounding_step: props.defaults?.cash_rounding_step ?? 1,
    branch_code: '',
    branch_name: '',
    branch_type: 'store',
    branch_address: '',
    branch_commune: '',
    branch_city: '',
    branch_region: '',
    branch_phone: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const isPristineStep = computed(() => currentStep.value === steps.length);
const hasErrors = computed(
    () =>
        Object.keys(form.errors as Record<string, string | undefined>).length > 0
        || Object.keys(localErrors.value).length > 0,
);

function errorFor(field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[field] ?? localErrors.value[field];
}

function businessValue(field: string): string {
    const value = (form as unknown as Record<string, unknown>)[field];

    return typeof value === 'string' ? value : '';
}

function setBusinessValue(field: string, event: Event): void {
    const target = event.target as HTMLInputElement;
    (form as unknown as Record<string, unknown>)[field] = target.value;
}

function fieldHelp(field: string): string {
    const limits: Record<string, string> = {
        business_name: 'Máximo 150 caracteres.',
        fiscal_id: 'Máximo 50 caracteres; no se valida contra el SII.',
        fiscal_name: 'Máximo 150 caracteres.',
        commercial_activity: 'Máximo 255 caracteres.',
        fiscal_address: 'Máximo 255 caracteres.',
        fiscal_commune: 'Máximo 100 caracteres.',
        responsible_name: 'Máximo 150 caracteres.',
        responsible_email: 'Correo válido, máximo 255 caracteres.',
        responsible_phone: 'Máximo 50 caracteres.',
    };

    return limits[field] ?? 'Campo opcional.';
}

function uppercaseBranchCode(): void {
    form.branch_code = (form.branch_code ?? '').toUpperCase();
}

function validateStep(step: number): boolean {
    const errors: Record<string, string> = {};

    if (step === 1) {
        if (form.responsible_email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.responsible_email)) {
            errors.responsible_email = 'El correo del responsable no es válido.';
        }
    }

    if (step === 2) {
        if (!/^[A-Za-z]{2}$/.test(form.country)) {
            errors.country = 'El país debe ser un código de dos letras.';
        }
        if (!/^[A-Za-z]{3}$/.test(form.currency_code)) {
            errors.currency_code = 'La moneda debe ser un código de tres letras.';
        }
        if (!Number.isInteger(Number(form.currency_decimals)) || Number(form.currency_decimals) < 0 || Number(form.currency_decimals) > 4) {
            errors.currency_decimals = 'Los decimales deben ser un entero entre 0 y 4.';
        }
        if (form.timezone.trim() === '') {
            errors.timezone = 'La zona horaria es obligatoria.';
        }
        if (!/^[a-z]{2}-[A-Z]{2}$/.test(form.locale)) {
            errors.locale = 'El idioma debe tener el formato es-CL.';
        }
        if (!Number.isInteger(Number(form.cash_rounding_step)) || Number(form.cash_rounding_step) < 1) {
            errors.cash_rounding_step = 'El paso de redondeo debe ser un entero mínimo 1.';
        }
    }

    if (step === 3) {
        if (!/^[A-Z0-9-]+$/.test(form.branch_code)) {
            errors.branch_code = 'El código solo puede contener mayúsculas, números y guiones.';
        }
        if (form.branch_code.length > 30) {
            errors.branch_code = 'El código no puede superar los 30 caracteres.';
        }
        if (form.branch_name.trim() === '') {
            errors.branch_name = 'El nombre de la sucursal es obligatorio.';
        }
        if (!branchTypes.value.some((type) => type.value === form.branch_type)) {
            errors.branch_type = 'El tipo de sucursal no es válido.';
        }
    }

    if (step === 4) {
        if (form.admin_name.trim() === '') {
            errors.admin_name = 'El nombre del administrador es obligatorio.';
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.admin_email)) {
            errors.admin_email = 'El correo del administrador no es válido.';
        }
        if (form.admin_password.length < 15 || form.admin_password.length > 64) {
            errors.admin_password = 'La contraseña debe tener entre 15 y 64 caracteres.';
        }
        if (form.admin_password !== form.admin_password_confirmation) {
            errors.admin_password_confirmation = 'La confirmación no coincide con la contraseña.';
        }
    }

    const next = { ...localErrors.value };

    for (const field of localFieldsByStep[step] ?? []) {
        delete next[field];
    }

    Object.assign(next, errors);
    localErrors.value = next;

    return Object.keys(errors).length === 0;
}

function focusStepHeading(): void {
    nextTick(() => stepHeading.value?.focus());
}

function focusErrors(): void {
    nextTick(() => errorSummary.value?.focus());
}

function nextStep(): void {
    if (!validateStep(currentStep.value)) {
        focusErrors();

        return;
    }

    if (currentStep.value < steps.length) {
        currentStep.value += 1;
        focusStepHeading();
    }
}

function previousStep(): void {
    if (currentStep.value > 1) {
        currentStep.value -= 1;
        focusStepHeading();
    }
}

function submit(): void {
    for (let step = 1; step <= steps.length; step += 1) {
        if (!validateStep(step)) {
            currentStep.value = step;
            focusErrors();

            return;
        }
    }

    form.post('/setup', {
        preserveScroll: true,
        onError: () => {
            const firstError = Object.keys(form.errors as Record<string, string | undefined>)[0];

            if (firstError === 'country' || firstError === 'currency_code' || firstError === 'timezone' || firstError === 'locale' || firstError === 'prices_include_tax' || firstError === 'cash_rounding_step' || firstError === 'currency_decimals') {
                currentStep.value = 2;
            } else if (firstError?.startsWith('branch_')) {
                currentStep.value = 3;
            } else if (firstError?.startsWith('admin_')) {
                currentStep.value = 4;
            } else {
                currentStep.value = 1;
            }

            focusErrors();
        },
    });
}
</script>

<style scoped>
.sf-setup-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f3f4f6;
}

.sf-setup-header {
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

.sf-header-note {
    font-size: 0.8rem;
    color: #6b7280;
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

.sf-steps {
    margin-bottom: 1.25rem;
}

.sf-step-heading {
    margin: 0 0 0.75rem;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    outline: none;
}

.sf-step-heading:focus,
.sf-step-heading:focus-visible {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
}

.sf-step-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    list-style: none;
    margin: 0;
    padding: 0;
}

.sf-step {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.6rem;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    font-size: 0.75rem;
    color: #4b5563;
}

.sf-step-active {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 700;
}

.sf-step-done {
    border-color: #86efac;
    background: #f0fdf4;
    color: #166534;
}

.sf-step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    background: #e5e7eb;
    font-weight: 700;
}

.sf-step-active .sf-step-number {
    background: #2563eb;
    color: #ffffff;
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

.sf-error {
    background: #fee2e2;
    color: #991b1b;
}

.sf-footnote {
    margin-top: 1.25rem;
    font-size: 0.75rem;
    color: #6b7280;
}

@media (max-width: 640px) {
    .sf-setup-header {
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
