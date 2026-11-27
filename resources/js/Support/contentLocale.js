/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, provide, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Content languages on a record form.
 *
 * A translatable field holds {"en": "…", "de": "…"}; the form shows one
 * language at a time, picked with ContentLocaleSwitcher. FormField reads the
 * chosen language from the injected `contentLocale`.
 */
export function useContentLocale(fields) {
    const locales = computed(() => usePage().props.contentLocales || []);
    const fallback = locales.value.find((language) => language.default) || locales.value[0];
    const locale = ref(fallback ? fallback.code : null);

    provide('contentLocale', locale);

    // The switch only makes sense with something to switch: a translatable
    // field, or a block of a layout field with a translatable setting.
    const showSwitcher = computed(() => locales.value.length > 1 && fields().some((field) => field.translatable
        || field.types?.some?.((type) => type.fields?.some((setting) => setting.translatable))));

    return { locale, locales, showSwitcher };
}

/**
 * The starting value of a form field: a translatable one gets a key for
 * every content language, so each input has something to bind to.
 */
export function initialValue(field, stored) {
    if (!field.translatable) {
        return stored ?? field.default ?? '';
    }

    const locales = usePage().props.contentLocales || [];
    const translations = stored && typeof stored === 'object' && !Array.isArray(stored) ? stored : {};
    const value = {};

    locales.forEach((language) => {
        value[language.code] = translations[language.code] ?? (language.default ? (field.default ?? '') : '');
    });

    return value;
}

/** Errors of a translatable field come as "name.de". */
export function fieldHasError(errors, field) {
    return Object.keys(errors).some((key) => key === field.name || key.startsWith(`${field.name}.`));
}
