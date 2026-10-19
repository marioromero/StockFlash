<template>
    <div class="sf-login-page">
        <div class="sf-login-card">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>

            <h1 class="sf-title">{{ $page.props.welcome ?? 'Ingreso al sistema' }}</h1>

            <form @submit.prevent="submit" class="sf-form">
                <div v-if="error" class="sf-error" role="alert">
                    {{ error }}
                </div>

                <div v-if="warning" class="sf-warning" role="status">
                    {{ warning }}
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="email">Correo electrónico</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        class="sf-input"
                    />
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="password">Contraseña</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="sf-input"
                    />
                </div>

                <div class="sf-actions">
                    <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                        {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
                    </button>
                </div>
            </form>

            <p class="sf-help">
                ¿Olvidó su contraseña? Pida al administrador que la restablezca manualmente.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    error?: string;
    warning?: string;
}>();

const form = useForm({
    email: '',
    password: '',
});

const errors = computed(() => form.errors);

async function submit() {
    await form.post('/login', {
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            // Los errores de validación se muestran automáticamente por Inertia
        },
    });
}
</script>

<style scoped>
.sf-login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    padding: 1rem;
}

.sf-login-card {
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

.sf-actions {
    display: flex;
    justify-content: flex-end;
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

.sf-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.sf-warning {
    background: #fef3c7;
    color: #92400e;
    padding: 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.sf-help {
    font-size: 0.875rem;
    color: #6b7280;
    text-align: center;
    margin-top: 1rem;
}

@media (max-width: 640px) {
    .sf-login-card {
        padding: 1.5rem;
    }
}
</style>