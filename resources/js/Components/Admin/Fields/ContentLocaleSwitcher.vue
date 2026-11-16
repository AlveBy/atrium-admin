<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'

/**
 * Which language of the translatable fields the form shows. A language with
 * validation errors carries their count, so an error on a tab that is not
 * open is not missed.
 */
const props = defineProps({
    modelValue: { type: String, default: null },
    locales: { type: Array, required: true },
    fields: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const translatableNames = computed(() => props.fields.filter((field) => field.translatable).map((field) => field.name))

function errorCount(code) {
    return Object.keys(props.errors)
        .filter((key) => translatableNames.value.some((name) => key === `${name}.${code}`))
        .length
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 mb-6" role="group" :aria-label="$t('languages.switch')">
        <span class="text-sm text-muted mr-1">{{ $t('languages.switch') }}:</span>
        <button
            v-for="language in locales"
            :key="language.code"
            type="button"
            :data-content-locale="language.code"
            :aria-pressed="modelValue === language.code ? 'true' : 'false'"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border transition-colors"
            :class="modelValue === language.code
                ? 'bg-accent-hover border-accent-hover text-on-accent'
                : 'bg-surface border-field text-fg hover:bg-hover'"
            @click="emit('update:modelValue', language.code)"
        >
            {{ language.name }}
            <span
                v-if="errorCount(language.code) > 0"
                class="inline-flex items-center justify-center min-w-5 h-5 px-1 text-xs font-semibold rounded-full bg-danger-hover text-on-danger"
            >{{ errorCount(language.code) }}</span>
        </button>
    </div>
</template>
