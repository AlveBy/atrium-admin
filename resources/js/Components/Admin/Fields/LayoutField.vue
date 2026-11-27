<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, inject, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FormField from './FormField.vue'
import LayoutControls from './LayoutControls.vue'
import { initialValue } from '../../../Support/contentLocale'
import { t } from '@atrium/Support/i18n'

/*
 * The tree of a page built of blocks (docs/site-builder.md): sections, their
 * columns and the blocks in them. Nodes are added, ordered with the arrow
 * buttons and deleted; a block's settings are the fields of its type. Every
 * change emits the whole tree, and nothing is emitted until something changes.
 */
const props = defineProps({
    modelValue: { type: [Object, Array, String], default: null },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: null },
    types: { type: Array, default: () => [] },
    sectionFields: { type: Array, default: () => [] },
    columnFields: { type: Array, default: () => [] },
    maxSections: { type: Number, default: 100 },
    maxColumns: { type: Number, default: 12 },
    maxBlocks: { type: Number, default: 100 },
    version: { type: Number, default: 1 },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const GRID = 12

const page = usePage()
const languages = computed(() => page.props.contentLocales || [])
const locale = inject('contentLocale', ref(null))
const language = computed(() => languages.value.find((item) => item.code === locale.value))
const multilingual = computed(() => languages.value.length > 1)

// An empty PHP array arrives as [] where the tree means an empty object.
const object = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {})
const list = (value) => (Array.isArray(value) ? value : [])

const sections = computed(() => list(object(props.modelValue).sections).map((section) => ({
    ...section,
    settings: object(section.settings),
    columns: list(section.columns).map((column) => ({
        ...column,
        settings: object(column.settings),
        blocks: list(column.blocks).map((block) => ({ ...block, settings: object(block.settings) })),
    })),
})))

const open = ref({})
const chosen = ref({})
const noErrors = {}
let lastId = 0

function change(mutate) {
    const next = JSON.parse(JSON.stringify(sections.value))
    mutate(next)
    emit('update:modelValue', { version: props.version, sections: next })
}

/** Unique in the tree: the time, a counter and chance. */
function newId(prefix) {
    return `${prefix}${Date.now().toString(36)}${++lastId}${Math.random().toString(36).slice(2, 6)}`
}

/** The settings a new node starts with: those its fields have a default for. */
function defaults(fields) {
    return Object.fromEntries(fields
        .filter((field) => field.default !== null && field.default !== undefined)
        .map((field) => [field.name, initialValue(field, undefined)]))
}

/** A node's fields, under names of their own: two text blocks do not share inputs. */
function settingsOf(node, fields) {
    return fields.map((field) => ({
        setting: field.name,
        field: { ...field, name: `layout-${node.id}-${field.name}`, disabled: field.disabled || props.disabled },
        value: initialValue(field, node.settings[field.name]),
    }))
}

function typeOf(block) {
    return props.types.find((item) => item.type === block.type)
}

function typeLabel(block) {
    return typeOf(block)?.label || block.type
}

function move(items, index, step) {
    items.splice(index + step, 0, ...items.splice(index, 1))
}

/** Columns of a row take equal parts of the twelve. */
function share(columns) {
    for (const column of columns) {
        column.settings.span = Math.floor(GRID / columns.length)
    }
}

function newColumn() {
    return { id: newId('c'), settings: defaults(props.columnFields), blocks: [] }
}

function addSection() {
    change((next) => next.push({ id: newId('s'), settings: defaults(props.sectionFields), columns: [newColumn()] }))
}

function addColumn(s) {
    change((next) => {
        next[s].columns.push(newColumn())
        share(next[s].columns)
    })
}

function removeColumn(s, c) {
    change((next) => {
        next[s].columns.splice(c, 1)
        share(next[s].columns)
    })
}

function addBlock(s, c) {
    const column = sections.value[s].columns[c]
    const type = props.types.find((item) => item.type === chosen.value[column.id])
    if (!type) return
    const block = { id: newId('b'), type: type.type, settings: defaults(type.fields) }
    change((next) => next[s].columns[c].blocks.push(block))
    open.value[block.id] = true
    chosen.value[column.id] = ''
}

function setSection(s, setting, value) {
    change((next) => { next[s].settings[setting] = value })
}

function setColumn(s, c, setting, value) {
    change((next) => { next[s].columns[c].settings[setting] = value })
}

function setBlock(s, c, b, setting, value) {
    change((next) => { next[s].columns[c].blocks[b].settings[setting] = value })
}

const addButton = 'inline-flex items-center gap-1 px-2.5 py-1.5 text-sm font-medium text-accent-soft-fg bg-accent-soft rounded-md border border-accent-soft hover:border-accent disabled:opacity-50 disabled:cursor-not-allowed'
</script>

<template>
    <div class="layout-field" :data-layout="name" dusk="layout-editor">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
            <span v-if="label" class="block text-sm font-medium text-fg">
                {{ label }}
                <span v-if="required" class="text-danger">*</span>
            </span>
            <span v-if="multilingual && language" class="text-sm text-muted" dusk="layout-language">
                {{ t('fields.layout.language', { language: language.name }) }}
            </span>
        </div>

        <p v-if="error" class="mb-3 text-sm text-danger" role="alert" dusk="layout-error">{{ error }}</p>

        <ol v-if="sections.length" class="space-y-4 mb-3" :aria-label="t('fields.layout.sections')">
            <li v-for="(section, s) in sections" :key="section.id" class="rounded-md border border-field bg-surface-alt p-3" :dusk="`layout-section-${s}`">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-fg">{{ t('fields.layout.section', { section: s + 1 }) }}</p>
                    <LayoutControls
                        :target="t('fields.layout.section', { section: s + 1 })"
                        prefix="layout-section"
                        :position="`${s}`"
                        :first="s === 0"
                        :last="s === sections.length - 1"
                        :disabled="disabled"
                        @up="change((next) => move(next, s, -1))"
                        @down="change((next) => move(next, s, 1))"
                        @remove="change((next) => next.splice(s, 1))"
                    />
                </div>

                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="entry in settingsOf(section, sectionFields)"
                        :key="entry.field.name"
                        :dusk="`layout-section-setting-${s}-${entry.setting}`"
                    >
                        <FormField
                            :field="entry.field"
                            :model-value="entry.value"
                            :errors="noErrors"
                            @update:model-value="setSection(s, entry.setting, $event)"
                        />
                    </div>
                </div>

                <ol v-if="section.columns.length" class="mt-3 space-y-3">
                    <li v-for="(column, c) in section.columns" :key="column.id" class="rounded-md border border-line bg-surface p-3" :dusk="`layout-column-${s}-${c}`">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-fg">{{ t('fields.layout.column', { column: c + 1 }) }}</p>
                            <LayoutControls
                                :target="t('fields.layout.places.column', { section: s + 1, column: c + 1 })"
                                prefix="layout-column"
                                :position="`${s}-${c}`"
                                :first="c === 0"
                                :last="c === section.columns.length - 1"
                                :disabled="disabled"
                                @up="change((next) => move(next[s].columns, c, -1))"
                                @down="change((next) => move(next[s].columns, c, 1))"
                                @remove="removeColumn(s, c)"
                            />
                        </div>

                        <div class="mt-2 max-w-xs">
                            <div
                                v-for="entry in settingsOf(column, columnFields)"
                                :key="entry.field.name"
                                :dusk="`layout-column-setting-${s}-${c}-${entry.setting}`"
                            >
                                <FormField
                                    :field="entry.field"
                                    :model-value="entry.value"
                                    :errors="noErrors"
                                    @update:model-value="setColumn(s, c, entry.setting, $event)"
                                />
                            </div>
                        </div>

                        <ol v-if="column.blocks.length" class="mt-3 space-y-2">
                            <li v-for="(block, b) in column.blocks" :key="block.id" class="rounded-md border border-field bg-surface" :dusk="`layout-block-${s}-${c}-${b}`" :data-block-type="block.type">
                                <div class="flex items-center gap-2 px-2 py-1.5">
                                    <button
                                        type="button"
                                        class="flex-1 min-w-0 text-left"
                                        :aria-expanded="open[block.id] ? 'true' : 'false'"
                                        :dusk="`layout-block-toggle-${s}-${c}-${b}`"
                                        @click="open[block.id] = !open[block.id]"
                                    >
                                        <span class="font-medium text-fg">{{ typeLabel(block) }}</span>
                                        <span class="ml-2 text-sm text-muted underline">{{ t('fields.layout.settings') }}</span>
                                    </button>
                                    <LayoutControls
                                        :target="t('fields.layout.places.block', { section: s + 1, column: c + 1, block: b + 1, type: typeLabel(block) })"
                                        prefix="layout-block"
                                        :position="`${s}-${c}-${b}`"
                                        :first="b === 0"
                                        :last="b === column.blocks.length - 1"
                                        :disabled="disabled"
                                        @up="change((next) => move(next[s].columns[c].blocks, b, -1))"
                                        @down="change((next) => move(next[s].columns[c].blocks, b, 1))"
                                        @remove="change((next) => next[s].columns[c].blocks.splice(b, 1))"
                                    />
                                </div>
                                <p v-if="!typeOf(block)" class="px-2 pb-2 text-sm text-danger" :dusk="`layout-block-unknown-${s}-${c}-${b}`">
                                    {{ t('fields.layout.unknown', { type: block.type }) }}
                                </p>
                                <div v-else-if="open[block.id]" class="space-y-4 border-t border-line p-3" :dusk="`layout-block-settings-${s}-${c}-${b}`">
                                    <div
                                        v-for="entry in settingsOf(block, typeOf(block).fields)"
                                        :key="entry.field.name"
                                        :dusk="`layout-setting-${s}-${c}-${b}-${entry.setting}`"
                                    >
                                        <FormField
                                            :field="entry.field"
                                            :model-value="entry.value"
                                            :errors="noErrors"
                                            @update:model-value="setBlock(s, c, b, entry.setting, $event)"
                                        />
                                    </div>
                                </div>
                            </li>
                        </ol>
                        <p v-else class="mt-3 text-sm text-muted">{{ t('fields.layout.no_blocks') }}</p>

                        <div v-if="!disabled" class="mt-3 flex flex-wrap items-center gap-2">
                            <select
                                :value="chosen[column.id] || ''"
                                class="px-3 py-1.5 text-sm border border-field rounded-md bg-surface text-fg focus:ring-focus focus:border-focus"
                                :aria-label="t('fields.layout.add_block')"
                                :dusk="`layout-block-type-${s}-${c}`"
                                @change="chosen[column.id] = $event.target.value"
                            >
                                <option value="">{{ t('fields.layout.choose_block') }}</option>
                                <option v-for="type in types" :key="type.type" :value="type.type">{{ type.label }}</option>
                            </select>
                            <button type="button" :class="addButton" :disabled="!chosen[column.id] || column.blocks.length >= maxBlocks" :dusk="`layout-add-block-${s}-${c}`" @click="addBlock(s, c)">
                                {{ t('fields.layout.add_block') }}
                            </button>
                        </div>
                    </li>
                </ol>
                <p v-else class="mt-3 text-sm text-muted">{{ t('fields.layout.no_columns') }}</p>

                <button v-if="!disabled" type="button" class="mt-3" :class="addButton" :disabled="section.columns.length >= maxColumns" :dusk="`layout-add-column-${s}`" @click="addColumn(s)">
                    {{ t('fields.layout.add_column') }}
                </button>
            </li>
        </ol>
        <p v-else class="mb-3 text-sm text-muted" dusk="layout-empty">{{ t('fields.layout.empty') }}</p>

        <button v-if="!disabled" type="button" :class="addButton" :disabled="sections.length >= maxSections" dusk="layout-add-section" @click="addSection">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            {{ t('fields.layout.add_section') }}
        </button>

        <p v-if="hint" class="mt-2 text-sm text-muted">{{ hint }}</p>
    </div>
</template>
