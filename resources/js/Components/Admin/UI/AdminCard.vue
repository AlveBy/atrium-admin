<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

defineProps({
    title: String,
    subtitle: String,
    noPadding: Boolean,
})
</script>

<template>
    <div class="bg-surface rounded-lg shadow">
        <div v-if="title || $slots.header" class="px-6 py-4 border-b border-line">
            <slot name="header">
                <h3 class="text-lg font-medium text-fg">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-muted">{{ subtitle }}</p>
            </slot>
        </div>
        <div :class="noPadding ? '' : 'p-6'">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-line bg-surface-alt rounded-b-lg">
            <slot name="footer" />
        </div>
    </div>
</template>
