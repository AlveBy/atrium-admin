<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * "Errors in 24 hours" tile of alveby/atrium-errors: the counts, the
 * noisiest errors and the events hour by hour. Its strings come with the
 * data, as the module is not part of the panel's dictionary.
 */
import { Link } from '@inertiajs/vue3'
import BarChart from '@atrium/Components/Admin/UI/BarChart.vue'

defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-surface rounded-lg shadow px-5 py-4" data-gadget="errors" dusk="errors-gadget">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-semibold text-fg">{{ title || data.strings.title }}</h2>
            <Link :href="data.url" class="text-sm text-accent hover:text-accent-hover hover:underline" dusk="errors-gadget-all">{{ data.strings.all }}</Link>
        </div>

        <dl class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div v-for="key in ['events', 'new', 'regressed', 'unresolved']" :key="key" class="flex flex-col-reverse" :data-count="key">
                <dt class="text-xs text-muted">{{ data.strings[key] }}</dt>
                <dd class="text-2xl font-semibold text-fg tabular-nums">{{ data[key] }}</dd>
            </div>
        </dl>

        <p v-if="!data.events" class="mt-4 text-sm text-muted">{{ data.strings.none }}</p>

        <template v-else>
            <div class="mt-4">
                <BarChart :points="data.chart" :every="6" :height="72" :label="data.strings.chart" />
            </div>

            <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-muted">{{ data.strings.top }}</h3>
            <ul class="mt-2 divide-y divide-line">
                <li v-for="issue in data.top" :key="issue.id" class="py-1.5 flex items-baseline gap-3 text-sm">
                    <span class="w-10 shrink-0 text-right font-semibold text-fg tabular-nums">{{ issue.count }}</span>
                    <Link :href="issue.url" class="min-w-0 truncate text-accent hover:text-accent-hover hover:underline" dusk="errors-gadget-issue">
                        <span class="font-medium">{{ issue.class }}</span>: {{ issue.title }}
                    </Link>
                </li>
            </ul>
        </template>
    </div>
</template>
