<template>
    <div class="sf-install-page">
        <header class="sf-install-header">
            <div class="sf-brand" aria-label="StockFlash">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <p class="sf-header-note">Instalación técnica de una sola vez</p>
        </header>

        <main class="sf-main">
            <section class="sf-card" aria-labelledby="install-title">
                <h1 id="install-title" class="sf-title">Instalador técnico</h1>
                <p class="sf-subtitle">
                    Prepara el entorno, el esquema y las semillas autorizadas. No crea usuarios,
                    sucursales, licencias ni datos comerciales: eso se completa después en
                    <strong>/setup</strong>.
                </p>

                <div
                    v-if="storage_warning"
                    class="sf-message sf-warning"
                    role="status"
                    aria-live="polite"
                >
                    No fue posible crear las tiendas temporales de sesión/caché del instalador. Se
                    usan tiendas en memoria y el preflight reportará el bloqueo de permisos; no se
                    ejecutará la instalación hasta corregirlo.
                </div>


                <!-- Token de arranque: solo se presenta cuando aún no hay autorización. -->
                <section v-if="!authorized" class="sf-section" aria-labelledby="install-bootstrap-title">
                    <h2 id="install-bootstrap-title" class="sf-section-title">Autorización técnica</h2>
                    <p class="sf-hint">
                        El instalador está cerrado por defecto. Ingrese el token de arranque definido
                        fuera de la aplicación pública. El token no se muestra ni se conserva en el
                        formulario ante un error.
                    </p>

                    <form class="sf-form" novalidate @submit.prevent="submitBootstrap">
                        <div class="sf-field">
                            <label class="sf-label" for="install-bootstrap-token">Token de arranque</label>
                            <input
                                id="install-bootstrap-token"
                                ref="bootstrapTokenInput"
                                v-model="bootstrapForm.bootstrap_token"
                                type="password"
                                name="bootstrap_token"
                                autocomplete="off"
                                class="sf-input"
                                :class="{ 'sf-input-error': Boolean(errorFor(bootstrapForm, 'bootstrap_token')) }"
                                :aria-invalid="Boolean(errorFor(bootstrapForm, 'bootstrap_token'))"
                                :aria-describedby="`install-bootstrap-help${errorFor(bootstrapForm, 'bootstrap_token') ? ' install-bootstrap-error' : ''}`"
                                :disabled="bootstrapForm.processing"
                            />
                            <p id="install-bootstrap-help" class="sf-hint">
                                El token lo administra el responsable técnico y no se muestra en pantalla.
                            </p>
                            <p
                                v-if="errorFor(bootstrapForm, 'bootstrap_token')"
                                id="install-bootstrap-error"
                                class="sf-error-text"
                                role="alert"
                            >
                                {{ errorFor(bootstrapForm, 'bootstrap_token') }}
                            </p>
                        </div>

                        <div class="sf-actions">
                            <button type="submit" class="sf-button sf-button-primary" :disabled="bootstrapForm.processing">
                                {{ bootstrapForm.processing ? 'Verificando…' : 'Autorizar instalador' }}
                            </button>
                        </div>
                    </form>
                </section>

                <template v-else>
                    <div
                        v-if="state?.code === 'DATABASE_UNAVAILABLE'"
                        class="sf-message sf-warning"
                        role="status"
                        aria-live="polite"
                    >
                        No fue posible verificar la base de datos actual. Puede continuar indicando el
                        destino correcto en el paso 2; la virginidad se revalidará allí antes de escribir
                        cualquier secreto o ejecutar migraciones.
                    </div>

                    <nav class="sf-steps" aria-label="Pasos del instalador">
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

                    <div v-if="failure" class="sf-message sf-error" role="alert" aria-live="assertive">
                        {{ failure }}
                    </div>

                    <div
                        v-if="errorFor(wizardForm, 'install') || errorFor(wizardForm, 'db') || errorFor(wizardForm, 'preflight')"
                        ref="wizardErrorBox"
                        class="sf-message sf-error"
                        role="alert"
                        tabindex="-1"
                    >
                        <p v-if="errorFor(wizardForm, 'preflight')">{{ errorFor(wizardForm, 'preflight') }}</p>
                        <p v-if="errorFor(wizardForm, 'db')">{{ errorFor(wizardForm, 'db') }}</p>
                        <p v-if="errorFor(wizardForm, 'install')">{{ errorFor(wizardForm, 'install') }}</p>
                    </div>

                    <!-- Paso 1 · Requisitos previos -->
                    <section class="sf-section" aria-labelledby="install-preflight-title">
                        <h2 id="install-preflight-title" class="sf-section-title">Paso 1 · Requisitos previos</h2>
                        <p class="sf-hint">
                            Se comprueba versión de PHP, extensiones, archivos del proyecto y permisos de
                            escritura. No se ejecutan migraciones ni comandos de shell.
                        </p>

                        <ul v-if="preflight" class="sf-check-list" aria-live="polite">
                            <li v-for="check in preflight.checks" :key="check.id" class="sf-check">
                                <span class="sf-check-status" :class="check.status === 'ok' ? 'sf-check-ok' : 'sf-check-fail'">
                                    {{ check.status === 'ok' ? 'OK' : 'Falla' }}
                                </span>
                                <span class="sf-check-body">
                                    <strong>{{ check.label }}</strong>
                                    <span class="sf-check-detail">{{ check.detail }}</span>
                                </span>
                            </li>
                        </ul>

                        <form class="sf-form" novalidate @submit.prevent="submitPreflight">
                            <input v-model="preflightForm.install_token" type="hidden" name="install_token" />
                            <div class="sf-actions">
                                <button type="submit" class="sf-button sf-button-primary" :disabled="preflightForm.processing">
                                    {{ preflightForm.processing ? 'Verificando…' : 'Verificar requisitos' }}
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Paso 2 · Conexión de base de datos -->
                    <section class="sf-section" aria-labelledby="install-db-title">
                        <h2 id="install-db-title" class="sf-section-title">Paso 2 · Conexión de base de datos</h2>
                        <p class="sf-hint">
                            La prueba de conexión usa los datos indicados y no guarda la contraseña en la
                            sesión del servidor. El destino probado se reutiliza para confirmar.
                        </p>

                        <form class="sf-form" novalidate @submit.prevent="submitConnection">
                            <div class="sf-field sf-field-half">
                                <label class="sf-label" for="install-driver">Motor</label>
                                <select
                                    id="install-driver"
                                    v-model="wizardForm.driver"
                                    class="sf-input"
                                    :disabled="wizardForm.processing"
                                >
                                    <option value="mariadb">MariaDB</option>
                                    <option value="mysql">MySQL</option>
                                </select>
                            </div>

                            <div class="sf-field sf-field-half">
                                <label class="sf-label" for="install-host">Host</label>
                                <input
                                    id="install-host"
                                    v-model="wizardForm.host"
                                    type="text"
                                    maxlength="255"
                                    autocomplete="off"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': Boolean(errorFor(wizardForm, 'host')) }"
                                    :aria-invalid="Boolean(errorFor(wizardForm, 'host'))"
                                    :aria-describedby="`install-host-help${errorFor(wizardForm, 'host') ? ' install-host-error' : ''}`"
                                    :disabled="wizardForm.processing"
                                />
                                <p id="install-host-help" class="sf-hint">Solo letras, números, puntos, guiones y dos puntos.</p>
                                <p v-if="errorFor(wizardForm, 'host')" id="install-host-error" class="sf-error-text" role="alert">
                                    {{ errorFor(wizardForm, 'host') }}
                                </p>
                            </div>

                            <div class="sf-field sf-field-half">
                                <label class="sf-label" for="install-port">Puerto</label>
                                <input
                                    id="install-port"
                                    v-model.number="wizardForm.port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    step="1"
                                    inputmode="numeric"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': Boolean(errorFor(wizardForm, 'port')) }"
                                    :aria-invalid="Boolean(errorFor(wizardForm, 'port'))"
                                    :aria-describedby="`install-port-help${errorFor(wizardForm, 'port') ? ' install-port-error' : ''}`"
                                    :disabled="wizardForm.processing"
                                />
                                <p id="install-port-help" class="sf-hint">Entre 1 y 65535.</p>
                                <p v-if="errorFor(wizardForm, 'port')" id="install-port-error" class="sf-error-text" role="alert">
                                    {{ errorFor(wizardForm, 'port') }}
                                </p>
                            </div>

                            <div class="sf-field sf-field-half">
                                <label class="sf-label" for="install-database">Base de datos</label>
                                <input
                                    id="install-database"
                                    v-model="wizardForm.database"
                                    type="text"
                                    maxlength="64"
                                    autocomplete="off"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': Boolean(errorFor(wizardForm, 'database')) }"
                                    :aria-invalid="Boolean(errorFor(wizardForm, 'database'))"
                                    :aria-describedby="`install-database-help${errorFor(wizardForm, 'database') ? ' install-database-error' : ''}`"
                                    :disabled="wizardForm.processing"
                                />
                                <p id="install-database-help" class="sf-hint">Nombre de la base de datos ya creada.</p>
                                <p v-if="errorFor(wizardForm, 'database')" id="install-database-error" class="sf-error-text" role="alert">
                                    {{ errorFor(wizardForm, 'database') }}
                                </p>
                            </div>

                            <div class="sf-field sf-field-half">
                                <label class="sf-label" for="install-username">Usuario</label>
                                <input
                                    id="install-username"
                                    v-model="wizardForm.username"
                                    type="text"
                                    maxlength="80"
                                    autocomplete="off"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': Boolean(errorFor(wizardForm, 'username')) }"
                                    :aria-invalid="Boolean(errorFor(wizardForm, 'username'))"
                                    :aria-describedby="`install-username-help${errorFor(wizardForm, 'username') ? ' install-username-error' : ''}`"
                                    :disabled="wizardForm.processing"
                                />
                                <p id="install-username-help" class="sf-hint">Usuario con permisos sobre esa base de datos.</p>
                                <p v-if="errorFor(wizardForm, 'username')" id="install-username-error" class="sf-error-text" role="alert">
                                    {{ errorFor(wizardForm, 'username') }}
                                </p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="install-db-password">Contraseña de la base de datos</label>
                                <input
                                    id="install-db-password"
                                    v-model="wizardForm.db_password"
                                    type="password"
                                    maxlength="255"
                                    autocomplete="new-password"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': Boolean(errorFor(wizardForm, 'db_password')) }"
                                    :aria-invalid="Boolean(errorFor(wizardForm, 'db_password'))"
                                    :aria-describedby="`install-db-password-help${errorFor(wizardForm, 'db_password') ? ' install-db-password-error' : ''}`"
                                    :disabled="wizardForm.processing"
                                />
                                <p id="install-db-password-help" class="sf-hint">No se muestra en pantalla ni se guarda en la sesión.</p>
                                <p v-if="errorFor(wizardForm, 'db_password')" id="install-db-password-error" class="sf-error-text" role="alert">
                                    {{ errorFor(wizardForm, 'db_password') }}
                                </p>
                            </div>

                            <input v-model="wizardForm.install_token" type="hidden" name="install_token" />

                            <div class="sf-actions">
                                <button type="submit" class="sf-button sf-button-secondary" :disabled="wizardForm.processing">
                                    {{ wizardForm.processing ? 'Probando…' : 'Probar conexión' }}
                                </button>
                            </div>
                        </form>

                        <div
                            v-if="connection"
                            class="sf-message"
                            :class="connection.ok ? 'sf-success' : 'sf-error'"
                            role="status"
                            aria-live="polite"
                        >
                            {{ connection.message }}
                            <span v-if="connection.ok" class="sf-connection-label">Destino: {{ connection.label }}</span>
                        </div>
                    </section>

                    <!-- Paso 3 · Confirmación explícita -->
                    <section class="sf-section" aria-labelledby="install-confirm-title">
                        <h2 id="install-confirm-title" class="sf-section-title">Paso 3 · Confirmación de destino</h2>

                        <form class="sf-form" novalidate @submit.prevent="submitRun">
                            <fieldset class="sf-fieldset">
                                <legend class="sf-legend">Confirmación irreversible</legend>
                                <p class="sf-hint">
                                    Se ejecutarán migraciones normales y las semillas contractuales existentes.
                                    No se borran datos, no se reinician usuarios ni se revierten migraciones
                                    automáticamente ante un fallo.
                                </p>

                                <label class="sf-checkbox-row" for="install-confirm-destination">
                                    <input
                                        id="install-confirm-destination"
                                        v-model="wizardForm.confirm_destination"
                                        type="checkbox"
                                        class="sf-checkbox"
                                        :aria-describedby="errorFor(wizardForm, 'confirm_destination') ? 'install-confirm-destination-error' : undefined"
                                    />
                                    <span>Confirmo que el destino de la base de datos es el correcto.</span>
                                </label>
                                <p
                                    v-if="errorFor(wizardForm, 'confirm_destination')"
                                    id="install-confirm-destination-error"
                                    class="sf-error-text"
                                    role="alert"
                                >
                                    {{ errorFor(wizardForm, 'confirm_destination') }}
                                </p>

                                <div class="sf-field">
                                    <label class="sf-label" for="install-confirmation-phrase">
                                        Escriba INSTALAR para confirmar
                                    </label>
                                    <input
                                        id="install-confirmation-phrase"
                                        v-model="wizardForm.confirmation_phrase"
                                        type="text"
                                        maxlength="16"
                                        autocomplete="off"
                                        class="sf-input"
                                        :class="{ 'sf-input-error': Boolean(errorFor(wizardForm, 'confirmation_phrase')) }"
                                        :aria-invalid="Boolean(errorFor(wizardForm, 'confirmation_phrase'))"
                                        :aria-describedby="`install-confirmation-help${errorFor(wizardForm, 'confirmation_phrase') ? ' install-confirmation-error' : ''}`"
                                        :disabled="wizardForm.processing"
                                    />
                                    <p id="install-confirmation-help" class="sf-hint">La frase debe coincidir exactamente.</p>
                                    <p
                                        v-if="errorFor(wizardForm, 'confirmation_phrase')"
                                        id="install-confirmation-error"
                                        class="sf-error-text"
                                        role="alert"
                                    >
                                        {{ errorFor(wizardForm, 'confirmation_phrase') }}
                                    </p>
                                </div>
                            </fieldset>

                            <input v-model="wizardForm.install_token" type="hidden" name="install_token" />

                            <div class="sf-actions">
                                <button
                                    type="submit"
                                    class="sf-button sf-button-primary"
                                    :disabled="wizardForm.processing || !canRun"
                                >
                                    {{ wizardForm.processing ? 'Instalando…' : 'Ejecutar instalación' }}
                                </button>
                            </div>
                        </form>

                        <p class="sf-hint">
                            Las claves APP_KEY, BLIND_INDEX_KEY y BACKUP_ENCRYPTION_KEY se generan en el
                            servidor durante esta confirmación y nunca se muestran en pantalla, props ni
                            registros.
                        </p>
                    </section>

                    <!-- Paso 4 · Estado final -->
                    <section class="sf-section" aria-labelledby="install-final-title">
                        <h2 id="install-final-title" class="sf-section-title">Paso 4 · Cron y continuación</h2>
                        <p class="sf-hint">
                            Al finalizar se muestra una única línea orientativa de cron. El instalador no
                            instala cron ni despliega el sistema. Después debe continuar en <strong>/setup</strong>.
                        </p>
                        <p class="sf-code-line">
                            <code>{{ cronLine }}</code>
                        </p>
                    </section>
                </template>

                <p class="sf-footnote">
                    Versión de aplicación: {{ appVersion }} · Canal: {{ releaseChannel }}.
                    Si la instancia ya fue instalada, no se permite reinstalar ni borrar datos.
                </p>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

interface Step {
    id: number;
    label: string;
}

interface PreflightCheck {
    id: string;
    label: string;
    status: string;
    detail: string;
}

interface PreflightResult {
    ok: boolean;
    checks: PreflightCheck[];
}

interface ConnectionResult {
    ok: boolean;
    message: string;
    label: string;
}

const props = defineProps<{
    authorized: boolean;
    flow_token?: string;
    state?: { code: string; message: string };
    steps?: Step[];
    preflight?: PreflightResult | null;
    connection?: ConnectionResult | null;
    failure?: string | null;
    cron_line?: string;
    app_version?: string;
    release_channel?: string;
    storage_warning?: boolean;
    database_defaults?: {
        driver: string;
        host: string;
        port: number;
        database: string;
        username: string;
    };
}>();

const steps = computed<Step[]>(() => props.steps ?? []);
const cronLine = computed(() => props.cron_line ?? 'php artisan schedule:run');
const appVersion = computed(() => props.app_version ?? '—');
const releaseChannel = computed(() => props.release_channel ?? 'stable');

const bootstrapForm = useForm({
    bootstrap_token: '',
});

const bootstrapTokenInput = ref<HTMLInputElement | null>(null);
const wizardErrorBox = ref<HTMLElement | null>(null);

const preflightForm = useForm({
    install_token: props.flow_token ?? '',
});

const wizardForm = useForm({
    install_token: props.flow_token ?? '',
    driver: props.database_defaults?.driver ?? 'mariadb',
    host: props.database_defaults?.host ?? '',
    port: props.database_defaults?.port ?? 3306,
    database: props.database_defaults?.database ?? '',
    username: props.database_defaults?.username ?? '',
    db_password: '',
    confirm_destination: false,
    confirmation_phrase: '',
});

const currentStep = computed(() => {
    if (props.preflight?.ok && props.connection?.ok) {
        return 3;
    }

    if (props.preflight?.ok) {
        return 2;
    }

    return 1;
});

const canRun = computed(
    () =>
        Boolean(props.preflight?.ok)
        && Boolean(props.connection?.ok)
        && wizardForm.confirm_destination === true
        && wizardForm.confirmation_phrase.trim().toUpperCase() === 'INSTALAR',
);

function errorFor(form: { errors: Record<string, string | undefined> }, field: string): string | undefined {
    return form.errors[field];
}

function submitBootstrap(): void {
    bootstrapForm.post('/install/bootstrap', {
        preserveState: true,
        preserveScroll: true,
        onError: () => {
            // m-09: se limpia el token pero se conserva el error en el mismo
            // componente para que el mensaje siga visible.
            bootstrapForm.reset('bootstrap_token');
            nextTick(() => bootstrapTokenInput.value?.focus());
        },
    });
}

function focusWizardErrors(): void {
    nextTick(() => {
        const errors = wizardForm.errors as Record<string, string | undefined>;
        const ids: Record<string, string> = {
            host: 'install-host',
            port: 'install-port',
            database: 'install-database',
            username: 'install-username',
            db_password: 'install-db-password',
            driver: 'install-driver',
            confirm_destination: 'install-confirm-destination',
            confirmation_phrase: 'install-confirmation-phrase',
        };

        for (const field of Object.keys(ids)) {
            if (errors[field]) {
                const element = document.getElementById(ids[field]);

                if (element) {
                    element.focus();

                    return;
                }
            }
        }

        wizardErrorBox.value?.focus();
    });
}

function submitPreflight(): void {
    preflightForm.post('/install/preflight', {
        preserveState: true,
        preserveScroll: true,
        onError: focusWizardErrors,
    });
}

function submitConnection(): void {
    wizardForm.post('/install/connection', {
        preserveState: true,
        preserveScroll: true,
        onError: () => {
            wizardForm.db_password = '';
            focusWizardErrors();
        },
    });
}

function submitRun(): void {
    wizardForm.post('/install/run', {
        preserveState: true,
        preserveScroll: true,
        onError: focusWizardErrors,
    });
}
</script>

<style scoped>
.sf-install-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f3f4f6;
}

.sf-install-header {
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
    color: #303846;
}

.sf-brand .flash {
    color: #0856FF;
}

.sf-header-note {
    font-size: 0.8rem;
    color: #6b7280;
}

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 900px;
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

.sf-section {
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e5e7eb;
}

.sf-section-title {
    margin: 0 0 0.5rem;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.sf-steps {
    margin: 0 0 1rem;
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
    border-color: #0856FF;
    background: #eff6ff;
    color: #0856FF;
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
    background: #0856FF;
    color: #ffffff;
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
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
    min-height: 44px;
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
    border-color: #0856FF;
    outline: 2px solid #0856FF;
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

.sf-checkbox-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    grid-column: 1 / -1;
    min-height: 44px;
    font-size: 0.875rem;
    color: #374151;
}

.sf-checkbox {
    width: 1.15rem;
    height: 1.15rem;
    margin: 0;
}

.sf-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.sf-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0.55rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.sf-button:focus-visible {
    outline: 2px solid #0856FF;
    outline-offset: 2px;
}

.sf-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sf-button-primary {
    background: #0856FF;
    color: #ffffff;
}

.sf-button-primary:hover:not(:disabled) {
    background: #0856FF;
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

.sf-success {
    background: #dcfce7;
    color: #166534;
}

.sf-warning {
    background: #fef3c7;
    color: #92400e;
}

.sf-connection-label {
    display: block;
    margin-top: 0.35rem;
    font-weight: 600;
}

.sf-check-list {
    list-style: none;
    margin: 0 0 1rem;
    padding: 0;
    display: grid;
    gap: 0.5rem;
}

.sf-check {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    font-size: 0.85rem;
    color: #374151;
}

.sf-check-status {
    flex: 0 0 auto;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
}

.sf-check-ok {
    background: #dcfce7;
    color: #166534;
}

.sf-check-fail {
    background: #fee2e2;
    color: #991b1b;
}

.sf-check-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.sf-check-detail {
    color: #6b7280;
}

.sf-code-line {
    margin: 0.5rem 0 0;
    padding: 0.65rem 0.8rem;
    background: #303846;
    color: #e2e8f0;
    border-radius: 0.375rem;
    overflow-x: auto;
}

.sf-footnote {
    margin-top: 1.5rem;
    font-size: 0.75rem;
    color: #6b7280;
}

@media (max-width: 640px) {
    .sf-install-header {
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

