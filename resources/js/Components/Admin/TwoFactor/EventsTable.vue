<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The sign-in events of alveby/atrium-2fa as a table: when, who (only for
 * the whole log), what, how and from where. Every cell comes already worded
 * in the panel's language.
 */
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
    events: { type: Array, required: true },
    withWho: Boolean,
})

const l = (key, params = {}) => label(props.t, key, params)
</script>

<template>
    <p v-if="!events.length" class="text-sm text-muted">{{ l('log.empty') }}</p>
    <div v-else class="overflow-x-auto">
        <table dusk="a2f-events" class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-alt">
                <tr>
                    <th scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('log.when') }}</th>
                    <th v-if="withWho" scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('log.who') }}</th>
                    <th scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('log.what') }}</th>
                    <th scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('log.how') }}</th>
                    <th scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('log.where') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <tr v-for="event in events" :key="event.id">
                    <td class="px-3 py-2 text-fg"><time :datetime="event.when_iso">{{ event.when }}</time></td>
                    <td v-if="withWho" class="px-3 py-2 text-fg">{{ event.who }}</td>
                    <td class="px-3 py-2 text-fg">{{ event.what }}<span v-if="event.by" class="text-muted"> ({{ event.by }})</span></td>
                    <td class="px-3 py-2 text-fg">{{ event.how }}</td>
                    <td class="px-3 py-2 text-muted">{{ event.ip }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
