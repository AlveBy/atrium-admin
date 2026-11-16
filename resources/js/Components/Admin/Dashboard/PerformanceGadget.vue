<script setup>
/**
 * "Performance monitor" dashboard tile.
 *
 * Mirrors the Bitrix gadget: a green card, a "Details" button on the left,
 * a large score on the right. In Bitrix the button led to a separate perfmon
 * module section; here it expands a list of the heaviest pages — exactly the
 * question people opened that section for.
 */
import { ref } from 'vue'

defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})

const expanded = ref(false)
</script>

<template>
    <div class="perf-card rounded-lg shadow px-5 py-4">
        <div class="perf-title">{{ title || $t('dashboard.performance.title') }}</div>

        <div class="mt-2 flex items-end justify-between gap-4">
            <button
                v-if="data.slowest.length"
                type="button"
                class="perf-button"
                :class="{ 'perf-button-active': expanded }"
                @click="expanded = !expanded"
            >
                <span class="perf-lamp" />
                {{ expanded ? $t('dashboard.performance.collapse') : $t('dashboard.performance.details') }}
            </button>
            <span v-else />

            <div v-if="data.rate" class="flex items-center gap-3">
                <div class="perf-mark">{{ data.rate }}</div>
                <div class="perf-caption">
                    {{ data.caption }}<br>
                    <span class="perf-unit">{{ data.unit }}</span>
                </div>
            </div>
            <div v-else class="perf-caption text-right">
                {{ $t('dashboard.performance.pending') }}
            </div>
        </div>

        <p v-if="data.rate" class="perf-note">
            {{ $t('dashboard.performance.note', { seconds: data.averageSeconds, pages: data.samples, days: data.windowDays }) }}
        </p>

        <div v-if="expanded" class="perf-details">
            <div class="perf-details-title">{{ $t('dashboard.performance.slowest_title') }}</div>
            <ul>
                <li v-for="row in data.slowest" :key="row.path">
                    <span class="perf-path" :title="row.path">{{ row.path }}</span>
                    <span class="perf-ms">{{ $t('dashboard.performance.slowest_row', { ms: row.ms, hits: row.hits }) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.perf-card {
    background-image: linear-gradient(to bottom, #a3bf40, #93af30, #829d1e);
    color: #4a5813;
}

.perf-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
    text-shadow: 0 1px 0 rgba(243, 244, 188, 0.73);
}

.perf-button {
    align-items: center;
    background-image: linear-gradient(to bottom, #b6d734, #86a21c);
    border: 1px solid;
    border-color: #86a129 #748b1f #586a15;
    border-radius: 4px;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2), 0 1px 0 #778f1d;
    color: #fff;
    display: inline-flex;
    font-size: 12px;
    gap: 6px;
    padding: 5px 12px;
    text-shadow: 0 -1px 0 rgba(74, 91, 11, 0.6);
}

.perf-button:hover {
    background-image: linear-gradient(to bottom, #cdec51, #9cba26);
}

.perf-button-active {
    background: #667a16;
    box-shadow: 0 1px 0 0 rgba(230, 238, 198, 0.6), inset 0 2px 3px 0 rgba(0, 0, 0, 0.4);
}

.perf-lamp {
    background: #e8f5a0;
    border-radius: 50%;
    box-shadow: 0 0 4px #f0ffb0;
    display: inline-block;
    height: 7px;
    width: 7px;
}

.perf-mark {
    color: #3b4a00;
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
    text-shadow: 0 1px 0 rgba(243, 244, 188, 0.73);
}

.perf-caption {
    color: #4a5813;
    font-size: 12px;
    line-height: 1.25;
}

.perf-unit {
    color: #56660f;
    font-size: 10px;
}

.perf-note {
    color: #46540f;
    font-size: 11px;
    margin-top: 10px;
}

.perf-details {
    border-top: 1px solid rgba(74, 88, 19, 0.25);
    font-size: 11px;
    margin-top: 10px;
    padding-top: 8px;
}

.perf-details-title {
    font-weight: 700;
    margin-bottom: 4px;
}

.perf-details li {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    padding: 2px 0;
}

.perf-path {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.perf-ms {
    color: #3b4a00;
    font-weight: 700;
    white-space: nowrap;
}
</style>
