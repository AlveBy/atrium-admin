<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, inject, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { t } from '@atrium/Support/i18n'

/*
 * The fields of a form visitors fill in, on the form's own page: a palette
 * adds a field, a card sets it up, dragging (or the arrow buttons, from the
 * keyboard) orders the fields, and each takes 1 to 12 columns of a grid. The
 * preview beside them shows the form as the site draws it, on a computer or
 * on a phone, where every field takes the whole row.
 *
 * Texts (label, hint, placeholder, options) are kept per content language;
 * the language switch of the page picks which one is edited. Every change
 * emits the whole list, so the server gets exactly what is on screen.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: null },
    types: { type: Object, default: () => ({}) },
    choiceTypes: { type: Array, default: () => [] },
    columns: { type: Number, default: 12 },
    maxItems: { type: Number, default: 100 },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const page = usePage()
const languages = computed(() => page.props.contentLocales || [])
const defaultLocale = computed(() => (languages.value.find((language) => language.default) || languages.value[0] || {}).code || 'en')
const injected = inject('contentLocale', ref(null))
const locale = computed(() => injected.value || defaultLocale.value)
const language = computed(() => languages.value.find((item) => item.code === locale.value))
const multilingual = computed(() => languages.value.length > 1)

const items = computed(() => Array.isArray(props.modelValue) ? props.modelValue : [])
const canAdd = computed(() => !props.disabled && items.value.length < props.maxItems)
const open = ref(null)
const dragging = ref(null)
const dropAt = ref(null)
const device = ref('desktop')
let lastKey = 0

/** Widths offered as buttons; any other is in the list beside them. */
const presets = computed(() => [
    { width: props.columns, label: t('fields.form_builder.width_full') },
    { width: props.columns / 2, label: '1/2' },
    { width: props.columns / 3, label: '1/3' },
    { width: props.columns / 4, label: '1/4' },
].filter((preset) => Number.isInteger(preset.width)))

const lengthTypes = ['text', 'email', 'tel', 'textarea']

function copy(list) {
    return JSON.parse(JSON.stringify(list))
}

function keyed(list) {
    for (const item of list) {
        if (item._key === undefined) {
            item._key = `f${++lastKey}`
        }
    }
    return list
}

function change(mutate) {
    const list = keyed(copy(items.value))
    mutate(list)
    emit('update:modelValue', list)
}

function text(item, field, code = locale.value) {
    const value = item[field]
    return value && typeof value === 'object' ? (value[code] ?? '') : ''
}

/** What the site shows: this language, or the default one where it is empty. */
function shown(item, field) {
    return text(item, field) || text(item, field, defaultLocale.value)
}

function options(item) {
    return [...new Set(shown(item, 'options').split(/\r?\n/).map((line) => line.trim()).filter(Boolean))]
}

const latin = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm',
    н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch',
    ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya', і: 'i', ї: 'yi', є: 'ye', ґ: 'g', ў: 'u', қ: 'q', ғ: 'g',
    ң: 'n', ө: 'o', ұ: 'u', ү: 'u', һ: 'h', ә: 'a',
}

/** A code from a label: Latin letters, digits and "_", unique in the form. */
function codeFrom(label, list, self) {
    const base = [...label.toLowerCase()].map((char) => latin[char] ?? char).join('')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 60) || 'field'
    const taken = new Set(list.filter((item) => item._key !== self).map((item) => item.name))
    let code = base
    for (let index = 2; taken.has(code); index++) {
        code = `${base}_${index}`
    }
    return code
}

function add(type) {
    if (!canAdd.value) return
    const key = `f${++lastKey}`
    change((list) => {
        const label = props.types[type] || type
        list.push({
            _key: key,
            _auto: true,
            name: codeFrom(label, list, key),
            type,
            required: false,
            width: props.columns,
            min: null,
            max: null,
            label: { [defaultLocale.value]: label },
            hint: {},
            placeholder: {},
            options: props.choiceTypes.includes(type) ? { [defaultLocale.value]: `${t('fields.form_builder.option')} 1\n${t('fields.form_builder.option')} 2` } : {},
        })
    })
    open.value = key
}

function find(list, key) {
    return list.find((item) => item._key === key)
}

function set(key, field, value) {
    change((list) => {
        const item = find(list, key)
        if (!item) return
        item[field] = value
        if (field === 'name') item._auto = false
    })
}

function setText(key, field, value) {
    change((list) => {
        const item = find(list, key)
        if (!item) return
        item[field] = { ...(item[field] && typeof item[field] === 'object' ? item[field] : {}), [locale.value]: value }
        // A new field's code follows its label until the code is typed.
        if (field === 'label' && item._auto && locale.value === defaultLocale.value) {
            item.name = codeFrom(value, list, key)
        }
    })
}

function setNumber(key, field, value) {
    set(key, field, value === '' || value === null ? null : Math.trunc(Number(value)))
}

function remove(key) {
    change((list) => list.splice(list.findIndex((item) => item._key === key), 1))
    if (open.value === key) open.value = null
}

function move(index, step) {
    const to = index + step
    if (to < 0 || to >= items.value.length) return
    change((list) => list.splice(to, 0, ...list.splice(index, 1)))
}

function onDragStart(event, key) {
    if (props.disabled) return
    dragging.value = key
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', key)
}

function onDragOver(event, index) {
    if (dragging.value === null) return
    event.preventDefault()
    const box = event.currentTarget.getBoundingClientRect()
    dropAt.value = event.clientY - box.top < box.height / 2 ? index : index + 1
}

function onDrop(event) {
    event.preventDefault()
    const key = dragging.value
    const at = dropAt.value
    dragging.value = null
    dropAt.value = null
    if (key === null || at === null) return
    change((list) => {
        const from = list.findIndex((item) => item._key === key)
        const [item] = list.splice(from, 1)
        list.splice(at > from ? at - 1 : at, 0, item)
    })
}

function onDragEnd() {
    dragging.value = null
    dropAt.value = null
}

/** What is wrong with a field before the server says so. */
function problem(item, list) {
    if (!text(item, 'label', defaultLocale.value).trim()) return t('fields.form_builder.needs_label')
    if (!/^[A-Za-z0-9_-]+$/.test(item.name || '')) return t('fields.form_builder.needs_name')
    if (list.filter((other) => other.name === item.name).length > 1) return t('fields.form_builder.name_taken', { name: item.name })
    if (props.choiceTypes.includes(item.type) && options(item).length === 0) return t('fields.form_builder.needs_options')
    return null
}

const problems = computed(() => Object.fromEntries(items.value.map((item, index) => [item._key ?? index, problem(item, items.value)])))

function span(item) {
    const width = Number(item.width) || props.columns
    return device.value === 'phone' ? props.columns : Math.min(props.columns, Math.max(1, width))
}

const input = 'w-full px-3 py-2 border border-field rounded-md bg-surface text-fg focus:ring-focus focus:border-focus disabled:opacity-60'
const smallButton = 'p-1 rounded text-muted hover:text-fg hover:bg-hover disabled:opacity-30 disabled:cursor-not-allowed'

// Fields from the server come without keys: give them keys once.
onMounted(() => {
    if (items.value.some((item) => item._key === undefined)) {
        emit('update:modelValue', keyed(copy(items.value)))
    }
})
</script>

<template>
    <div class="form-builder" :data-builder="name" dusk="form-builder">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
            <span v-if="label" class="block text-sm font-medium text-fg">
                {{ label }}
                <span v-if="required" class="text-danger">*</span>
            </span>
            <span v-if="multilingual && language" class="text-sm text-muted" dusk="builder-language">
                {{ t('fields.form_builder.language', { language: language.name }) }}
            </span>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <!-- The fields and their settings -->
            <section :aria-label="t('fields.form_builder.fields')">
                <div v-if="canAdd" class="mb-3" role="group" :aria-label="t('fields.form_builder.add')">
                    <p class="text-sm text-muted mb-2">{{ t('fields.form_builder.add') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(typeLabel, type) in types"
                            :key="type"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-sm font-medium text-accent-soft-fg bg-accent-soft rounded-md border border-accent-soft hover:border-accent"
                            :dusk="`builder-add-${type}`"
                            @click="add(type)"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            {{ typeLabel }}
                        </button>
                    </div>
                </div>

                <ol v-if="items.length" class="space-y-2" @dragover.prevent @drop="onDrop">
                    <li
                        v-for="(item, index) in items"
                        :key="item._key ?? index"
                        :dusk="`builder-item-${index}`"
                        :data-builder-name="item.name"
                        @dragover="onDragOver($event, index)"
                    >
                        <div
                            class="flex items-center gap-2 rounded-md border bg-surface px-2 py-1.5"
                            :class="[
                                problems[item._key ?? index] ? 'border-danger' : 'border-line',
                                dropAt === index && dragging !== item._key ? 'border-t-4 border-t-accent' : '',
                                dropAt === index + 1 && index === items.length - 1 ? 'border-b-4 border-b-accent' : '',
                                dragging === item._key ? 'opacity-50' : '',
                            ]"
                            :draggable="!disabled"
                            @dragstart="onDragStart($event, item._key)"
                            @dragend="onDragEnd"
                        >
                            <span class="cursor-grab select-none text-muted" :title="t('fields.form_builder.drag')" aria-hidden="true">⋮⋮</span>
                            <button
                                type="button"
                                class="flex-1 min-w-0 text-left"
                                :aria-expanded="open === item._key ? 'true' : 'false'"
                                :dusk="`builder-toggle-${index}`"
                                @click="open = open === item._key ? null : item._key"
                            >
                                <span class="font-medium text-fg">{{ shown(item, 'label') || t('fields.form_builder.untitled') }}</span>
                                <span v-if="item.required" class="text-danger" aria-hidden="true"> *</span>
                                <span class="ml-2 text-sm text-muted">{{ types[item.type] || item.type }} · {{ t('fields.form_builder.columns', { count: Number(item.width) || columns, total: columns }) }}</span>
                                <span v-if="problems[item._key ?? index]" class="block text-sm text-danger" :dusk="`builder-problem-${index}`">{{ problems[item._key ?? index] }}</span>
                            </button>
                            <div class="flex items-center gap-0.5 shrink-0">
                                <button type="button" :class="smallButton" :disabled="disabled || index === 0" :title="t('fields.form_builder.move_up')" :aria-label="t('fields.form_builder.move_up')" :dusk="`builder-up-${index}`" @click="move(index, -1)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                </button>
                                <button type="button" :class="smallButton" :disabled="disabled || index === items.length - 1" :title="t('fields.form_builder.move_down')" :aria-label="t('fields.form_builder.move_down')" :dusk="`builder-down-${index}`" @click="move(index, 1)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <button type="button" class="p-1 rounded text-danger hover:bg-danger-soft disabled:opacity-30" :disabled="disabled" :title="t('common.delete')" :aria-label="t('common.delete')" :dusk="`builder-remove-${index}`" @click="remove(item._key)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <div v-if="open === item._key" class="mt-1 mb-2 ml-6 grid gap-3 sm:grid-cols-2 rounded-md border border-line bg-surface-alt p-3" :dusk="`builder-settings-${index}`">
                            <label class="block text-sm text-fg sm:col-span-2">
                                {{ t('fields.form_builder.label') }}<span v-if="multilingual && language"> ({{ language.name }})</span>
                                <input type="text" :class="input" :value="text(item, 'label')" :placeholder="locale !== defaultLocale ? text(item, 'label', defaultLocale) : ''" :disabled="disabled" maxlength="255" dusk="builder-label" @input="setText(item._key, 'label', $event.target.value)">
                            </label>
                            <label class="block text-sm text-fg">
                                {{ t('fields.form_builder.name') }}
                                <input type="text" :class="input" :value="item.name" :disabled="disabled" maxlength="100" spellcheck="false" dusk="builder-name" @input="set(item._key, 'name', $event.target.value.trim())">
                                <span class="block mt-1 text-muted">{{ t('fields.form_builder.name_hint') }}</span>
                            </label>
                            <label class="block text-sm text-fg">
                                {{ t('fields.form_builder.type') }}
                                <select :class="input" :value="item.type" :disabled="disabled" dusk="builder-type" @change="set(item._key, 'type', $event.target.value)">
                                    <option v-for="(typeLabel, type) in types" :key="type" :value="type">{{ typeLabel }}</option>
                                </select>
                            </label>
                            <fieldset class="sm:col-span-2">
                                <legend class="text-sm text-fg mb-1">{{ t('fields.form_builder.width') }}</legend>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="preset in presets"
                                        :key="preset.width"
                                        type="button"
                                        class="px-2.5 py-1 text-sm rounded-md border"
                                        :class="Number(item.width) === preset.width ? 'border-accent bg-accent-soft text-accent-soft-fg font-medium' : 'border-field bg-surface text-fg hover:bg-hover'"
                                        :aria-pressed="Number(item.width) === preset.width ? 'true' : 'false'"
                                        :disabled="disabled"
                                        :dusk="`builder-width-${preset.width}`"
                                        @click="set(item._key, 'width', preset.width)"
                                    >{{ preset.label }}</button>
                                    <select class="px-2 py-1 text-sm border border-field rounded-md bg-surface text-fg" :value="Number(item.width)" :disabled="disabled" :aria-label="t('fields.form_builder.width')" dusk="builder-width" @change="set(item._key, 'width', Number($event.target.value))">
                                        <option v-for="count in columns" :key="count" :value="count">{{ t('fields.form_builder.columns', { count, total: columns }) }}</option>
                                    </select>
                                </div>
                            </fieldset>
                            <label class="flex items-center gap-2 text-sm text-fg sm:col-span-2">
                                <input type="checkbox" class="h-4 w-4 text-accent focus:ring-focus border-field rounded" :checked="!!item.required" :disabled="disabled" dusk="builder-required" @change="set(item._key, 'required', $event.target.checked)">
                                {{ t('fields.form_builder.required') }}
                            </label>
                            <label v-if="choiceTypes.includes(item.type)" class="block text-sm text-fg sm:col-span-2">
                                {{ t('fields.form_builder.options') }}<span v-if="multilingual && language"> ({{ language.name }})</span>
                                <textarea :class="input" rows="4" :value="text(item, 'options')" :disabled="disabled" dusk="builder-options" @input="setText(item._key, 'options', $event.target.value)" />
                                <span class="block mt-1 text-muted">{{ t('fields.form_builder.options_hint') }}</span>
                            </label>
                            <label v-if="!['checkbox', 'radio', 'select', 'date'].includes(item.type)" class="block text-sm text-fg sm:col-span-2">
                                {{ t('fields.form_builder.placeholder') }}<span v-if="multilingual && language"> ({{ language.name }})</span>
                                <input type="text" :class="input" :value="text(item, 'placeholder')" :disabled="disabled" maxlength="255" dusk="builder-placeholder" @input="setText(item._key, 'placeholder', $event.target.value)">
                            </label>
                            <label class="block text-sm text-fg sm:col-span-2">
                                {{ t('fields.form_builder.hint') }}<span v-if="multilingual && language"> ({{ language.name }})</span>
                                <input type="text" :class="input" :value="text(item, 'hint')" :disabled="disabled" maxlength="500" dusk="builder-hint" @input="setText(item._key, 'hint', $event.target.value)">
                            </label>
                            <template v-if="item.type === 'number' || lengthTypes.includes(item.type)">
                                <label class="block text-sm text-fg">
                                    {{ item.type === 'number' ? t('fields.form_builder.min_value') : t('fields.form_builder.min_length') }}
                                    <input type="number" :class="input" :value="item.min ?? ''" :min="item.type === 'number' ? null : 0" step="1" :disabled="disabled" dusk="builder-min" @input="setNumber(item._key, 'min', $event.target.value)">
                                </label>
                                <label class="block text-sm text-fg">
                                    {{ item.type === 'number' ? t('fields.form_builder.max_value') : t('fields.form_builder.max_length') }}
                                    <input type="number" :class="input" :value="item.max ?? ''" :min="item.type === 'number' ? null : 0" step="1" :disabled="disabled" dusk="builder-max" @input="setNumber(item._key, 'max', $event.target.value)">
                                </label>
                            </template>
                        </div>
                    </li>
                </ol>

                <p v-else class="text-muted text-sm py-2" dusk="builder-empty">{{ t('fields.form_builder.empty') }}</p>

                <p v-if="hint" class="mt-2 text-sm text-muted">{{ hint }}</p>
                <p v-if="error" class="mt-2 text-sm text-danger" role="alert" dusk="builder-error">{{ error }}</p>
            </section>

            <!-- How the site shows it -->
            <section :aria-label="t('fields.form_builder.preview')" class="xl:sticky xl:top-4 self-start">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <p class="text-sm font-medium text-fg">{{ t('fields.form_builder.preview') }}</p>
                    <div class="inline-flex rounded-md border border-field overflow-hidden" role="group" :aria-label="t('fields.form_builder.device')">
                        <button
                            v-for="option in ['desktop', 'phone']"
                            :key="option"
                            type="button"
                            class="px-2.5 py-1 text-sm"
                            :class="device === option ? 'bg-accent-soft text-accent-soft-fg font-medium' : 'bg-surface text-fg hover:bg-hover'"
                            :aria-pressed="device === option ? 'true' : 'false'"
                            :dusk="`builder-device-${option}`"
                            @click="device = option"
                        >{{ t(`fields.form_builder.${option}`) }}</button>
                    </div>
                </div>
                <div
                    class="rounded-md border border-line bg-surface p-4 mx-auto"
                    :class="device === 'phone' ? 'max-w-[22rem]' : ''"
                    inert
                    dusk="builder-preview"
                    :data-device="device"
                >
                    <div v-if="items.length" class="grid gap-4" :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }">
                        <div
                            v-for="(item, index) in items"
                            :key="item._key ?? index"
                            class="grid gap-1 text-sm text-fg"
                            :style="{ gridColumn: `span ${span(item)} / span ${span(item)}` }"
                            :dusk="`builder-preview-${item.name}`"
                            :data-span="span(item)"
                        >
                            <label v-if="item.type === 'checkbox'" class="flex items-center gap-2">
                                <input type="checkbox" class="h-4 w-4 border-field rounded"> {{ shown(item, 'label') }}<span v-if="item.required" class="text-danger">*</span>
                            </label>
                            <template v-else>
                                <span class="font-medium">{{ shown(item, 'label') || t('fields.form_builder.untitled') }}<span v-if="item.required" class="text-danger"> *</span></span>
                                <template v-if="item.type === 'radio'">
                                    <label v-for="option in options(item)" :key="option" class="flex items-center gap-2"><input type="radio" :name="`preview-${item._key}`"> {{ option }}</label>
                                </template>
                                <select v-else-if="item.type === 'select'" :class="input">
                                    <option>{{ t('fields.form_builder.choose') }}</option>
                                    <option v-for="option in options(item)" :key="option">{{ option }}</option>
                                </select>
                                <textarea v-else-if="item.type === 'textarea'" :class="input" rows="3" :placeholder="shown(item, 'placeholder')" />
                                <input v-else :type="item.type" :class="input" :placeholder="shown(item, 'placeholder')">
                            </template>
                            <span v-if="shown(item, 'hint')" class="text-muted">{{ shown(item, 'hint') }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted">{{ t('fields.form_builder.preview_empty') }}</p>
                </div>
            </section>
        </div>
    </div>
</template>
