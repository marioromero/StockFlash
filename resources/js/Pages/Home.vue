<template>
    <main class="sf-shell">
        <section class="sf-card" aria-labelledby="sf-title">
            <p class="sf-brand" aria-label="StockFlash">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </p>
            <h1 id="sf-title">Base local operativa</h1>
            <p class="sf-sub">
                Bootstrap BOOT-001: Laravel + Vue 3 + Inertia + TypeScript.
                Sin módulos comerciales activos.
            </p>
            <span class="sf-badge">BOOT-001 · {{ version }}</span>
        </section>
    </main>
</template>

<script setup lang="ts">
defineProps<{ version: string }>();
</script>
