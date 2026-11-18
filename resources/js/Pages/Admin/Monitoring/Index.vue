<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Monitoring" screen of alveby/atrium-monitoring: server timings, the
 * visitors' Web Vitals and uptime, by day, week or month.
 *
 * The charts come drawn from the server (SvgChart): p50, p75 and p95 are
 * told apart by marker shape, line style and a name at the line's end, not
 * by colour alone, and every chart has its numbers in a table under it. The
 * module's strings come in the `t` prop.
 */
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import MonitoringChart from '@atrium/Components/Admin/Monitoring/MonitoringChart.vue'
import { label, number } from '@atrium/Components/Admin/Monitoring/labels'

const props = defineProps({
    t: { type: Object, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    range: { type: Object, required: true },
    server: { type: Object, required: true },
    vitals: { type: Object, required: true },
    uptime: { type: Object, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
const ALL = '*'

function filter(changes) {
    const next = { ...props.filters, ...changes }
    const query = {}

    for (const [key, value] of Object.entries(next)) {
        if (value !== null && value !== '' && value !== ALL && !(key === 'period' && value === 'day')) {
            query[key] = value
        }
    }

    router.get(route('admin.monitoring.index'), query, { preserveScroll: true, preserveState: true })
}

const page = usePage()
const num = (value) => number(value, page.props.locale)
const percent = (value) => (value === null ? '—' : `${num(value)}%`)
const rating = (key) => (key ? l(`ratings.${key}`) : '—')
</script>

<template>
    <AdminLayout :title="l('title')">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-fg">{{ l('title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ l('privacy') }}</p>
        </div>

        <form dusk="monitoring-filters" class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent>
            <label class="text-sm text-fg">
                <span class="block mb-1 font-medium">{{ l('filters.period') }}</span>
                <select dusk="filter-period" class="rounded border border-line bg-surface px-2 py-1.5" :value="filters.period" @change="filter({ period: $event.target.value })">
                    <option v-for="period in ['day', 'week', 'month']" :key="period" :value="period">{{ l(`periods.${period}`) }}</option>
                </select>
            </label>
            <label v-if="options.sites.length > 1" class="text-sm text-fg">
                <span class="block mb-1 font-medium">{{ l('filters.site') }}</span>
                <select dusk="filter-site" class="rounded border border-line bg-surface px-2 py-1.5" :value="filters.site ?? ''" @change="filter({ site: $event.target.value })">
                    <option value="">{{ l('filters.all') }}</option>
                    <option v-for="site in options.sites" :key="site.value" :value="site.value">{{ site.label }}</option>
                </select>
            </label>
            <label v-if="options.locales.length > 1" class="text-sm text-fg">
                <span class="block mb-1 font-medium">{{ l('filters.locale') }}</span>
                <select dusk="filter-locale" class="rounded border border-line bg-surface px-2 py-1.5" :value="filters.locale" @change="filter({ locale: $event.target.value })">
                    <option :value="ALL">{{ l('filters.all') }}</option>
                    <option v-for="code in options.locales" :key="code" :value="code">{{ code }}</option>
                </select>
            </label>
            <label class="text-sm text-fg">
                <span class="block mb-1 font-medium">{{ l('filters.device') }}</span>
                <select dusk="filter-device" class="rounded border border-line bg-surface px-2 py-1.5" :value="filters.device" @change="filter({ device: $event.target.value })">
                    <option :value="ALL">{{ l('filters.all') }}</option>
                    <option v-for="device in ['mobile', 'tablet', 'desktop']" :key="device" :value="device">{{ l(`devices.${device}`) }}</option>
                </select>
            </label>
            <p v-if="filters.route !== ALL || filters.page !== ALL" class="text-sm text-fg">
                <span v-if="filters.route !== ALL" dusk="filter-route">{{ l('filters.route') }}: <code class="font-mono">{{ filters.route }}</code></span>
                <span v-if="filters.page !== ALL" dusk="filter-page" class="ml-3">{{ l('filters.page') }}: <code class="font-mono">{{ filters.page }}</code></span>
                <button type="button" dusk="filter-clear" class="ml-3 text-accent underline" @click="filter({ route: ALL, page: ALL })">{{ l('filters.clear') }}</button>
            </p>
            <p class="ml-auto text-sm text-muted tabular-nums">{{ range.from }} — {{ range.to }}</p>
        </form>

        <div class="space-y-8">
            <section dusk="monitoring-server" aria-labelledby="monitoring-server">
                <h2 id="monitoring-server" class="mb-3 text-lg font-semibold text-fg">{{ l('sections.server') }}</h2>

                <dl class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
                    <div class="bg-surface rounded-lg shadow px-4 py-3">
                        <dt class="text-xs text-muted">{{ l('summary.requests') }}</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums" dusk="summary-requests">{{ server.summary.requests }}</dd>
                    </div>
                    <div class="bg-surface rounded-lg shadow px-4 py-3">
                        <dt class="text-xs text-muted">{{ l('summary.ttfb') }}, p75</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ server.summary.ttfb.p75 }}</dd>
                        <dd class="text-xs text-muted tabular-nums">p50 {{ server.summary.ttfb.p50 }} · p95 {{ server.summary.ttfb.p95 }}</dd>
                    </div>
                    <div class="bg-surface rounded-lg shadow px-4 py-3">
                        <dt class="text-xs text-muted">{{ l('summary.errors') }}</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ percent(server.summary.errors) }}</dd>
                    </div>
                    <div class="bg-surface rounded-lg shadow px-4 py-3">
                        <dt class="text-xs text-muted">{{ l('summary.cache_ratio') }}</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ percent(server.summary.cache_ratio) }}</dd>
                    </div>
                    <div class="bg-surface rounded-lg shadow px-4 py-3">
                        <dt class="text-xs text-muted">{{ l('summary.queries') }}</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ server.summary.queries === null ? '—' : num(server.summary.queries) }}</dd>
                    </div>
                    <div class="bg-surface rounded-lg shadow px-4 py-3">
                        <dt class="text-xs text-muted">{{ l('summary.memory_p95') }}</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ server.summary.memory_p95 === null ? '—' : `${num(server.summary.memory_p95)} ${l('summary.mb')}` }}</dd>
                    </div>
                </dl>

                <div class="grid gap-6 xl:grid-cols-2">
                    <MonitoringChart v-for="chart in server.charts" :key="chart.key" :title="l(`charts.${chart.key}`)" :svg="chart.svg" :points="chart.points" :t="t" :dusk="`chart-${chart.key}`" />
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                    <AdminCard no-padding :title="l('sections.routes')">
                        <p v-if="!server.routes.length" class="p-4 text-sm text-muted">{{ l('no_data') }}</p>
                        <div v-else class="overflow-x-auto">
                            <table dusk="slow-routes" class="min-w-full text-sm">
                                <thead class="bg-surface-alt">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.route') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.count') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">p50</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">p75</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">p95</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="row in server.routes" :key="row.route">
                                        <td class="px-3 py-2">
                                            <button type="button" dusk="route-filter" class="font-mono text-accent underline text-left" @click="filter({ route: row.route })">{{ row.route }}</button>
                                            <span v-if="row.sample" class="block text-xs text-muted font-mono break-all">{{ row.sample }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums">{{ row.count }}</td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ row.p50 }}</td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap font-semibold">{{ row.p75 }}</td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ row.p95 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AdminCard>

                    <AdminCard no-padding :title="l('sections.queries')">
                        <p v-if="!server.queries.length" class="p-4 text-sm text-muted">{{ l('no_data') }}</p>
                        <div v-else class="overflow-x-auto">
                            <table dusk="slow-queries" class="min-w-full text-sm">
                                <thead class="bg-surface-alt">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.query') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.count') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">p75</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">p95</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="row in server.queries" :key="row.hash">
                                        <td class="px-3 py-2">
                                            <a :href="route('admin.monitoring.query', row.hash)" dusk="query-link" class="font-mono text-xs text-accent underline break-all">{{ row.sql }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums">{{ row.count }}</td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap font-semibold">{{ row.p75 }}</td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ row.p95 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AdminCard>
                </div>
            </section>

            <section dusk="monitoring-vitals" aria-labelledby="monitoring-vitals">
                <h2 id="monitoring-vitals" class="mb-3 text-lg font-semibold text-fg">{{ l('sections.vitals') }}</h2>

                <dl class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div v-for="metric in vitals.metrics" :key="metric.key" class="bg-surface rounded-lg shadow px-4 py-3" :data-vital="metric.key">
                        <dt class="text-xs text-muted">{{ l(`vitals.${metric.key}`) }} ({{ metric.key.toUpperCase() }}), p75</dt>
                        <dd class="mt-1 text-xl font-semibold text-fg tabular-nums">{{ metric.p75 }}</dd>
                        <dd class="text-sm font-medium text-fg" data-rating>{{ rating(metric.rating) }}</dd>
                        <dd class="mt-1 text-xs text-muted">{{ l('vitals.thresholds', { good: metric.thresholds[0], poor: metric.thresholds[1] }) }}</dd>
                        <dd class="text-xs text-muted tabular-nums">{{ l('columns.count') }}: {{ metric.count }}</dd>
                    </div>
                </dl>

                <div class="grid gap-6 xl:grid-cols-2">
                    <MonitoringChart v-for="metric in vitals.metrics" :key="metric.key" :title="l(`vitals.${metric.key}`)" :svg="metric.svg" :points="metric.points" :t="t" :dusk="`chart-${metric.key}`" />
                </div>

                <AdminCard no-padding v-if="vitals.pages.length" :title="l('sections.pages')" class="mt-6">
                    <div class="overflow-x-auto">
                        <table dusk="slow-pages" class="min-w-full text-sm">
                            <thead class="bg-surface-alt">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.page') }}</th>
                                    <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.rating') }}</th>
                                    <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.count') }}</th>
                                    <th class="px-3 py-2 text-right font-medium text-fg">p75</th>
                                    <th class="px-3 py-2 text-right font-medium text-fg">p95</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="row in vitals.pages" :key="row.page">
                                    <td class="px-3 py-2">
                                        <button type="button" dusk="page-filter" class="font-mono text-accent underline text-left break-all" @click="filter({ page: row.page })">{{ row.page }}</button>
                                        <a v-if="row.errors_url" :href="row.errors_url" class="block text-xs text-accent underline" dusk="page-errors">{{ l('links.errors') }}</a>
                                    </td>
                                    <td class="px-3 py-2 text-fg">{{ rating(row.rating) }}</td>
                                    <td class="px-3 py-2 text-right text-fg tabular-nums">{{ row.count }}</td>
                                    <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap font-semibold">{{ row.p75 }}</td>
                                    <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ row.p95 }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminCard>
            </section>

            <section dusk="monitoring-uptime" aria-labelledby="monitoring-uptime">
                <h2 id="monitoring-uptime" class="mb-3 text-lg font-semibold text-fg">{{ l('sections.uptime') }}</h2>

                <div class="grid gap-6 xl:grid-cols-2">
                    <AdminCard no-padding :title="l('modules.checks')">
                        <p v-if="!uptime.checks.length" class="p-4 text-sm text-muted">{{ l('no_data') }}</p>
                        <div v-else class="overflow-x-auto">
                            <table dusk="uptime-checks" class="min-w-full text-sm">
                                <thead class="bg-surface-alt">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.check') }}</th>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.status') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.uptime') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-fg">{{ l('columns.response') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="check in uptime.checks" :key="check.id" :data-status="check.status">
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-fg">{{ check.name }}</span>
                                            <span class="block text-xs text-muted font-mono break-all">{{ check.url }}</span>
                                            <span v-if="check.error && check.status !== 'up'" class="block text-xs text-fg">{{ check.error }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-fg whitespace-nowrap">
                                            <span aria-hidden="true">{{ { up: '●', failing: '◐', down: '○', unknown: '·' }[check.status] }}</span>
                                            {{ l(`status.${check.status}`) }}
                                            <span v-if="check.checked_at" class="block text-xs text-muted tabular-nums">{{ check.checked_at }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums">{{ percent(check.uptime) }}</td>
                                        <td class="px-3 py-2 text-right text-fg tabular-nums whitespace-nowrap">{{ check.response ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AdminCard>

                    <AdminCard no-padding :title="l('sections.incidents')">
                        <p v-if="!uptime.incidents.length" class="p-4 text-sm text-muted">{{ l('no_data') }}</p>
                        <div v-else class="overflow-x-auto">
                            <table dusk="incidents" class="min-w-full text-sm">
                                <thead class="bg-surface-alt">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('columns.check') }}</th>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('fields.started_at') }}</th>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ l('fields.duration') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="incident in uptime.incidents" :key="incident.id">
                                        <td class="px-3 py-2 text-fg">
                                            {{ incident.check }}
                                            <span v-if="incident.error" class="block text-xs text-muted">{{ incident.error }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-fg tabular-nums whitespace-nowrap">{{ incident.started_at }}</td>
                                        <td class="px-3 py-2 text-fg whitespace-nowrap">
                                            {{ incident.duration }}
                                            <span v-if="!incident.resolved_at" class="font-semibold">({{ l('ongoing') }})</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AdminCard>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
