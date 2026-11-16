import { usePage } from '@inertiajs/vue3';
import { pluralIndex } from './plural';

/**
 * Interface strings of the panel.
 *
 * The dictionary comes from the server (lang/{locale}/*.php of the package,
 * flattened to dot keys) as the `translations` shared prop, so PHP and Vue
 * read the same files. Usage mirrors Laravel:
 *
 *   t('common.save')                         → "Save"
 *   t('resource.deleted', { name: 'News' })  → "News deleted" (":name" in the string)
 *   tc('files.count', 3)                     → "3 files"  ("one|many" forms, :count filled in)
 *
 * In templates the same functions are available as $t and $tc.
 */

function dictionary() {
    return usePage().props.translations || {};
}

function locale() {
    return usePage().props.locale || 'en';
}

function replace(line, replacements) {
    return Object.entries(replacements).reduce(
        (text, [key, value]) => text.replaceAll(`:${key}`, String(value ?? '')),
        line,
    );
}

/** A missing key shows itself, so it is easy to spot and grep for. */
export function t(key, replacements = {}) {
    const line = dictionary()[key];

    return typeof line === 'string' ? replace(line, replacements) : key;
}

export function tc(key, count, replacements = {}) {
    const line = dictionary()[key];

    if (typeof line !== 'string') {
        return key;
    }

    const forms = line.split('|');
    const form = forms[Math.min(pluralIndex(locale(), count), forms.length - 1)];

    return replace(form, { count, ...replacements });
}

export const i18n = {
    install(app) {
        app.config.globalProperties.$t = t;
        app.config.globalProperties.$tc = tc;
    },
};
