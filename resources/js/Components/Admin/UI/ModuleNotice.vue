<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * What a module's page tells after an action: a confirmation and the errors
 * of the request, drawn like the panel's own flash messages (an icon and a
 * word read out, not colour alone). `prefix` names the dusk hooks:
 * `{prefix}-status` and `{prefix}-error`.
 */
defineProps({
    prefix: { type: String, required: true },
    status: { type: String, default: null },
    errors: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="status" role="status" :dusk="`${prefix}-status`" class="mb-4 flex items-start gap-3 p-4 bg-success-soft border border-success text-success-soft-fg rounded">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <span><span class="sr-only">{{ $t('actions.success') }}: </span>{{ status }}</span>
    </div>
    <div v-for="(message, index) in errors" :key="index" role="alert" :dusk="`${prefix}-error`" class="mb-4 flex items-start gap-3 p-4 bg-danger-soft border border-danger text-danger-soft-fg rounded">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        </svg>
        <span><span class="sr-only">{{ $t('actions.error') }}: </span>{{ message }}</span>
    </div>
</template>
