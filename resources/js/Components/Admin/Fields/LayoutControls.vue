<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { t } from '@atrium/Support/i18n'

/*
 * Move up, move down and delete: the buttons of a section, a column and a
 * block of the layout editor. Each says what it acts on, for screen readers
 * and as a tooltip, and names its node for the editor to find it again.
 */
defineProps({
    target: { type: String, required: true },
    prefix: { type: String, required: true },
    position: { type: String, required: true },
    node: { type: String, required: true },
    first: { type: Boolean, default: false },
    last: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
})

defineEmits(['up', 'down', 'remove'])

const button = 'p-1 rounded text-muted hover:text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus disabled:opacity-30 disabled:cursor-not-allowed'
</script>

<template>
    <div class="flex items-center gap-0.5 shrink-0">
        <button type="button" :class="button" :disabled="disabled || first" :title="`${t('fields.layout.move_up')}: ${target}`" :aria-label="`${t('fields.layout.move_up')}: ${target}`" :data-control="`up:${node}`" :dusk="`${prefix}-up-${position}`" @click="$emit('up')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
        </button>
        <button type="button" :class="button" :disabled="disabled || last" :title="`${t('fields.layout.move_down')}: ${target}`" :aria-label="`${t('fields.layout.move_down')}: ${target}`" :data-control="`down:${node}`" :dusk="`${prefix}-down-${position}`" @click="$emit('down')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </button>
        <button type="button" class="p-1 rounded text-danger hover:bg-danger-soft focus:outline-none focus:ring-2 focus:ring-focus disabled:opacity-30" :disabled="disabled" :title="`${t('common.delete')}: ${target}`" :aria-label="`${t('common.delete')}: ${target}`" :data-control="`remove:${node}`" :dusk="`${prefix}-remove-${position}`" @click="$emit('remove')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</template>
