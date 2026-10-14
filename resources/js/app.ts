import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => (title ? `${title} — StockFlash` : 'StockFlash'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, any>;
        const page = pages[`./Pages/${name}.vue`];
        if (!page) {
            throw new Error(`Página Inertia no encontrada: ${name}`);
        }
        return page.default as any;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
