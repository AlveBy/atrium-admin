<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * One p50 / p75 / p95 chart of the monitoring module: the SVG drawn on the
 * server, the legend in words and the same numbers as a table.
 *
 * Colours (contrast against the panel's surface, WCAG):
 *   light themes, white surface: p50 #0B5FA5 6.57:1, p75 #B34700 5.50:1, p95 #374151 10.31:1
 *   (on the lightest page colour, #f7f3ec of "sand": 5.94, 4.97, 9.32)
 *   night, surface #161d27: p50 #6CB4F0 7.61:1, p75 #F5A05B 8.12:1, p95 #E5E7EB 13.69:1
 * The colour only helps: each line also has its own marker (circle, square,
 * triangle), stroke (solid, dashed, dotted) and its name at the end.
 */
import { label } from './labels'

const props = defineProps({
    title: { type: String, required: true },
    svg: { type: String, required: true },
    points: { type: Array, default: () => [] },
    t: { type: Object, required: true },
})

const l = (key) => label(props.t, key)
</script>

<template>
    <figure class="bg-surface rounded-lg shadow px-5 py-4">
        <figcaption class="text-sm font-semibold text-fg">{{ title }}</figcaption>
        <p class="mt-1 text-xs text-muted">{{ l('charts.legend') }}</p>
        <!-- Drawn and escaped by SvgChart on the server. -->
        <div class="mt-3" v-html="svg" />
        <details class="mt-2 text-sm">
            <summary class="cursor-pointer text-accent">{{ l('charts.table') }}</summary>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-2 py-1 text-left font-medium text-fg">{{ l('columns.period') }}</th>
                            <th class="px-2 py-1 text-right font-medium text-fg">{{ l('columns.count') }}</th>
                            <th class="px-2 py-1 text-right font-medium text-fg">p50</th>
                            <th class="px-2 py-1 text-right font-medium text-fg">p75</th>
                            <th class="px-2 py-1 text-right font-medium text-fg">p95</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="point in points" :key="point.label">
                            <td class="px-2 py-1 text-fg tabular-nums">{{ point.label }}</td>
                            <td class="px-2 py-1 text-right text-fg tabular-nums">{{ point.count }}</td>
                            <td class="px-2 py-1 text-right text-fg tabular-nums">{{ point.p50 }}</td>
                            <td class="px-2 py-1 text-right text-fg tabular-nums">{{ point.p75 }}</td>
                            <td class="px-2 py-1 text-right text-fg tabular-nums">{{ point.p95 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </figure>
</template>

<style>
.atrium-monitoring-chart {
    --monitoring-p50: #0b5fa5;
    --monitoring-p75: #b34700;
    --monitoring-p95: #374151;
    display: block;
    height: auto;
    overflow: visible;
}

[data-theme='night'] .atrium-monitoring-chart {
    --monitoring-p50: #6cb4f0;
    --monitoring-p75: #f5a05b;
    --monitoring-p95: #e5e7eb;
}

[data-theme='contrast'] .atrium-monitoring-chart {
    --monitoring-p50: #000000;
    --monitoring-p75: #000000;
    --monitoring-p95: #000000;
}

.atrium-monitoring-chart .grid {
    stroke: var(--color-line);
    stroke-width: 1;
}

.atrium-monitoring-chart .axis,
.atrium-monitoring-chart .empty {
    fill: var(--color-muted);
    font-size: 12px;
}

.atrium-monitoring-chart .series path {
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.atrium-monitoring-chart .label {
    font-size: 12px;
    font-weight: 600;
}

.atrium-monitoring-chart .series.p50 path { stroke: var(--monitoring-p50); }
.atrium-monitoring-chart .series.p50 :is(circle, rect, polygon, .label) { fill: var(--monitoring-p50); }
.atrium-monitoring-chart .series.p75 path { stroke: var(--monitoring-p75); }
.atrium-monitoring-chart .series.p75 :is(circle, rect, polygon, .label) { fill: var(--monitoring-p75); }
.atrium-monitoring-chart .series.p95 path { stroke: var(--monitoring-p95); }
.atrium-monitoring-chart .series.p95 :is(circle, rect, polygon, .label) { fill: var(--monitoring-p95); }
</style>
