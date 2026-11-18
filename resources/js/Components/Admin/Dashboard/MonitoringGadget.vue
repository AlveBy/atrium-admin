<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * "Speed and uptime" dashboard tile of alveby/atrium-monitoring: the
 * visitors' Web Vitals at p75 over a week, uptime over 30 days, what is down
 * now and the slowest routes. A rating is written out in words, never shown
 * by colour alone.
 */
import { usePage } from '@inertiajs/vue3'
import { number } from '@atrium/Components/Admin/Monitoring/labels'

const props = defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})

const page = usePage()
const rating = (key) => (key ? props.data.ratings[key] : '—')
</script>

<template>
    <div class="bg-surface rounded-lg shadow px-5 py-4" data-gadget="monitoring">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-semibold text-fg">{{ title || data.labels.title }}</h2>
            <a :href="data.url" dusk="monitoring-open" class="text-xs text-accent underline">{{ data.labels.open }}</a>
        </div>

        <p v-if="data.down.length" dusk="monitoring-down" role="status" class="mt-3 rounded border border-danger bg-danger-soft px-3 py-2 text-sm font-medium text-danger-soft-fg">
            {{ data.labels.down }}: {{ data.down.join(', ') }}
        </p>

        <div class="mt-3 grid grid-cols-2 gap-4">
            <div>
                <h3 class="text-xs text-muted">{{ data.labels.uptime }}</h3>
                <p class="mt-1 text-2xl font-semibold text-fg tabular-nums" dusk="monitoring-uptime">{{ data.uptime === null ? '—' : `${number(data.uptime, page.props.locale)}%` }}</p>
            </div>
            <div>
                <h3 class="text-xs text-muted">{{ data.labels.slowest }}</h3>
                <ol class="mt-1 space-y-0.5 text-xs">
                    <li v-for="row in data.slowest" :key="row.route" class="flex justify-between gap-2">
                        <span class="font-mono text-fg truncate">{{ row.route }}</span>
                        <span class="text-fg tabular-nums whitespace-nowrap">{{ row.p75 }}</span>
                    </li>
                    <li v-if="!data.slowest.length" class="text-muted">—</li>
                </ol>
            </div>
        </div>

        <h3 class="mt-4 text-xs text-muted">{{ data.labels.vitals }}</h3>
        <dl class="mt-1 grid grid-cols-5 gap-2">
            <div v-for="vital in data.vitals" :key="vital.key" :data-vital="vital.key" :title="vital.label">
                <dt class="text-xs font-medium text-muted">{{ vital.key.toUpperCase() }}</dt>
                <dd class="text-sm font-semibold text-fg tabular-nums">{{ vital.value }}</dd>
                <dd class="text-xs text-fg leading-tight">{{ rating(vital.rating) }}</dd>
            </div>
        </dl>
    </div>
</template>
