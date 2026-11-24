<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Fields from what to what: of one entry of the activity log, or between two
 * versions of a record. The old and the new value differ by their column and
 * their words ("Was", "Now"), and the old one is struck through, not by
 * colour alone.
 */
import { t } from '../../../Support/i18n'

defineProps({
    changes: { type: Array, required: true },
})

// A language with no text yet is no value: {"en": "Hi", "de": null} shows "en: Hi".
const filled = (value) => Object.entries(value).filter(([, item]) => !isEmpty(item))

const isEmpty = (value) => value === null || value === '' || (Array.isArray(value) && value.length === 0)
    || (typeof value === 'object' && !Array.isArray(value) && filled(value).length === 0)

/** A value as text: yes or no, a list, the text of each language. */
const shown = (value) => {
    if (typeof value === 'boolean') {
        return value ? t('history.yes') : t('history.no')
    }

    if (Array.isArray(value)) {
        return value.map((item) => shown(item)).join(', ')
    }

    if (value !== null && typeof value === 'object') {
        return filled(value).map(([key, item]) => `${key}: ${shown(item)}`).join('\n')
    }

    return String(value)
}
</script>

<template>
    <table class="w-full table-fixed text-sm" data-history-changes>
        <thead>
            <tr class="text-left text-muted">
                <th scope="col" class="w-1/4 py-1 pr-3 font-medium">{{ $t('history.field') }}</th>
                <th scope="col" class="py-1 pr-3 font-medium">{{ $t('history.old') }}</th>
                <th scope="col" class="py-1 font-medium">{{ $t('history.new') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="change in changes" :key="change.field" class="align-top" :data-history-field="change.field">
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
</template>
