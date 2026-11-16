/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

// Applies the admin's colour theme to <html data-theme>. The server renders
// the right theme already (and a head script resolves "system" before the
// first paint); this keeps it right after a switch and when the operating
// system turns dark or light while the panel is open.

export const THEMES = ['graphite', 'day', 'night', 'sand', 'contrast']

const dark = typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-color-scheme: dark)')
    : null

let choice = null

const resolve = (value) => {
    if (value === 'system') {
        return dark?.matches ? 'night' : 'graphite'
    }
    return THEMES.includes(value) ? value : 'graphite'
}

export function applyTheme(value) {
    if (!value) return
    choice = value
    const root = document.documentElement
    root.dataset.themeChoice = value
    root.dataset.theme = resolve(value)
}

dark?.addEventListener('change', () => {
    if (choice === 'system') {
        applyTheme(choice)
    }
})
