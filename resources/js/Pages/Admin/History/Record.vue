<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The history of one record, from its form's "History" button: its versions,
 * two of them compared and an older one put back, and what the activity log
 * has about it.
 */
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'
import AdminPagination from '../../../Components/Admin/Table/AdminPagination.vue'
import ActivityChanges from '../../../Components/Admin/History/ActivityChanges.vue'
import ActivityEntries from '../../../Components/Admin/History/ActivityEntries.vue'
import { t } from '../../../Support/i18n'

const props = defineProps({
    entries: { type: Object, required: true },
    title: { type: String, required: true },
    moduleLabel: { type: String, required: true },
    indexUrl: { type: String, required: true },
    editUrl: { type: String, required: true },
    // Newest first, the first is the record as it is now.
    versions: { type: Array, default: () => [] },
    // { from, to, changes } when two versions are compared.
    comparison: { type: Object, default: null },
})

const locale = () => (usePage().props.locale || 'en').replace('_', '-')

/** A version by its date, who saved it and from where. */
const named = (version, index) => {
    const parts = [version.at
        ? new Date(version.at).toLocaleString(locale(), { dateStyle: 'medium', timeStyle: 'short' })
        : t('history.versions.baseline')]

    if (version.at) {
        parts.push(version.admin || t('history.system'))
    }

    if (index === 0) {
        parts.push(t('history.versions.current'))
    }

    return parts.join(' · ')
}

const from = ref(props.comparison?.from ?? props.versions[1]?.id ?? '')
const to = ref(props.comparison?.to ?? props.versions[0]?.id ?? '')

const compare = () => {
    router.get(window.location.pathname, { from: from.value, to: to.value }, { preserveScroll: true, preserveState: true })
}

const restoring = ref(null)

const restore = () => {
    router.post(restoring.value.revertUrl, {}, { preserveScroll: true, onFinish: () => { restoring.value = null } })
}

const field = 'block w-full rounded-md border border-field bg-surface px-3 py-2 text-sm text-fg focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <Head :title="title" />
    <nav class="mb-6 text-sm" aria-label="Breadcrumb">
        <Link :href="indexUrl" class="text-muted hover:text-accent">{{ moduleLabel }}</Link>
    </nav>

    <div class="max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-fg">{{ title }}</h1>
            <AdminButton :href="editUrl" variant="secondary" data-action="back-to-record">{{ $t('history.back') }}</AdminButton>
        </div>

        <AdminCard v-if="versions.length" class="mb-6" data-history-versions>
            <h2 class="text-lg font-semibold text-fg">{{ $t('history.versions.title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $t('history.versions.hint') }}</p>

            <ol class="mt-4 divide-y divide-line">
                <li v-for="(version, index) in versions" :key="version.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm" :data-history-version="version.id">
                    <span class="text-fg">
                        {{ named(version, index) }}
                        <span v-if="version.source" class="text-muted">{{ $t(`history.sources.${version.source}`) }}</span>
                    </span>
                    <AdminButton v-if="version.revertUrl" variant="secondary" size="sm" data-action="restore-version" @click="restoring = version">
                        {{ $t('history.versions.restore') }}
                    </AdminButton>
                </li>
            </ol>

            <form v-if="versions.length > 1" class="mt-4 grid gap-4 sm:grid-cols-3 sm:items-end" @submit.prevent="compare" data-history-compare>
                <div>
                    <label for="history-compare-from" class="mb-1 block text-sm font-medium text-fg">{{ $t('history.versions.from') }}</label>
                    <select id="history-compare-from" v-model="from" dusk="history-compare-from" :class="field">
                        <option v-for="(version, index) in versions" :key="version.id" :value="version.id">{{ named(version, index) }}</option>
                    </select>
                </div>
                <div>
                    <label for="history-compare-to" class="mb-1 block text-sm font-medium text-fg">{{ $t('history.versions.to') }}</label>
                    <select id="history-compare-to" v-model="to" dusk="history-compare-to" :class="field">
                        <option v-for="(version, index) in versions" :key="version.id" :value="version.id">{{ named(version, index) }}</option>
                    </select>
                </div>
                <div>
                    <AdminButton type="submit" dusk="history-compare">{{ $t('history.versions.compare') }}</AdminButton>
                </div>
            </form>

            <div v-if="comparison" class="mt-4" data-history-comparison>
                <ActivityChanges v-if="comparison.changes.length" :changes="comparison.changes" />
                <p v-else class="text-sm text-muted" data-history-same>{{ $t('history.versions.same') }}</p>
            </div>
        </AdminCard>

        <AdminCard>
            <ActivityEntries :entries="entries.data" />
        </AdminCard>

        <AdminPagination v-if="entries.last_page > 1" :paginator="entries" class="mt-4 rounded-lg" />
    </div>

    <AdminModal :show="restoring !== null" :title="$t('history.versions.confirm_title')" @close="restoring = null">
        <p class="text-fg">{{ $t('history.versions.confirm') }}</p>

        <template #footer>
            <AdminButton data-action="restore-cancel" variant="secondary" @click="restoring = null">{{ $t('common.cancel') }}</AdminButton>
            <AdminButton data-action="restore-confirm" @click="restore">{{ $t('history.versions.restore') }}</AdminButton>
        </template>
    </AdminModal>
</template>
