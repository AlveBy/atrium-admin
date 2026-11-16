<script setup>
/**
 * "Site speed" dashboard tile: the median page time, and where it falls on
 * a 0…2.5 s scale split into five named bands. The band is told by its name
 * and by the fill length, not by colour.
 */
import { computed } from 'vue'

const props = defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})

const current = computed(() => props.data.bands.indexOf(props.data.label))
</script>

<template>
    <div class="bg-white rounded-lg shadow px-5 py-4" data-gadget="site-speed">
        <h2 class="text-sm font-semibold text-gray-900">{{ title || $t('dashboard.site_speed.title') }}</h2>

        <div class="mt-3 flex items-baseline gap-2">
            <template v-if="data.seconds">
                <span class="text-3xl font-semibold text-gray-900 tabular-nums">{{ data.seconds }}</span>
                <span class="text-sm text-gray-600">{{ data.unit }}</span>
            </template>
            <span class="text-sm font-medium text-gray-900" :class="{ 'ml-2': data.seconds }" data-speed-band>{{ data.label }}</span>
        </div>

        <div class="mt-4">
            <div class="relative h-2 rounded-full bg-gray-200 overflow-hidden">
                <div
                    v-if="data.percent !== null"
                    class="absolute inset-y-0 left-0 bg-blue-600"
                    :style="{ width: data.percent + '%' }"
                />
                <span
                    v-for="mark in [20, 40, 60, 80]"
                    :key="mark"
                    class="absolute inset-y-0 w-0.5 bg-white"
                    :style="{ left: mark + '%' }"
                />
            </div>

            <div class="mt-1.5 grid grid-cols-5 gap-1 text-xs text-gray-600">
                <span
                    v-for="(band, index) in data.bands"
                    :key="band"
                    class="text-center leading-tight"
                    :class="{ 'font-semibold text-gray-900': index === current }"
                >{{ band }}</span>
            </div>

            <div class="mt-1 flex justify-between text-xs text-gray-600 tabular-nums">
                <span>0</span>
                <span>{{ data.scaleSeconds }} {{ data.unit }}</span>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-600">
            <template v-if="data.samples">
                {{ $t('dashboard.site_speed.samples_note', { samples: data.samples, days: data.windowDays }) }}
            </template>
            <template v-else>
                {{ $t('dashboard.site_speed.no_samples') }}
            </template>
        </p>
    </div>
</template>
