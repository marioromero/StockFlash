<template>
    <div class="sf-password-page">
        <div class="sf-password-card">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>

            <h1 class="sf-title">Cambio de contraseña</h1>

            <form @submit.prevent="submit" class="sf-form">
                <div v-if="error" class="sf-error" role="alert">
                    {{ error }}
                </div>

                <div v-if="success" class="sf-success" role="status">
                    {{ success }}
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="current_password">Contraseña actual</label>
                    <input
                        id="current_password"
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="sf-input"
                    />
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="password">Contraseña nueva</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="sf-input"
                    />
                    <p class="sf-hint">Mínimo 15 caracteres.</p>
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="password_confirmation">Confirmar contraseña nueva</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="sf-input"
                    />
                </div>

                <div class="sf-actions">
                    <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                        {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                </div>
            </form>

            <div class="sf-actions">
                <button type="button" class="sf-button sf-button-secondary" @click="$inertia.get('/dashboard')">
                    Volver al dashboard
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    error?: string;
    success?: string;
}>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const errors = computed(() => form.errors);

async function submit() {
    await form.put('/password/change', {
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>

<style scoped>
.sf-password-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    padding: 1rem;
}

.sf-password-card {
    width: 100%;
    max-width: 400px;
    background: #ffffff;
    padding: 2rem;
    border-radius: 0.75rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.sf-brand {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
    margin-bottom: 1.5rem;
    text-align: center;
}

.sf-brand .stock {
    color: #0f172a;
}

.sf-brand .flash {
    color: #2563eb;
}

.sf-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
    text-align: center;
    margin-bottom: 1.5rem;
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.sf-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.sf-input {
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 1rem;
}

.sf-input:focus {
    border-color: #2563eb;
    outline: none;
    ring: 2px;
    ring-color: #93c5fd;
}

.sf-hint {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.sf-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

.sf-button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
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

.sf-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.sf-success {
    background: #dcfce7;
    color: #166534;
    padding: 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

@media (max-width: 640px) {
    .sf-password-card {
        padding: 1.5rem;
    }
}
</style>