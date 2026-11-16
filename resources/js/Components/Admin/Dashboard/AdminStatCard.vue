<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'

const props = defineProps({
    stat: {
        type: Object,
        required: true
    }
})

const colorClasses = computed(() => {
    const colors = {
        blue: 'bg-accent-soft text-accent',
        green: 'bg-success-soft text-success',
        yellow: 'bg-warning-soft text-warning-soft-fg',
        red: 'bg-danger-soft text-danger',
        purple: 'bg-accent-soft text-accent',
        gray: 'bg-off text-muted',
    }
    return colors[props.stat.color] || colors.blue
})

const iconPath = computed(() => {
    const icons = {
        users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        document: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        folder: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        default: 'M4 6h16M4 12h16M4 18h16',
    }
    return icons[props.stat.icon] || icons.default
})
</script>

<template>
    <div class="bg-surface rounded-lg shadow p-6">
        <div class="flex items-center">
            <div
                class="w-12 h-12 rounded-lg flex items-center justify-center"
                :class="colorClasses"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath" />
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-muted">{{ stat.label }}</p>
                <p class="text-2xl font-bold text-fg">{{ stat.value }}</p>
            </div>
        </div>
    </div>
</template>
