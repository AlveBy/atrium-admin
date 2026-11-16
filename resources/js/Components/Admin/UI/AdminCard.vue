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
    <div class="bg-white rounded-lg shadow">
        <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-200">
            <slot name="header">
                <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-600">{{ subtitle }}</p>
            </slot>
        </div>
        <div :class="noPadding ? '' : 'p-6'">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
            <slot name="footer" />
        </div>
    </div>
</template>
