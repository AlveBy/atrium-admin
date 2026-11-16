import '../css/atrium.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => {
        const brand = document.querySelector('title')?.dataset.brand || 'Atrium';
        return title ? `${title} — ${brand}` : brand;
    },
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];
        if (!page) {
            throw new Error(`Atrium: page "${name}" not found`);
        }
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
