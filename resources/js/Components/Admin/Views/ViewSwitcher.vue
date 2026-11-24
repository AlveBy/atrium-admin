<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { router } from '@inertiajs/vue3'

const props = defineProps({
    routeName: String,
    // The looks of the list the module has: 'table' and the views it declared.
    views: { type: Array, default: () => [] },
    // The one drawn now.
    view: String,
    // What the new look keeps from the address: the search and the filters.
    query: { type: Object, default: () => ({}) },
})

// The name and the icon of every look. A look is told apart by its word and
// its icon, not by colour: the chosen one is also underlined and `aria-pressed`.
const looks = {
    table: { label: 'views.table', path: 'M3 5h18M3 12h18M3 19h18' },
    kanban: { label: 'views.kanban.name', path: 'M4 4h4v16H4zM10 4h4v10h-4zM16 4h4v13h-4z' },
}

const choose = (type) => {
    if (type !== props.view) {
        router.get(route(`${props.routeName}.index`), { ...props.query, view: type }, { preserveScroll: true })
    }
}
</script>

<template>
    <div role="group" :aria-label="$t('views.label')" data-view-switcher class="inline-flex rounded-lg border border-field bg-surface p-0.5">
        <button
            v-for="type in views"
            :key="type"
            type="button"
            :data-view-choice="type"
            :aria-pressed="type === view"
            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-fg focus:outline-none focus:ring-2 focus:ring-focus"
            :class="type === view ? 'bg-accent-soft font-semibold text-accent-soft-fg underline underline-offset-4' : 'hover:bg-hover'"
            @click="choose(type)"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="looks[type]?.path" />
            </svg>
            {{ $t(looks[type]?.label ?? type) }}
        </button>
    </div>
</template>
