<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { ajaxHeaders } from '@atrium/Support/csrf'
import { t } from '@atrium/Support/i18n'

/*
 * Records of another module picked by a few words, for relation fields with
 * more records than fit in the page. The records come from `searchUrl`
 * ({ data: [{ value, label }], more }) twenty at a time: as the editor
 * types (after a short pause) and as the list is scrolled to its end.
 *
 * A combobox in the WAI-ARIA sense: arrows move through the list, Enter
 * picks, Escape closes it. The chosen records are chips above the box;
 * `selected` names the ones chosen before the form was opened.
 */
const props = defineProps({
    modelValue: { type: [Array, String, Number, null], default: null },
    inputId: { type: String, required: true },
    label: { type: String, default: '' },
    searchUrl: { type: String, required: true },
    selected: { type: Array, default: () => [] },
    multiple: { type: Boolean, default: false },
    maxItems: { type: Number, default: 0 },
    clearable: { type: Boolean, default: true },
    placeholder: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const DELAY = 250

const values = computed(() => {
    const raw = props.modelValue
    if (raw === null || raw === undefined || raw === '') return []
    return Array.isArray(raw) ? raw : [raw]
})

// Names of the records by value, from the form and from the answers.
const names = reactive(new Map(props.selected.map((option) => [String(option.value), option.label])))

const chips = computed(() => values.value.map((value) => ({
    value,
    label: names.get(String(value)) ?? `#${value}`,
})))

const isChosen = (value) => values.value.some((chosen) => String(chosen) === String(value))
const full = computed(() => props.multiple && props.maxItems > 0 && values.value.length >= props.maxItems)

const query = ref('')
const results = ref([])
const more = ref(false)
const page = ref(0)
const loading = ref(false)
const failed = ref(false)
const open = ref(false)
const active = ref(-1)
const list = ref(null)
let timer = null
let ticket = 0

const listId = computed(() => `${props.inputId}-options`)
const optionId = (index) => `${props.inputId}-option-${index}`

async function load(next) {
    const mine = ++ticket
    loading.value = true
    failed.value = false

    try {
        const params = new URLSearchParams({ q: query.value, page: String(next) })
        const separator = props.searchUrl.includes('?') ? '&' : '?'
        const response = await fetch(`${props.searchUrl}${separator}${params}`, { headers: ajaxHeaders(), credentials: 'same-origin' })
        if (!response.ok) throw new Error(String(response.status))
        const body = await response.json()
        if (mine !== ticket) return
        const rows = Array.isArray(body.data) ? body.data : []
        rows.forEach((row) => names.set(String(row.value), row.label))
        results.value = next === 1 ? rows : [...results.value, ...rows]
        more.value = body.more === true
        page.value = next
    } catch (e) {
        if (mine !== ticket) return
        failed.value = true
        more.value = false
        if (next === 1) results.value = []
    } finally {
        if (mine === ticket) loading.value = false
    }
}

function search() {
    active.value = -1
    load(1)
}

function loadMore() {
    if (more.value && !loading.value) load(page.value + 1)
}

// Typing, not every change of the text: clearing it after a pick must not
// open the list again.
function onInput() {
    open.value = true
    clearTimeout(timer)
    timer = setTimeout(search, DELAY)
}

onBeforeUnmount(() => clearTimeout(timer))

function show() {
    if (props.disabled || open.value) return
    open.value = true
    if (page.value === 0) search()
}

function close() {
    open.value = false
    active.value = -1
}

function pick(option) {
    if (!option || props.disabled) return

    names.set(String(option.value), option.label)

    if (!props.multiple) {
        emit('update:modelValue', option.value)
        reset()
        return
    }

    if (isChosen(option.value)) {
        remove(option.value)
    } else if (!full.value) {
        emit('update:modelValue', [...values.value, option.value])
    }
}

function remove(value) {
    if (props.disabled) return

    if (!props.multiple) {
        emit('update:modelValue', null)
        return
    }

    emit('update:modelValue', values.value.filter((chosen) => String(chosen) !== String(value)))
}

function clearAll() {
    if (!props.disabled) emit('update:modelValue', props.multiple ? [] : null)
}

function move(step) {
    if (!open.value) {
        show()
        return
    }

    const last = results.value.length - 1
    if (last < 0) return

    const next = Math.min(Math.max(active.value + step, 0), last)
    active.value = next

    if (next === last) loadMore()

    nextTick(() => document.getElementById(optionId(next))?.scrollIntoView?.({ block: 'nearest' }))
}

function onEnter() {
    if (open.value && active.value >= 0) pick(results.value[active.value])
}

// The next opening searches again, from the first page.
function reset() {
    query.value = ''
    page.value = 0
    close()
}

function onEscape() {
    if (open.value) {
        close()
    } else {
        reset()
    }
}

function onScroll() {
    const box = list.value
    if (box && box.scrollTop + box.clientHeight >= box.scrollHeight - 40) loadMore()
}

// Leaving the box for anything but its own list closes the list.
function onFocusOut(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) close()
}

const status = computed(() => {
    if (loading.value) return t('common.loading')
    if (failed.value) return t('fields.relation.failed')
    if (page.value > 0 && results.value.length === 0) return t('fields.relation.nothing')
    if (page.value > 0) return t('fields.relation.results', { count: results.value.length })
    return ''
})
</script>

<template>
    <div class="relation-search" :data-relation-search="inputId" @focusout="onFocusOut">
        <div v-if="chips.length" class="mb-2 flex flex-wrap items-center gap-1" data-relation-chosen>
            <span
                v-for="chip in chips"
                :key="chip.value"
                class="inline-flex items-center px-2 py-1 rounded-md text-sm bg-accent-soft text-accent-hover"
                :data-chip-value="chip.value"
            >
                {{ chip.label }}
                <button
                    v-if="multiple || clearable"
                    type="button"
                    class="ml-1 rounded text-accent-hover focus:outline-none focus:ring-2 focus:ring-focus"
                    :aria-label="t('fields.relation.remove', { label: chip.label })"
                    :disabled="disabled"
                    :data-remove-value="chip.value"
                    @click="remove(chip.value)"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
            <button
                v-if="multiple && chips.length > 1"
                type="button"
                class="text-xs text-muted hover:text-danger"
                :disabled="disabled"
                data-relation-clear
                @click="clearAll"
            >
                {{ t('fields.belongs_to_many.clear_all') }}
            </button>
        </div>

        <div class="relative">
            <input
                :id="inputId"
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="listId"
                :aria-activedescendant="open && active >= 0 ? optionId(active) : undefined"
                :aria-label="label || undefined"
                :aria-invalid="error ? 'true' : undefined"
                :placeholder="placeholder || t('fields.relation.search')"
                :disabled="disabled"
                class="w-full px-3 py-2 border border-field rounded-md shadow-sm bg-surface text-fg placeholder:text-muted focus:ring-focus focus:border-focus"
                :class="{ 'border-danger': error }"
                data-relation-input
                @input="onInput"
                @focus="show"
                @click="show"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="onEnter"
                @keydown.esc.prevent="onEscape"
            />

            <ul
                v-show="open"
                :id="listId"
                ref="list"
                role="listbox"
                :aria-label="label || undefined"
                :aria-multiselectable="multiple ? 'true' : undefined"
                tabindex="-1"
                class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-md border border-field bg-surface shadow-lg"
                data-relation-options
                @scroll="onScroll"
            >
                <li
                    v-for="(option, index) in results"
                    :id="optionId(index)"
                    :key="option.value"
                    role="option"
                    :aria-selected="isChosen(option.value) ? 'true' : 'false'"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-fg cursor-pointer border-b border-line last:border-b-0"
                    :class="{
                        'bg-hover ring-2 ring-inset ring-focus': index === active,
                        'bg-accent-soft': index !== active && isChosen(option.value),
                    }"
                    :data-option-value="option.value"
                    :data-option-selected="isChosen(option.value) ? 'true' : 'false'"
                    @mousedown.prevent
                    @mousemove="active = index"
                    @click="pick(option)"
                >
                    <svg
                        class="w-4 h-4 shrink-0 text-accent-hover"
                        :class="{ invisible: !isChosen(option.value) }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ option.label }}</span>
                </li>
                <li v-if="status" role="presentation" class="px-3 py-2 text-sm" :class="failed ? 'text-danger' : 'text-muted'" data-relation-status>
                    {{ status }}
                </li>
            </ul>
        </div>

        <p v-if="full" class="mt-1 text-sm text-muted" data-relation-full>
            {{ t('fields.relation.limit', { count: maxItems }) }}
        </p>

        <span class="sr-only" aria-live="polite">{{ open ? status : '' }}</span>
    </div>
</template>
