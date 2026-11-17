<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * A bar chart that is read, not guessed: every bar above zero carries its
 * number, the axis names every `every`-th bar, and a hidden table repeats it
 * all for screen readers. One colour, the theme's accent, checked against the
 * card in every theme.
 *
 * points: [{ label: '14:00', value: 3 }, ...]
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    points: { type: Array, required: true },
    every: { type: Number, default: 1 },
    label: { type: String, default: '' },
    height: { type: Number, default: 96 },
})

const page = usePage()
const format = computed(() => new Intl.NumberFormat(String(page.props.locale || 'en').replace('_', '-'), { notation: 'compact' }))

// Room for the number above the tallest bar.
const VALUE_ROOM = 14

const max = computed(() => Math.max(1, ...props.points.map((point) => Number(point.value) || 0)))

function barHeight(value) {
    return value > 0 ? `${Math.max(2, (value / max.value) * (props.height - VALUE_ROOM))}px` : '0px'
}
</script>

<template>
    <figure class="w-full" :aria-label="label" dusk="bar-chart">
        <div class="flex items-end gap-px" :style="{ height: `${height}px` }" aria-hidden="true">
            <div
                v-for="(point, index) in points"
                :key="index"
                class="flex-1 min-w-0 h-full flex flex-col items-center justify-end"
                :title="`${point.label}: ${point.value}`"
                data-bar
            >
                <span v-if="point.value > 0" class="mb-0.5 text-[10px] leading-none text-fg tabular-nums" data-bar-value>{{ format.format(point.value) }}</span>
                <div class="w-full rounded-t-sm bg-accent" :style="{ height: barHeight(point.value) }" />
            </div>
        </div>
        <div class="h-px bg-field" aria-hidden="true" />
        <div class="mt-1 flex gap-px" aria-hidden="true">
            <span
                v-for="(point, index) in points"
                :key="index"
                class="flex-1 min-w-0 text-[10px] text-muted tabular-nums whitespace-nowrap"
            >{{ index % every === 0 ? point.label : '' }}</span>
        </div>
        <table class="sr-only">
            <caption v-if="label">{{ label }}</caption>
            <tbody>
                <tr v-for="(point, index) in points" :key="index">
                    <th scope="row">{{ point.label }}</th>
                    <td>{{ point.value }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
