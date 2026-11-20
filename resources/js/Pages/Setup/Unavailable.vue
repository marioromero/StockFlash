<template>
    <div class="sf-unavailable-page">
        <header class="sf-header">
            <div class="sf-brand" aria-label="StockFlash">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <p class="sf-header-note">Configuración inicial</p>
        </header>

        <main class="sf-main">
            <section class="sf-card" aria-labelledby="setup-unavailable-title">
                <h1 id="setup-unavailable-title" class="sf-title">Asistente no disponible</h1>

                <div class="sf-message" role="alert" aria-live="assertive">
                    <p>{{ message }}</p>
                    <p v-if="reason_code === 'NOT_READY_FOR_PRODUCTION'" class="sf-code">
                        Código: NOT_READY_FOR_PRODUCTION
                    </p>
                </div>

                <p class="sf-hint">
                    No se muestran formularios ni datos de la instalación. Si necesita asistencia,
                    contacte al responsable técnico del sistema.
                </p>

                <div class="sf-actions">
                    <a href="/login" class="sf-button sf-button-secondary">Ir al ingreso</a>
                    <a href="/roadmap" class="sf-button sf-button-secondary">Ver avance del proyecto</a>
                    <a href="/docs/" class="sf-button sf-button-secondary">Abrir guía de usuario</a>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    reason_code?: string | null;
    message?: string | null;
}>();
</script>

<style scoped>
.sf-unavailable-page {
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

.sf-header-note {
    font-size: 0.8rem;
    color: #6b7280;
}

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 720px;
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

.sf-message {
    margin: 1rem 0;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: #fef3c7;
    color: #92400e;
}

.sf-code {
    margin-top: 0.5rem;
    font-weight: 700;
}

.sf-hint {
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.5;
}

.sf-actions {
    margin-top: 1.25rem;
    display: flex;
    gap: 0.6rem;
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

.sf-button-secondary {
    background: #e5e7eb;
    color: #111827;
}

.sf-button-secondary:hover {
    background: #d1d5db;
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
}
</style>
