<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * One slow query of alveby/atrium-monitoring: its SQL with the values taken
 * out, its time by day and the routes it runs on.
 */
import { Head } from '@inertiajs/vue3'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import MonitoringChart from '@atrium/Components/Admin/Monitoring/MonitoringChart.vue'
import { label } from '@atrium/Components/Admin/Monitoring/labels'

const props = defineProps({
    t: { type: Object, required: true },
    query: { type: Object, required: true },
    chart: { type: String, required: true },
    points: { type: Array, required: true },
    routes: { type: Array, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
</script>

<template>
    <Head :title="l('columns.query')" />
    <div class="mb-6">
        <a :href="route('admin.monitoring.index')" class="text-sm text-accent underline" dusk="monitoring-back">← {{ l('back') }}</a>
        <h1 class="mt-2 text-2xl font-bold text-fg">{{ l('columns.query') }}</h1>
    </div>

    <div class="space-y-6">
        <AdminCard>
            <pre dusk="query-sql" class="whitespace-pre-wrap break-all font-mono text-sm text-fg">{{ query.sql }}</pre>
            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted">
                <div><dt class="inline">{{ l('columns.connection') }}:</dt> <dd class="inline font-mono">{{ query.connection }}</dd></div>
                <div><dt class="inline">{{ l('columns.last_seen') }}:</dt> <dd class="inline tabular-nums">{{ query.last_seen }}</dd></div>
            </dl>
        </AdminCard>

        <MonitoringChart :title="l('charts.query_time')" :svg="chart" :points="points" :t="t" dusk="chart-query" />

        <AdminCard no-padding :title="l('sections.routes_of_query')">
            <p v-if="!routes.length" class="p-4 text-sm text-muted">{{ l('no_data') }}</p>
            <div v-else class="overflow-x-auto">
                <table dusk="query-routes" class="min-w-full text-sm">
                    <thead class="bg-surface-alt">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.route') }}</th>
                            <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.times') }}</th>
                            <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.average') }}</th>
                            <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.slowest') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="row in routes" :key="row.route">
                            <td class="px-3 py-2">
                                <a :href="route('admin.monitoring.index', { route: row.route })" class="font-mono text-accent underline">{{ row.route }}</a>
                                <span class="block text-xs text-muted font-mono break-all">{{ row.path }}</span>
                                <a v-if="row.errors_url" :href="row.errors_url" class="block text-xs text-accent underline" dusk="query-route-errors">{{ l('links.errors') }}</a>
                            </td>
                            <td class="px-3 py-2 text-right text-fg tabular-nums">{{ row.times }}</td>
                            <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ row.average }}</td>
                            <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ row.slowest }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AdminCard>
    </div>
</template>
