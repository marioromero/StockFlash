<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <Link href="/settings/pos-stations" class="sf-button sf-button-secondary">Volver a estaciones</Link>
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-titlebar">
                <div>
                    <h1 class="sf-title">{{ station.name }}</h1>
                    <p class="sf-subtitle">Código {{ station.code }} · {{ station.is_active ? 'Activa' : 'Inactiva' }}</p>
                </div>
            </div>

            <div v-if="flash.success" class="sf-message sf-success" role="status">{{ flash.success }}</div>
            <div v-if="readOnly" class="sf-message sf-warning" role="status">
                Licencia en modo solo lectura: la ficha puede consultarse, pero no se guardan cambios ni se
                vinculan o revocan dispositivos.
            </div>
            <div v-if="errorMessage" class="sf-message sf-error" role="alert">{{ errorMessage }}</div>

            <section class="sf-card" aria-labelledby="station-detail-title">
                <h2 id="station-detail-title" class="sf-section-title">Datos de la estación</h2>
                <dl class="sf-summary-grid">
                    <div class="sf-summary-item"><dt>Código</dt><dd>{{ station.code }}</dd></div>
                    <div class="sf-summary-item"><dt>Nombre</dt><dd>{{ station.name }}</dd></div>
                    <div class="sf-summary-item">
                        <dt>Sucursal</dt>
                        <dd>{{ station.branch ? `${station.branch.code} · ${station.branch.name}` : 'Sin sucursal' }}</dd>
                    </div>
                    <div class="sf-summary-item"><dt>Impresora</dt><dd>{{ printerLabel(station.printer_profile) }}</dd></div>
                    <div class="sf-summary-item">
                        <dt>Dispositivo vinculado</dt>
                        <dd>{{ station.has_device ? 'Sí' : 'No' }}</dd>
                    </div>
                    <div class="sf-summary-item">
                        <dt>Última actividad registrada</dt>
                        <dd>{{ station.last_seen_at ? station.last_seen_at : 'Sin actividad registrada' }}</dd>
                    </div>
                    <div class="sf-summary-item"><dt>Estado</dt><dd>{{ station.is_active ? 'Activa' : 'Inactiva' }}</dd></div>
                </dl>
                <p class="sf-section-hint">
                    El token del dispositivo es interno y nunca se muestra, copia ni registra. Solo se informa si este
                    navegador está vinculado.
                </p>
            </section>

            <section v-if="!readOnly" class="sf-card" aria-labelledby="station-edit-title">
                <h2 id="station-edit-title" class="sf-section-title">Editar estación</h2>
                <p class="sf-section-hint">
                    El código no se puede modificar. Cambiar la sucursal invalida el dispositivo vinculado. Solo puede
                    asignar sucursales activas dentro de su alcance.
                </p>

                <form class="sf-form" novalidate @submit.prevent="submitUpdate">
                    <div class="sf-grid">
                        <div class="sf-field">
                            <label class="sf-label" for="edit-station-name">Nombre *</label>
                            <input
                                id="edit-station-name"
                                v-model="form.name"
                                type="text"
                                maxlength="100"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="sf-error-text">{{ form.errors.name }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-station-branch">Sucursal</label>
                            <select
                                id="edit-station-branch"
                                v-model="form.branch_id"
                                class="sf-input"
                                :class="{ 'sf-input-error': form.errors.branch_id }"
                            >
                                <option value="">Mantener sucursal actual</option>
                                <option v-for="branch in branchOptions" :key="branch.id" :value="String(branch.id)">
                                    {{ branch.code }} · {{ branch.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.branch_id" class="sf-error-text">{{ form.errors.branch_id }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="edit-station-printer">Perfil de impresora</label>
                            <select id="edit-station-printer" v-model="form.printer_profile" class="sf-input">
                                <option value="">Sin impresora</option>
                                <option v-for="profile in printerProfiles" :key="profile" :value="profile">
                                    {{ printerLabel(profile) }}
                                </option>
                            </select>
                            <p v-if="form.errors.printer_profile" class="sf-error-text">
                                {{ form.errors.printer_profile }}
                            </p>
                        </div>
                    </div>

                    <p v-if="form.errors.read_only" class="sf-error-text" role="alert">{{ form.errors.read_only }}</p>
                    <p v-if="form.errors.station" class="sf-error-text" role="alert">{{ form.errors.station }}</p>

                    <div class="sf-form-actions">
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                            Guardar cambios
                        </button>
                        <button
                            v-if="station.is_active"
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processingStatus"
                            @click="changeStatus('deactivate')"
                        >
                            Desactivar
                        </button>
                        <button
                            v-else
                            type="button"
                            class="sf-button sf-button-secondary"
                            :disabled="processingStatus"
                            @click="changeStatus('activate')"
                        >
                            Activar
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="!readOnly" class="sf-card" aria-labelledby="station-device-title">
                <h2 id="station-device-title" class="sf-section-title">Dispositivo de este navegador</h2>
                <p class="sf-section-hint">
                    Vincular este navegador genera un token aleatorio nuevo y reemplaza cualquier token anterior de la
                    estación. Revocar elimina el token vigente; el navegador deberá vincularse otra vez.
                </p>
                <p class="sf-text-muted">
                    Estado del dispositivo: <strong>{{ station.has_device ? 'Vinculado' : 'Sin vincular' }}</strong>
                </p>
                <div class="sf-form-actions">
                    <button
                        type="button"
                        class="sf-button sf-button-primary"
                        :disabled="processingDevice || !station.is_active"
                        @click="bindBrowser"
                    >
                        Vincular este navegador
                    </button>
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        :disabled="processingDevice || !station.has_device"
                        @click="revokeDevice"
                    >
                        Revocar dispositivo
                    </button>
                </div>
                <p v-if="!station.is_active" class="sf-section-hint">
                    Una estación inactiva no puede vincular un dispositivo hasta ser activada.
                </p>
                <p v-if="station.is_active && !station.has_device" class="sf-section-hint">
                    La estación está activa pero aún no tiene un dispositivo vinculado.
                </p>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

interface BranchOption {
    id: number;
    code: string;
    name: string;
    is_active?: boolean;
}

interface StationDetail {
    id: number;
    code: string;
    name: string;
    printer_profile: string | null;
    is_active: boolean;
    has_device: boolean;
    last_seen_at: string | null;
    branch: BranchOption | null;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: { user?: { name?: string } | null };
    flash?: { success?: string | null; warning?: string | null; error?: string | null };
    errors?: Record<string, string>;
}

const props = defineProps<{
    station: StationDetail;
    branch_options: BranchOption[];
    printer_profiles: string[];
    read_only: boolean;
}>();

const page = usePage<SharedPageProps>();
const flash = computed(() => page.props.flash ?? {});
const userName = computed(() => page.props.auth?.user?.name ?? 'Usuario');
const errorMessage = computed(() => Object.values(page.props.errors ?? {})[0] ?? flash.value.error ?? null);
const readOnly = computed(() => props.read_only === true);
const branchOptions = computed(() => props.branch_options);
const printerProfiles = computed(() => props.printer_profiles);
const processingStatus = ref(false);
const processingDevice = ref(false);

const PRINTER_LABELS: Record<string, string> = {
    thermal58: 'Térmica 58 mm',
    thermal80: 'Térmica 80 mm',
    a4: 'A4',
};

function printerLabel(profile: string | null): string {
    if (!profile) {
        return 'Sin impresora';
    }

    return PRINTER_LABELS[profile] ?? profile;
}

const form = useForm({
    name: props.station.name,
    branch_id: '',
    printer_profile: props.station.printer_profile ?? '',
    read_only: '',
    station: '',
});

function submitUpdate(): void {
    const currentBranchId = props.station.branch?.id ?? null;
    const submittedBranchId = form.branch_id === '' ? null : Number(form.branch_id);

    const payload: Record<string, unknown> = {
        name: form.name,
        printer_profile: form.printer_profile === '' ? null : form.printer_profile,
    };

    if (submittedBranchId !== null && submittedBranchId !== currentBranchId) {
        payload.branch_id = submittedBranchId;
    }

    form.transform(() => payload).patch(`/settings/pos-stations/${props.station.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.branch_id = '';
        },
    });
}

function changeStatus(action: 'activate' | 'deactivate'): void {
    const message = action === 'activate'
        ? '¿Activar esta estación POS? No se emitirá un token automáticamente.'
        : '¿Desactivar esta estación POS? El dispositivo vinculado quedará invalidado.';

    if (!window.confirm(message)) {
        return;
    }

    processingStatus.value = true;
    router.post(`/settings/pos-stations/${props.station.id}/${action}`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingStatus.value = false;
        },
    });
}

function bindBrowser(): void {
    if (!window.confirm('¿Vincular este navegador a la estación? Un token anterior de la estación quedará invalidado.')) {
        return;
    }

    processingDevice.value = true;
    router.post(`/settings/pos-stations/${props.station.id}/bind`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingDevice.value = false;
        },
    });
}

function revokeDevice(): void {
    if (!window.confirm('¿Revocar el dispositivo vinculado? El navegador deberá vincularse de nuevo.')) {
        return;
    }

    processingDevice.value = true;
    router.post(`/settings/pos-stations/${props.station.id}/revoke`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingDevice.value = false;
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
@media (max-width: 720px) { .sf-header, .sf-main { padding: 1rem; } }
</style>
