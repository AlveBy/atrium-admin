<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * "Performance monitor" dashboard tile: how many pages a second the site
 * renders, and on request the heaviest pages.
 */
import { ref } from 'vue'

defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})

const expanded = ref(false)
</script>

<template>
    <div class="bg-surface rounded-lg shadow px-5 py-4" data-gadget="performance">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-sm font-semibold text-fg">{{ title || $t('dashboard.performance.title') }}</h2>

            <button
                v-if="data.slowest.length"
                type="button"
                class="rounded-md border border-field bg-surface px-3 py-1 text-sm text-fg hover:bg-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                :aria-expanded="expanded ? 'true' : 'false'"
                data-action="performance-details"
                @click="expanded = !expanded"
            >
                {{ expanded ? $t('dashboard.performance.collapse') : $t('dashboard.performance.details') }}
            </button>
        </div>

        <div v-if="data.rate" class="mt-3">
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-semibold text-fg tabular-nums">{{ data.rate }}</span>
                <span class="text-sm text-muted">{{ data.unit }}</span>
            </div>
            <div class="text-xs text-muted">{{ data.caption }}</div>
        </div>
        <p v-else class="mt-3 text-sm text-muted">
            {{ $t('dashboard.performance.pending') }}
        </p>

        <p v-if="data.rate" class="mt-3 text-xs text-muted">
            {{ $t('dashboard.performance.note', { seconds: data.averageSeconds, pages: data.samples, days: data.windowDays }) }}
        </p>

        <div v-if="expanded" class="mt-3 border-t border-line pt-3" data-performance-details>
            <h3 class="text-xs font-semibold text-fg">{{ $t('dashboard.performance.slowest_title') }}</h3>
            <ul class="mt-1 divide-y divide-line text-sm">
                <li v-for="row in data.slowest" :key="row.path" class="flex justify-between gap-3 py-1.5">
                    <span class="truncate text-fg" :title="row.path">{{ row.path }}</span>
                    <span class="whitespace-nowrap text-muted tabular-nums">{{ $t('dashboard.performance.slowest_row', { ms: row.ms, hits: row.hits }) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
