/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * A string of the monitoring module by its dotted key, with :placeholders
 * filled in. The strings come from the server in a `t` prop: they are the
 * module's, not in the panel's own dictionary.
 */
export function label(strings, key, params = {}) {
    let line = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), strings)

    if (typeof line !== 'string') {
        return key
    }

    for (const [name, value] of Object.entries(params)) {
        line = line.replaceAll(`:${name}`, String(value ?? ''))
    }

    return line
}

/**
 * A number in the panel's language: 99,5 in Russian, 99.5 in English.
 */
export function number(value, locale) {
    return Number(value).toLocaleString(String(locale || 'en').replace('_', '-'), { maximumFractionDigits: 2 })
}
