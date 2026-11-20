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
            <nav class="sf-public-links" aria-label="Accesos públicos">
                <a href="/roadmap">Ver funciones y avance del proyecto</a>
                <a href="/docs/">Abrir guía de usuario</a>
                <a href="/login">Ingresar al sistema</a>
            </nav>
        </section>
    </main>
</template>

<script setup lang="ts">
defineProps<{ version: string }>();
</script>

<style scoped>
.sf-public-links{display:flex;flex-wrap:wrap;gap:0.7rem;margin-top:1.1rem}
.sf-public-links a{border:1px solid #cbd5e1;border-radius:0.5rem;padding:0.55rem 0.75rem;background:#fff;color:#1d4ed8;text-decoration:none;font-size:0.9rem;font-weight:600}
.sf-public-links a:hover{background:#eff6ff}
.sf-public-links a:focus-visible{outline:3px solid #0d9488;outline-offset:2px}
</style>
