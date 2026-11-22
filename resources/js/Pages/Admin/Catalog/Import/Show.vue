<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * One import of products: map the file's columns to the fields, choose how
 * products are found and what to do with them, check, import, read the
 * report. While a check or an import runs the page asks for its state; on a
 * site without a queue worker each of those requests moves it forward.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ImportStatus from '@atrium/Components/Admin/Catalog/ImportStatus.vue'
import { ajaxHeaders } from '@atrium/Support/csrf.js'

const props = defineProps({
    import: { type: Object, required: true },
    preview: { type: Array, required: true },
    targets: { type: Array, required: true },
    keys: { type: Array, required: true },
    modes: { type: Array, required: true },
    strings: { type: Object, required: true },
})

const page = usePage()
const locale = computed(() => String(page.props.locale || 'en').replace('_', '-'))
const s = computed(() => props.strings.show)
const errors = computed(() => page.props.errors ?? {})

function replace(line, params) {
    return Object.entries(params).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value ?? '')), line)
}

function number(value) {
    return Number(value ?? 0).toLocaleString(locale.value)
}

// --- state, asked for while a check or an import runs -----------------------

const state = ref(props.import)
const running = computed(() => ['checking', 'importing'].includes(state.value.status))
const open = computed(() => ['new', 'checked', 'failed'].includes(state.value.status))
let timer = null
let stopped = false

watch(() => props.import, (value) => { state.value = value })

async function step() {
    const response = await fetch(route('admin.catalog-import.step', state.value.id), {
        method: 'POST',
        headers: ajaxHeaders(),
    }).catch(() => null)

    if (stopped) {
        return
    }

    if (response?.ok) {
        const next = await response.json()
        const finished = running.value && !['checking', 'importing'].includes(next.status)
        state.value = next

        if (finished) {
            router.reload()

            return
        }
    }

    schedule()
}

function schedule() {
    clearTimeout(timer)

    if (running.value) {
        timer = setTimeout(step, 1000)
    }
}

watch(running, schedule)
onMounted(schedule)
onBeforeUnmount(() => {
    stopped = true
    clearTimeout(timer)
})

const progress = computed(() => state.value.total > 0 ? Math.min(100, Math.round(state.value.processed * 100 / state.value.total)) : 0)

// --- mapping and options ------------------------------------------------------

const mapping = ref([...(props.import.mapping ?? props.import.headers.map(() => null))])
const options = ref({ ...props.import.options })
const saving = ref(false)

function samples(index) {
    return props.preview.map((row) => row[index] ?? '').filter((value) => value !== '').slice(0, 3)
}

function check() {
    saving.value = true
    router.put(route('admin.catalog-import.check', state.value.id), { mapping: mapping.value, ...options.value }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
    })
}

function run() {
    saving.value = true
    router.post(route('admin.catalog-import.run', state.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
    })
}

function cancel() {
    if (window.confirm(s.value.cancel_confirm)) {
        router.post(route('admin.catalog-import.cancel', state.value.id), {}, { preserveScroll: true })
    }
}

const delimiter = computed(() => props.strings.delimiters[state.value.delimiter] ?? state.value.delimiter)

const counts = computed(() => [
    ['created', state.value.created],
    ['updated', state.value.updated],
    ['skipped', state.value.skipped],
    ['failed', state.value.failed],
    ['sections', state.value.sections],
])

const listed = computed(() => state.value.errors ?? [])
const unlisted = computed(() => state.value.skipped + state.value.failed - listed.value.length)
const checked = computed(() => ['checking', 'checked'].includes(state.value.status))
const field = 'block w-full rounded-md border border-field bg-surface text-fg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <AdminLayout :title="strings.title">
        <Link :href="route('admin.catalog-import.index')" class="text-sm text-accent hover:text-accent-hover hover:underline" dusk="import-back">← {{ s.back }}</Link>

        <div class="mt-3 mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-fg break-all">{{ state.original_name }}</h1>
                <p class="mt-1 text-sm text-muted" dusk="import-file-info">
                    {{ replace(state.format === 'xlsx' ? s.workbook : s.file, { format: state.format.toUpperCase(), encoding: state.encoding, delimiter, rows: number(state.total) }) }}
                </p>
            </div>
            <ImportStatus :status="state.status" :strings="strings" />
        </div>

        <p v-if="state.message" class="mb-6 rounded-md bg-danger-soft text-danger-soft-fg px-4 py-3 text-sm" role="alert" dusk="import-message">{{ state.message }}</p>

        <!-- Progress -->
        <AdminCard v-if="running" class="mb-6">
            <p class="text-sm text-fg mb-2" dusk="import-progress">
                {{ state.status === 'checking' ? s.checking : s.importing }}
                {{ replace(s.progress, { processed: number(state.processed), total: number(state.total) }) }}
            </p>
            <div class="h-3 w-full rounded bg-off overflow-hidden" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full bg-accent transition-all" :style="{ width: progress + '%' }" />
            </div>
            <div class="mt-4">
                <AdminButton variant="secondary" dusk="import-cancel" @click="cancel">{{ s.cancel }}</AdminButton>
            </div>
        </AdminCard>

        <!-- Report -->
        <AdminCard v-if="state.started_at" :title="checked ? s.check_report : s.report" class="mb-6">
            <p v-if="state.status === 'checked'" class="mb-4 text-sm text-fg" dusk="import-checked">{{ s.checked }}</p>
            <p v-else-if="state.status === 'done'" class="mb-4 text-sm text-fg" dusk="import-done">{{ s.done }}</p>
            <p v-else-if="state.status === 'cancelled'" class="mb-4 text-sm text-fg">{{ s.cancelled }}</p>

            <dl class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div v-for="[name, value] in counts" :key="name" class="rounded-lg border border-line px-4 py-3 flex flex-col-reverse">
                    <dt class="text-xs text-muted">{{ checked ? s.will[name] : s.counts[name] }}</dt>
                    <dd class="text-lg font-semibold text-fg tabular-nums" :dusk="`import-count-${name}`">{{ number(value) }}</dd>
                </div>
            </dl>

            <div v-if="listed.length" class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm" dusk="import-errors">
                    <thead class="bg-surface-alt">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-fg">{{ s.row }}</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-fg">{{ s.outcome }}</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-fg">{{ s.reason }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="error in listed" :key="error.row" dusk="import-error">
                            <td class="px-4 py-2 tabular-nums text-fg align-top">{{ error.row }}</td>
                            <td class="px-4 py-2 align-top">
                                <span :class="['inline-block px-2 py-0.5 rounded text-xs font-medium', error.skipped ? 'bg-off text-off-fg' : 'bg-danger-soft text-danger-soft-fg']">
                                    {{ error.skipped ? s.skipped_row : s.failed_row }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-fg align-top">
                                <p v-for="(message, index) in error.messages" :key="index">{{ message }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="unlisted > 0" class="mt-2 text-xs text-muted">{{ replace(s.unlisted, { count: number(unlisted) }) }}</p>
            </div>

            <div v-if="state.status === 'checked'" class="mt-6 flex flex-col gap-2">
                <p class="text-sm text-muted">{{ s.run_hint }}</p>
                <div>
                    <AdminButton :disabled="saving" dusk="import-run" @click="run">{{ s.run }}</AdminButton>
                </div>
            </div>
        </AdminCard>

        <!-- Mapping and options -->
        <form v-if="open" class="flex flex-col gap-6" dusk="import-settings" @submit.prevent="check">
            <AdminCard :title="s.mapping" :subtitle="s.mapping_hint" no-padding>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-alt">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left font-medium text-fg">{{ s.column }}</th>
                                <th scope="col" class="px-4 py-2 text-left font-medium text-fg">{{ s.samples }}</th>
                                <th scope="col" class="px-4 py-2 text-left font-medium text-fg w-80">{{ s.target }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="(header, index) in state.headers" :key="index" dusk="import-column">
                                <td class="px-4 py-2 font-medium text-fg align-top break-words">
                                    <label :for="`target-${index}`">{{ header || replace(s.unnamed, { number: index + 1 }) }}</label>
                                </td>
                                <td class="px-4 py-2 text-muted align-top">
                                    <p v-for="(sample, n) in samples(index)" :key="n" class="truncate max-w-xs" :title="sample">{{ sample }}</p>
                                </td>
                                <td class="px-4 py-2 align-top">
                                    <select :id="`target-${index}`" v-model="mapping[index]" :class="field" :dusk="`import-target-${index}`">
                                        <option :value="null">{{ s.skip }}</option>
                                        <option v-for="target in targets" :key="target.key" :value="target.key">{{ target.label }}</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="errors.mapping" class="px-6 py-3 text-sm text-danger" role="alert" dusk="import-mapping-error">{{ errors.mapping }}</p>
            </AdminCard>

            <AdminCard :title="s.options">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="block text-sm font-medium text-fg mb-1">{{ strings.settings.key }}</span>
                        <select v-model="options.key" :class="field" dusk="import-key">
                            <option v-for="key in keys" :key="key.key" :value="key.key">{{ key.label }}</option>
                        </select>
                        <span class="block mt-1 text-xs text-muted">{{ s.key_hint }}</span>
                        <span v-if="errors.key" class="block mt-1 text-sm text-danger" role="alert" dusk="import-key-error">{{ errors.key }}</span>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-fg mb-1">{{ strings.settings.mode }}</span>
                        <select v-model="options.mode" :class="field" dusk="import-mode">
                            <option v-for="mode in modes" :key="mode" :value="mode">{{ strings.modes[mode] }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-fg mb-1">{{ strings.settings.separator }}</span>
                        <input v-model="options.separator" type="text" maxlength="5" :class="field" dusk="import-separator">
                        <span class="block mt-1 text-xs text-muted">{{ s.separator_hint }}</span>
                        <span v-if="errors.separator" class="block mt-1 text-sm text-danger" role="alert">{{ errors.separator }}</span>
                    </label>
                    <label class="flex items-start gap-2 md:mt-7">
                        <input v-model="options.create_sections" type="checkbox" class="mt-1 rounded border-field text-accent focus:ring-focus" dusk="import-create-sections">
                        <span class="text-sm text-fg">{{ strings.settings.create_sections }}</span>
                    </label>
                </div>
            </AdminCard>

            <div class="flex flex-col gap-2">
                <p class="text-sm text-muted">{{ s.check_hint }}</p>
                <div>
                    <AdminButton type="submit" :variant="state.status === 'checked' ? 'secondary' : 'primary'" :disabled="saving" dusk="import-check">
                        {{ state.status === 'checked' ? s.check_again : s.check }}
                    </AdminButton>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
