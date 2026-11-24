<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Entries of the activity log, newest first: when, who and from where, what
 * happened to which record, and each changed field from what to what. The
 * old and the new value differ by their column and their words ("Was",
 * "Now"), and the old one is struck through, not by colour alone.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { t } from '../../../Support/i18n'

defineProps({
    entries: { type: Array, required: true },
    // The journal names the module and the record of each entry; one
    // record's history does not need to.
    withRecord: { type: Boolean, default: false },
})

const locale = () => (usePage().props.locale || 'en').replace('_', '-')

const when = (at) => new Date(at).toLocaleString(locale(), { dateStyle: 'medium', timeStyle: 'short' })

const isEmpty = (value) => value === null || value === '' || (Array.isArray(value) && value.length === 0)

/** A value as text: yes or no, a list, the text of each language. */
const shown = (value) => {
    if (typeof value === 'boolean') {
        return value ? t('history.yes') : t('history.no')
    }

    if (Array.isArray(value)) {
        return value.map((item) => shown(item)).join(', ')
    }

    if (value !== null && typeof value === 'object') {
        return Object.entries(value).map(([key, item]) => `${key}: ${shown(item)}`).join('\n')
    }

    return String(value)
}
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

            <table v-if="entry.changes.length" class="mt-3 w-full table-fixed text-sm" data-history-changes>
                <thead>
                    <tr class="text-left text-muted">
                        <th scope="col" class="w-1/4 py-1 pr-3 font-medium">{{ $t('history.field') }}</th>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ $t('history.old') }}</th>
                        <th scope="col" class="py-1 font-medium">{{ $t('history.new') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="change in entry.changes" :key="change.field" class="align-top" :data-history-field="change.field">
                        <th scope="row" class="py-1 pr-3 text-left font-normal text-fg">{{ change.label }}</th>
                        <td v-if="change.secret" colspan="2" class="py-1 italic text-muted" data-history-secret>{{ $t('history.secret') }}</td>
                        <template v-else>
                            <td class="py-1 pr-3" data-history-old>
                                <span v-if="isEmpty(change.old)" class="italic text-muted">{{ $t('history.empty_value') }}</span>
                                <del v-else class="block max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-danger-soft px-2 py-0.5 text-danger-soft-fg">{{ shown(change.old) }}</del>
                            </td>
                            <td class="py-1" data-history-new>
                                <span v-if="isEmpty(change.new)" class="italic text-muted">{{ $t('history.empty_value') }}</span>
                                <ins v-else class="block max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-success-soft px-2 py-0.5 text-success-soft-fg no-underline">{{ shown(change.new) }}</ins>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </li>
    </ol>
</template>
