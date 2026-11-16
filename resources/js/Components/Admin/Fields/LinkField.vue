<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, ref, watch } from 'vue'
import { ajaxHeaders } from '@atrium/Support/csrf'
import { t } from '@atrium/Support/i18n'

/*
 * Where a link goes: nowhere, an address, or a record of a module picked
 * from a list (searched by a few words). The value is null,
 * { type: 'url', url } or { type: 'record', model, id, label }.
 */
const props = defineProps({
    modelValue: { type: [Object, null], default: null },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: null },
    targets: { type: Array, default: () => [] },
    searchUrl: { type: String, default: null },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const value = computed(() => (props.modelValue && typeof props.modelValue === 'object') ? props.modelValue : null)
const type = computed(() => value.value?.type || '')
const model = ref(value.value?.model || props.targets[0]?.value || '')
const query = ref('')
const results = ref([])
const loading = ref(false)
const failed = ref(false)
const picking = ref(false)
let timer = null
let request = 0

const moduleLabel = computed(() => props.targets.find((target) => target.value === value.value?.model)?.label || '')

function setType(next) {
    if (next === '') {
        emit('update:modelValue', null)
    } else if (next === 'url') {
        emit('update:modelValue', { type: 'url', url: value.value?.type === 'url' ? value.value.url : '' })
    } else {
        picking.value = true
        emit('update:modelValue', { type: 'record', model: model.value, id: null, label: '' })
        search()
    }
}

function setUrl(url) {
    emit('update:modelValue', { type: 'url', url })
}

function choose(record) {
    emit('update:modelValue', { type: 'record', model: model.value, id: record.id, label: record.label, url: record.url })
    picking.value = false
    results.value = []
    query.value = ''
}

async function search() {
    if (!props.searchUrl || !model.value) return
    const ticket = ++request
    loading.value = true
    failed.value = false
    try {
        const params = new URLSearchParams({ model: model.value, q: query.value })
        const response = await fetch(`${props.searchUrl}?${params}`, { headers: ajaxHeaders(), credentials: 'same-origin' })
        if (!response.ok) throw new Error(String(response.status))
        const body = await response.json()
        if (ticket === request) results.value = body.data || []
    } catch (e) {
        if (ticket === request) {
            results.value = []
            failed.value = true
        }
    } finally {
        if (ticket === request) loading.value = false
    }
}

watch(query, () => {
    clearTimeout(timer)
    timer = setTimeout(search, 250)
})

watch(model, () => {
    query.value = ''
    search()
})

const input = 'w-full px-3 py-2 border border-field rounded-md shadow-sm focus:ring-focus focus:border-focus bg-surface'
</script>

<template>
    <div class="link-field" :data-link-type="type || 'none'">
        <span v-if="label" class="block text-sm font-medium text-fg mb-1">
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </span>

        <div class="flex flex-wrap gap-4 mb-2 text-sm" role="radiogroup" :aria-label="label">
            <label v-if="!required" class="inline-flex items-center gap-1.5">
                <input type="radio" :name="`${name}-type`" value="" :checked="type === ''" :disabled="disabled" data-link-kind="none" @change="setType('')" />
                {{ t('fields.link.none') }}
            </label>
            <label class="inline-flex items-center gap-1.5">
                <input type="radio" :name="`${name}-type`" value="url" :checked="type === 'url'" :disabled="disabled" data-link-kind="url" @change="setType('url')" />
                {{ t('fields.link.url') }}
            </label>
            <label v-if="targets.length" class="inline-flex items-center gap-1.5">
                <input type="radio" :name="`${name}-type`" value="record" :checked="type === 'record'" :disabled="disabled" data-link-kind="record" @change="setType('record')" />
                {{ t('fields.link.record') }}
            </label>
        </div>

        <input
            v-if="type === 'url'"
            :id="name"
            type="text"
            :class="input"
            :value="value.url"
            :disabled="disabled"
            placeholder="/contacts"
            data-link-url
            @input="setUrl($event.target.value)"
        />

        <div v-else-if="type === 'record'" class="space-y-2">
            <div v-if="value.id && !picking" class="flex items-center gap-3 text-sm" data-link-chosen>
                <span class="text-muted">{{ moduleLabel }}:</span>
                <span class="font-medium text-fg">{{ value.label || `#${value.id}` }}</span>
                <a v-if="value.url" :href="value.url" target="_blank" rel="noopener" class="text-accent hover:underline truncate">{{ value.url }}</a>
                <button type="button" class="text-accent hover:underline" data-action="link-change" :disabled="disabled" @click="picking = true; search()">
                    {{ t('fields.link.change') }}
                </button>
            </div>

            <template v-else>
                <div class="flex flex-wrap gap-2">
                    <select v-model="model" :class="input" class="sm:w-56" :disabled="disabled" :aria-label="t('fields.link.module')" data-link-module>
                        <option v-for="target in targets" :key="target.value" :value="target.value">{{ target.label }}</option>
                    </select>
                    <input
                        v-model="query"
                        type="search"
                        :class="input"
                        class="flex-1 min-w-48"
                        :placeholder="t('fields.link.search')"
                        :disabled="disabled"
                        data-link-search
                    />
                </div>
                <p v-if="loading" class="text-sm text-muted">{{ t('common.loading') }}</p>
                <p v-else-if="failed" class="text-sm text-danger">{{ t('fields.link.failed') }}</p>
                <p v-else-if="!results.length" class="text-sm text-muted">{{ t('fields.link.nothing') }}</p>
                <ul v-else class="max-h-56 overflow-y-auto divide-y divide-line rounded-md border border-line bg-surface">
                    <li v-for="record in results" :key="record.id">
                        <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-hover" :data-link-record="record.id" @click="choose(record)">
                            <span class="font-medium text-fg">{{ record.label }}</span>
                            <span v-if="record.url" class="block truncate text-xs text-muted">{{ record.url }}</span>
                        </button>
                    </li>
                </ul>
            </template>
        </div>

        <p v-if="hint" class="mt-1 text-sm text-muted">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-sm text-danger">{{ error }}</p>
    </div>
</template>
