<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * One error of alveby/atrium-errors: what it is, how often it happens, the
 * trace of one event with the code around each line, the request, what
 * happened before, and every occurrence. Resolve, resolve in the next
 * release, ignore or reopen it here.
 */
import { computed, ref } from 'vue'
import { Link, router, usePage, Head } from '@inertiajs/vue3'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import AdminPagination from '@atrium/Components/Admin/Table/AdminPagination.vue'
import BarChart from '@atrium/Components/Admin/UI/BarChart.vue'

const props = defineProps({
    issue: { type: Object, required: true },
    event: { type: Object, default: null },
    occurrences: { type: Object, required: true },
    top: { type: Object, required: true },
    chart: { type: Object, required: true },
    currentRelease: { type: String, default: '' },
    strings: { type: Object, required: true },
})

const page = usePage()
const locale = computed(() => String(page.props.locale || 'en').replace('_', '-'))
const s = computed(() => props.strings.issue)

function date(iso) {
    return iso ? new Date(iso).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' }) : s.value.none
}

function replace(line, params) {
    return Object.entries(params).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value ?? '')), line)
}

// --- status ------------------------------------------------------------------

const saving = ref(false)

function setStatus(status, nextRelease = false) {
    saving.value = true
    router.post(route('admin.errors.status', props.issue.id), { status, next_release: nextRelease }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
    })
}

// --- chart -------------------------------------------------------------------

const period = ref('hourly')

// --- trace -------------------------------------------------------------------

const allFrames = ref(false)

const exceptions = computed(() => (props.event?.exceptions ?? []).map((exception) => {
    const frames = exception.frames ?? []
    const app = frames.filter((frame) => frame.in_app)

    return { ...exception, shown: allFrames.value || app.length === 0 ? frames : app, hidden: frames.length - app.length }
}))

function where(frame) {
    return [frame.file, frame.line].filter(Boolean).join(':')
}

const request = computed(() => {
    const r = props.event?.request ?? {}

    return [
        ['method', r.method], ['url', r.url], ['route', r.route],
        ['site', props.event?.site], ['locale', r.locale], ['user', r.user_id],
    ].filter(([, value]) => value !== null && value !== undefined && value !== '')
})

const environment = computed(() => {
    const e = props.event ?? {}

    return [
        ['release', e.release], ['php', e.runtime?.php], ['laravel', e.runtime?.laravel],
    ].filter(([, value]) => value)
})

const context = computed(() => Object.entries(props.event?.context ?? {}))

function show(value) {
    return typeof value === 'string' ? value : JSON.stringify(value)
}
</script>

<template>
    <Head :title="issue.short_class" />
    <Link :href="route('admin.errors.index')" class="text-sm text-accent hover:text-accent-hover hover:underline" dusk="error-back">← {{ s.back }}</Link>

    <div class="mt-3 mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-fg break-all">{{ issue.short_class }}</h1>
            <p class="mt-1 text-xs text-muted font-mono break-all">{{ issue.class }}</p>
            <p class="mt-2 text-fg break-words whitespace-pre-line" dusk="error-message">{{ issue.message }}</p>
            <p v-if="issue.culprit" class="mt-1 text-sm text-muted font-mono break-all">{{ issue.culprit }}</p>
            <p class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="px-2 py-0.5 rounded bg-off text-off-fg" dusk="error-status">{{ strings.statuses[issue.status] }}</span>
                <span class="px-2 py-0.5 rounded bg-off text-off-fg">{{ strings.levels[issue.level] ?? issue.level }}</span>
                <span class="px-2 py-0.5 rounded bg-off text-off-fg">{{ strings.kinds[issue.kind] ?? issue.kind }}</span>
                <span v-if="issue.resolved_in" class="px-2 py-0.5 rounded bg-success-soft text-success-soft-fg">{{ replace(s.resolved_in, { release: issue.resolved_in }) }}</span>
                <span v-if="issue.regressed_at" class="px-2 py-0.5 rounded bg-warning-soft text-warning-soft-fg">{{ s.regressed }}: {{ date(issue.regressed_at) }}</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
            <template v-if="issue.status === 'new'">
                <AdminButton :disabled="saving" dusk="error-resolve" @click="setStatus('resolved')">{{ s.resolve }}</AdminButton>
                <AdminButton variant="secondary" :disabled="saving" :title="replace(s.resolve_next_hint, { release: currentRelease })" dusk="error-resolve-next" @click="setStatus('resolved', true)">{{ s.resolve_next }}</AdminButton>
                <AdminButton variant="secondary" :disabled="saving" dusk="error-ignore" @click="setStatus('ignored')">{{ s.ignore }}</AdminButton>
            </template>
            <AdminButton v-else variant="secondary" :disabled="saving" dusk="error-reopen" @click="setStatus('new')">{{ s.reopen }}</AdminButton>
        </div>
    </div>

    <p v-if="issue.status === 'new'" class="-mt-4 mb-6 text-xs text-muted">{{ replace(s.resolve_next_hint, { release: currentRelease }) }}</p>

    <dl class="mb-6 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <div v-for="[label, value] in [
            [strings.list.events, issue.times_seen],
            [strings.list.users, issue.users_seen],
            [strings.list.first_seen, date(issue.first_seen_at)],
            [strings.list.last_seen, date(issue.last_seen_at)],
            [s.first_release, issue.first_release || s.none],
            [s.last_release, issue.last_release || s.none],
        ]" :key="label" class="bg-surface rounded-lg shadow px-4 py-3 flex flex-col-reverse">
            <dt class="text-xs text-muted">{{ label }}</dt>
            <dd class="text-lg font-semibold text-fg tabular-nums break-words">{{ value }}</dd>
        </div>
    </dl>

    <div class="grid gap-6 lg:grid-cols-3 mb-6">
        <AdminCard class="lg:col-span-2">
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-medium text-fg">{{ s.frequency }}</h2>
                    <div class="flex gap-1" role="group">
                        <button
                            v-for="[key, label] in [['hourly', s.last_24h], ['daily', s.last_30d]]"
                            :key="key"
                            type="button"
                            class="px-2.5 py-1 rounded text-sm border"
                            :class="period === key ? 'bg-accent text-on-accent border-accent' : 'bg-surface text-fg border-field hover:bg-hover'"
                            :aria-pressed="period === key"
                            :dusk="`error-chart-${key}`"
                            @click="period = key"
                        >{{ label }}</button>
                    </div>
                </div>
            </template>
            <BarChart :points="chart[period]" :every="period === 'hourly' ? 3 : 5" :label="`${s.frequency}, ${period === 'hourly' ? s.last_24h : s.last_30d}`" />
        </AdminCard>

        <AdminCard>
            <h3 class="text-sm font-semibold text-fg">{{ s.top_paths }}</h3>
            <ul class="mt-2 mb-4 text-sm" dusk="error-top-paths">
                <li v-for="row in top.paths" :key="row.value" class="flex justify-between gap-3 py-0.5">
                    <span class="font-mono text-fg break-all">{{ row.value }}</span>
                    <span class="tabular-nums text-fg">{{ row.count }}</span>
                </li>
                <li v-if="!top.paths.length" class="text-muted">{{ s.none }}</li>
            </ul>
            <h3 class="text-sm font-semibold text-fg">{{ s.top_releases }}</h3>
            <ul class="mt-2 text-sm">
                <li v-for="row in top.releases" :key="row.value" class="flex justify-between gap-3 py-0.5">
                    <span class="font-mono text-fg break-all">{{ row.value }}</span>
                    <span class="tabular-nums text-fg">{{ row.count }}</span>
                </li>
                <li v-if="!top.releases.length" class="text-muted">{{ s.none }}</li>
            </ul>
        </AdminCard>
    </div>

    <p v-if="!event" class="mb-6 bg-surface rounded-lg shadow px-6 py-6 text-sm text-muted" dusk="error-no-event">{{ s.no_event }}</p>

    <template v-else>
        <AdminCard class="mb-6">
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-medium text-fg">{{ s.trace }}</h2>
                    <span class="text-xs text-muted">{{ s.shown }}: {{ date(event.created_at) }}</span>
                    <button type="button" class="text-sm text-accent hover:text-accent-hover hover:underline" dusk="error-all-frames" @click="allFrames = !allFrames">
                        {{ allFrames ? s.app_frames : s.all_frames }}
                    </button>
                </div>
            </template>

            <section v-for="(exception, index) in exceptions" :key="index" class="mb-6 last:mb-0" dusk="error-exception">
                <p v-if="index > 0" class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ s.caused_by }}</p>
                <p class="font-semibold text-fg break-all">{{ exception.class }}</p>
                <p class="mb-3 text-sm text-fg break-words whitespace-pre-line">{{ exception.message }}</p>

                <ol class="space-y-2">
                    <li v-for="(frame, f) in exception.shown" :key="f" class="rounded border border-line" dusk="error-frame">
                        <p class="px-3 py-1.5 text-sm bg-surface-alt">
                            <span class="font-mono text-fg break-all">{{ frame.function || s.none }}</span>
                            <span class="ml-2 font-mono text-xs text-muted break-all">{{ where(frame) }}</span>
                        </p>
                        <pre v-if="frame.context" class="overflow-x-auto text-xs leading-5 font-mono py-1" dusk="error-code"><code><span
                            v-for="(code, c) in frame.context.pre" :key="`pre${c}`" class="block px-3 text-muted"
                        ><span class="inline-block w-12 select-none text-right pr-3">{{ frame.line - frame.context.pre.length + c }}</span>{{ code }}</span><span
                            class="block px-3 bg-accent-soft text-accent-soft-fg font-semibold"
                        ><span class="inline-block w-12 select-none text-right pr-3">{{ frame.line }}</span>{{ frame.context.line }}</span><span
                            v-for="(code, c) in frame.context.post" :key="`post${c}`" class="block px-3 text-muted"
                        ><span class="inline-block w-12 select-none text-right pr-3">{{ frame.line + 1 + c }}</span>{{ code }}</span></code></pre>
                    </li>
                </ol>
            </section>
        </AdminCard>

        <div class="grid gap-6 lg:grid-cols-2 mb-6">
            <AdminCard :title="s.request">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm" dusk="error-request">
                    <template v-for="[key, value] in request" :key="key">
                        <dt class="text-muted">{{ s[key] }}</dt>
                        <dd class="text-fg font-mono break-all">{{ value }}</dd>
                    </template>
                    <template v-for="[key, value] in environment" :key="key">
                        <dt class="text-muted">{{ s[key] }}</dt>
                        <dd class="text-fg font-mono break-all">{{ value }}</dd>
                    </template>
                    <template v-for="[key, value] in context" :key="`context-${key}`">
                        <dt class="text-muted font-mono">{{ key }}</dt>
                        <dd class="text-fg font-mono break-all">{{ show(value) }}</dd>
                    </template>
                </dl>
            </AdminCard>

            <AdminCard :title="s.breadcrumbs">
                <ol class="text-sm space-y-1" dusk="error-breadcrumbs">
                    <li v-for="(crumb, index) in event.breadcrumbs" :key="index" class="grid grid-cols-[auto_auto_1fr] gap-3">
                        <span class="font-mono text-muted tabular-nums">{{ crumb.time }}</span>
                        <span class="font-mono text-muted">{{ crumb.category }}</span>
                        <span class="text-fg break-words">{{ crumb.message }}</span>
                    </li>
                    <li v-if="!event.breadcrumbs?.length" class="text-muted">{{ s.none }}</li>
                </ol>
            </AdminCard>
        </div>
    </template>

    <AdminCard :title="s.occurrences" no-padding>
        <table class="min-w-full divide-y divide-line text-sm" dusk="error-occurrences">
            <thead class="bg-surface-alt">
                <tr>
                    <th v-for="key in ['time', 'site', 'method', 'url', 'release', 'user']" :key="key" scope="col" class="px-4 py-2 text-left font-medium text-fg">{{ s[key] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <tr v-for="row in occurrences.data" :key="row.id" :class="row.id === event?.id ? 'bg-accent-soft text-accent-soft-fg' : 'hover:bg-hover'">
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span v-if="row.id === event?.id" class="font-semibold">{{ date(row.created_at) }} · {{ s.shown }}</span>
                        <Link v-else :href="route('admin.errors.show', { issue: issue.id, event: row.id })" preserve-scroll class="text-accent hover:text-accent-hover hover:underline" dusk="error-occurrence">{{ date(row.created_at) }}</Link>
                    </td>
                    <td class="px-4 py-2">{{ row.site ?? s.none }}</td>
                    <td class="px-4 py-2 font-mono">{{ row.method ?? s.none }}</td>
                    <td class="px-4 py-2 font-mono break-all">{{ row.path ?? s.none }}</td>
                    <td class="px-4 py-2 font-mono">{{ row.release }}</td>
                    <td class="px-4 py-2 tabular-nums">{{ row.user_id ?? s.none }}</td>
                </tr>
            </tbody>
        </table>
        <AdminPagination v-if="occurrences.last_page > 1" :paginator="occurrences" />
    </AdminCard>
</template>
