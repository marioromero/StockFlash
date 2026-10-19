<template>
    <div class="sf-dashboard">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <button class="sf-button sf-button-secondary" @click="logout">
                    Cerrar sesión
                </button>
            </div>
        </header>

        <main class="sf-main">
            <section class="sf-card">
                <h2 class="sf-section-title">Dashboard</h2>
                <p class="sf-text">
                    Bienvenido al sistema StockFlash. Desde aquí puede acceder a los módulos disponibles según su rol y sucursal.
                </p>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { useInertia } from '@inertiajs/vue3';

const { data } = useInertia();

const userName = (data as any)?.user_name ?? 'Usuario';

async function logout() {
    await useInertia().post('/logout', {});
}
</script>

<style scoped>
.sf-dashboard {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

.sf-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    padding: 2rem;
    background: #f3f4f6;
}

.sf-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    max-width: 640px;
}

.sf-section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.75rem;
}

.sf-text {
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.5;
}

@media (max-width: 640px) {
    .sf-header {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
    }

    .sf-main {
        padding: 1rem;
    }
}
</style>