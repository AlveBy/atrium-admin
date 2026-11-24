<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Errors" screen of alveby/atrium-errors: one line per bug, searched
 * and filtered by status, site, level, source, release and address. The
 * module's strings come in the `strings` prop.
 */
import { computed, reactive } from 'vue'
import { Link, router, usePage, Head } from '@inertiajs/vue3'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminPagination from '@atrium/Components/Admin/Table/AdminPagination.vue'

const props = defineProps({
    issues: { type: Object, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    strings: { type: Object, required: true },
})

const page = usePage()
const locale = computed(() => String(page.props.locale || 'en').replace('_', '-'))

const form = reactive({ ...props.filters, site: props.filters.site ?? '' })

const STATUSES = computed(() => [...props.options.statuses, 'all'])

function apply() {
    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null))
    router.get(route('admin.errors.index'), query, { preserveState: true, preserveScroll: true })
}

function setStatus(status) {
    form.status = status
    apply()
}

function reset() {
    router.get(route('admin.errors.index'))
}

function date(iso) {
    return iso ? new Date(iso).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' }) : props.strings.issue.none
}

const field = 'block w-full rounded-md border border-field bg-surface text-fg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <Head :title="strings.title" />
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-fg">{{ strings.title }}</h1>
        <p class="mt-1 text-sm text-muted">{{ strings.subtitle }}</p>
    </div>

    <nav class="mb-4 flex flex-wrap gap-2" :aria-label="strings.list.status">
        <button
            v-for="status in STATUSES"
            :key="status"
            type="button"
            class="px-3 py-1.5 rounded-md text-sm font-medium border"
            :class="form.status === status ? 'bg-accent text-on-accent border-accent' : 'bg-surface text-fg border-field hover:bg-hover'"
            :aria-pressed="form.status === status"
            :dusk="`errors-status-${status}`"
            @click="setStatus(status)"
        >{{ strings.statuses[status] }}</button>
    </nav>

    <form class="bg-surface rounded-lg shadow p-4 mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" dusk="errors-filters" @submit.prevent="apply">
        <label class="sm:col-span-2 text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.search }}</span>
            <input v-model="form.q" type="search" :placeholder="strings.list.search" :class="field" dusk="errors-search">
        </label>
        <label class="text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.site }}</span>
            <select v-model="form.site" :class="field" dusk="errors-site">
                <option value="">{{ strings.list.all_sites }}</option>
                <option v-for="site in options.sites" :key="site.id" :value="site.id">{{ site.name }}</option>
            </select>
        </label>
        <label class="text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.release }}</span>
            <select v-model="form.release" :class="field" dusk="errors-release">
                <option value="">{{ strings.list.any_release }}</option>
                <option v-for="release in options.releases" :key="release" :value="release">{{ release }}</option>
            </select>
        </label>
        <label class="text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.level }}</span>
            <select v-model="form.level" :class="field" dusk="errors-level">
                <option value="">{{ strings.list.any_level }}</option>
                <option v-for="level in options.levels" :key="level" :value="level">{{ strings.levels[level] }}</option>
            </select>
        </label>
        <label class="text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.kind }}</span>
            <select v-model="form.kind" :class="field" dusk="errors-kind">
                <option value="">{{ strings.list.any_kind }}</option>
                <option v-for="kind in options.kinds" :key="kind" :value="kind">{{ strings.kinds[kind] }}</option>
            </select>
        </label>
        <label class="text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.path }}</span>
            <input v-model="form.path" type="text" :placeholder="strings.list.path_placeholder" :class="field" dusk="errors-path">
        </label>
        <label class="text-sm text-fg">
            <span class="block mb-1 text-muted">{{ strings.list.sort }}</span>
            <select v-model="form.sort" :class="field" dusk="errors-sort">
                <option v-for="(name, key) in strings.list.sorts" :key="key" :value="key">{{ name }}</option>
            </select>
        </label>
        <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
            <AdminButton type="submit" dusk="errors-apply">{{ strings.list.filter }}</AdminButton>
            <AdminButton type="button" variant="secondary" dusk="errors-reset" @click="reset">{{ strings.list.reset }}</AdminButton>
        </div>
    </form>

    <div class="bg-surface rounded-lg shadow overflow-hidden">
        <p v-if="!issues.data.length" class="px-6 py-10 text-center text-sm text-muted" dusk="errors-empty">{{ strings.list.empty }}</p>

        <table v-else class="min-w-full divide-y divide-line text-sm" dusk="errors-table">
            <thead class="bg-surface-alt">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ strings.list.issue }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ strings.list.level }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium text-fg">{{ strings.list.events }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium text-fg">{{ strings.list.users }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ strings.list.last_seen }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <tr v-for="issue in issues.data" :key="issue.id" class="hover:bg-hover" dusk="errors-row">
                    <td class="px-4 py-3 max-w-xl">
                        <Link :href="issue.url" class="font-semibold text-accent hover:text-accent-hover hover:underline" dusk="errors-open">{{ issue.short_class }}</Link>
                        <span v-if="issue.status !== 'new'" class="ml-2 inline-block px-1.5 py-0.5 rounded text-xs bg-off text-off-fg">{{ strings.statuses[issue.status] }}</span>
                        <p class="mt-0.5 text-fg break-words">{{ issue.title }}</p>
                        <p v-if="issue.culprit" class="mt-0.5 text-xs text-muted font-mono break-all">{{ issue.culprit }}</p>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-fg">
                        {{ strings.levels[issue.level] ?? issue.level }}
                        <span class="block text-xs text-muted">{{ strings.kinds[issue.kind] ?? issue.kind }}</span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-fg">{{ issue.times_seen }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-fg">{{ issue.users_seen }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-fg">
                        {{ date(issue.last_seen_at) }}
                        <span v-if="issue.last_release" class="block text-xs text-muted">{{ issue.last_release }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <AdminPagination v-if="issues.last_page > 1" :paginator="issues" />
    </div>
</template>
