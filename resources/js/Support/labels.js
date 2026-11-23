/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * A string of a module by its dotted key, with :placeholders filled in.
 * The strings come from the server in a `t` prop: they are the module's
 * (lang/{locale}/*.php of its package), not in the panel's own dictionary.
 * A missing key shows itself, so it is easy to spot.
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
