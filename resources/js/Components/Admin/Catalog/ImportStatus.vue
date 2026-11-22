<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The status of an import as a word on a tint: the word says it, the tint
 * only helps (every pair is over 4.5:1 in both themes).
 */
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    strings: { type: Object, required: true },
})

const TINTS = {
    done: 'bg-success-soft text-success-soft-fg',
    checked: 'bg-success-soft text-success-soft-fg',
    failed: 'bg-danger-soft text-danger-soft-fg',
    checking: 'bg-warning-soft text-warning-soft-fg',
    importing: 'bg-warning-soft text-warning-soft-fg',
}

const tint = computed(() => TINTS[props.status] ?? 'bg-off text-off-fg')
</script>

<template>
    <span :class="['inline-block px-2 py-0.5 rounded text-xs font-medium', tint]" dusk="import-status">{{ strings.statuses[status] ?? status }}</span>
</template>
