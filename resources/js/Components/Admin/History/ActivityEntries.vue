<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Entries of the activity log, newest first: when, who and from where, what
 * happened to which record, and each changed field from what to what.
 */
import { Link, usePage } from '@inertiajs/vue3'
import ActivityChanges from './ActivityChanges.vue'

defineProps({
    entries: { type: Array, required: true },
    // The journal names the module and the record of each entry; one
    // record's history does not need to.
    withRecord: { type: Boolean, default: false },
})

const locale = () => (usePage().props.locale || 'en').replace('_', '-')

const when = (at) => new Date(at).toLocaleString(locale(), { dateStyle: 'medium', timeStyle: 'short' })
</script>

<template>
    <p v-if="!entries.length" data-history-empty class="py-8 text-center text-muted">{{ $t('history.empty') }}</p>

    <ol v-else class="divide-y divide-line" data-history-entries>
        <li v-for="entry in entries" :key="entry.id" class="py-4" :data-history-entry="entry.id" :data-event="entry.event">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
                <span class="font-semibold text-fg" data-history-what>{{ $t(`history.events.${entry.event}`) }}</span>
                <template v-if="withRecord">
                    <span class="text-muted">{{ entry.module }}:</span>
                    <Link v-if="entry.url" :href="entry.url" class="text-accent hover:underline" data-history-record>{{ entry.record }}</Link>
                    <span v-else class="text-fg" data-history-record>{{ entry.record }}</span>
                </template>
                <span class="text-muted">·</span>
                <span class="text-fg" data-history-who>{{ entry.admin || $t('history.system') }}</span>
                <span class="text-muted" data-history-source>{{ $t(`history.sources.${entry.source}`) }}</span>
                <span class="text-muted">·</span>
                <time :datetime="entry.at" class="text-muted">{{ when(entry.at) }}</time>
            </div>

            <ActivityChanges v-if="entry.changes.length" :changes="entry.changes" class="mt-3" />
        </li>
    </ol>
</template>
