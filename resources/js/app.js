/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import '../css/atrium.css';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { i18n } from './Support/i18n';
import { applyTheme } from './Support/theme';

// The server sets <html lang> only on a full load; keep it right after an
// Inertia visit too, so screen readers pronounce the new language. `navigate`
// is skipped when a visit replaces the same URL (the language switch does
// that), hence `success` as well; history moves only fire `navigate`.
const syncLang = (event) => {
    const locale = event.detail.page.props.locale;
    if (locale) {
        document.documentElement.lang = locale.replace('_', '-');
    }
};
router.on('success', syncLang);
router.on('navigate', syncLang);

// The colour theme follows the account too: picked in another tab, or reset
// to the default on the sign-in page.
const syncTheme = (event) => applyTheme(event.detail.page.props.theme);
router.on('success', syncTheme);
router.on('navigate', syncTheme);

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
        applyTheme(props.initialPage.props.theme);
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: 'var(--atrium-accent)',
    },
});
