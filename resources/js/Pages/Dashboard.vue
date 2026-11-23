<template>
    <div class="sf-dashboard">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <span class="sf-user-name">{{ userName }}</span>
                <form @submit.prevent="logout" class="sf-logout-form">
                    <button type="submit" class="sf-button sf-button-secondary">Cerrar sesión</button>
                </form>
            </div>
        </header>

        <main class="sf-main">
            <section
                v-if="licenseNotice"
                class="sf-license-banner"
                :class="`sf-license-banner--${licenseNotice.severity}`"
                :role="licenseNotice.severity === 'danger' ? 'alert' : 'status'"
            >
                <p class="sf-license-banner-message">{{ licenseNotice.message }}</p>
                <p v-if="licenseNotice.date" class="sf-license-banner-date">
                    {{ licenseNotice.date_label }}:
                    <time :datetime="licenseNotice.date">{{ licenseNotice.date }}</time>
                </p>
            </section>

            <section class="sf-card">
                <h2 class="sf-section-title">Dashboard</h2>
                <p class="sf-text">
                    Bienvenido al sistema StockFlash. Desde aquí puede acceder a los módulos disponibles según su rol y sucursal.
                </p>
                <nav class="sf-nav" aria-label="Accesos rápidos">
                    <Link v-if="canViewCatalog" href="/catalog/products" class="sf-button sf-button-primary">
                        Productos
                    </Link>
                    <Link v-if="canViewCatalog" href="/catalog/masters" class="sf-button sf-button-primary">
                        Maestros de catálogo
                    </Link>
                    <Link v-if="canViewInventory" href="/inventory" class="sf-button sf-button-primary">
                        Inventario
                    </Link>
                    <Link v-if="canViewCustomers" href="/customers" class="sf-button sf-button-primary">
                        Clientes
                    </Link>
                    <Link v-if="canViewSellers" href="/sellers" class="sf-button sf-button-primary">
                        Vendedores
                    </Link>
                    <Link v-if="canManageUsers" href="/users" class="sf-button sf-button-primary">
                        Usuarios
                    </Link>
                    <Link v-if="canManageBranches" href="/branches" class="sf-button sf-button-primary">
                        Sucursales
                    </Link>
                    <Link v-if="canManageSettings" href="/settings/receipt" class="sf-button sf-button-primary">
                        Ajustes de comprobante
                    </Link>
                    <Link v-if="canManageSettings" href="/settings/business" class="sf-button sf-button-primary">
                        Datos del comercio
                    </Link>
                    <Link v-if="canManageSettings" href="/settings/general" class="sf-button sf-button-primary">
                        Preferencias generales
                    </Link>
                    <a
                        href="/roadmap"
                        class="sf-button sf-button-secondary"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Abrir el avance del proyecto en una nueva pestaña"
                    >
                        Avance del proyecto
                    </a>
                    <Link href="/password/change" class="sf-button sf-button-secondary">
                        Cambiar contraseña
                    </Link>
                    <a
                        href="/docs/"
                        class="sf-button sf-button-secondary"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Abrir la guía de ayuda en una nueva pestaña"
                    >
                        Ayuda
                    </a>
                </nav>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/core';

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { name?: string } | null;
        permissions?: string[];
    };
}

interface LicenseNotice {
    type: string;
    severity: string;
    message: string;
    date: string | null;
    date_label: string | null;
}

const props = defineProps<{
    user_name?: string;
    license_notice?: LicenseNotice | null;
    can_view_inventory?: boolean;
    can_view_customers?: boolean;
    can_view_sellers?: boolean;
}>();

const licenseNotice = computed(() => props.license_notice ?? null);

const page = usePage<SharedPageProps>();
const userName = computed(() => page.props.auth?.user?.name ?? props.user_name ?? 'Usuario');
const canViewCatalog = computed(() => {
    // `auth.permissions` ya está acotado por servidor a la sucursal por
    // defecto. La ruta GET exige 'catalog.view'; 'catalog.manage' habilita
    // escrituras dentro de la pantalla, pero por sí solo recibiría 403 en el
    // GET, así que no se muestra el enlace con solo manage.
    return (page.props.auth?.permissions ?? []).includes('catalog.view');
});
const canManageUsers = computed(() =>
    (page.props.auth?.permissions ?? []).includes('users.manage')
);
const canViewInventory = computed(() =>
    props.can_view_inventory === true
    || (page.props.auth?.permissions ?? []).includes('inventory.view')
);
const canViewCustomers = computed(() =>
    props.can_view_customers
    ?? (page.props.auth?.permissions ?? []).includes('customers.view')
);
const canViewSellers = computed(() => props.can_view_sellers === true);
const canManageBranches = computed(() =>
    (page.props.auth?.permissions ?? []).includes('settings.manage')
);
const canManageSettings = computed(() =>
    (page.props.auth?.permissions ?? []).includes('settings.manage')
);

async function logout(event: SubmitEvent) {
    event.preventDefault();
    await router.post('/logout');
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

.sf-logout-form {
    display: inline;
}

.sf-main {
    flex: 1;
    padding: 2rem;
    background: #f3f4f6;
}

.sf-license-banner {
    max-width: 640px;
    margin-bottom: 1rem;
    padding: 0.85rem 1rem;
    border-left: 4px solid #d97706;
    border-radius: 0.5rem;
    background: #fffbeb;
    color: #78350f;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.sf-license-banner--warning {
    border-left-color: #d97706;
    background: #fffbeb;
    color: #78350f;
}

.sf-license-banner--danger {
    border-left-color: #dc2626;
    background: #fef2f2;
    color: #7f1d1d;
}

.sf-license-banner--info {
    border-left-color: #2563eb;
    background: #eff6ff;
    color: #1e3a8a;
}

.sf-license-banner-message {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.5;
}

.sf-license-banner-date {
    margin-top: 0.25rem;
    font-size: 0.8125rem;
    line-height: 1.4;
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

.sf-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.25rem;
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