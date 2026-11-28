<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, nextTick, ref, useSlots } from 'vue'
import Palette from './Palette.vue'
import Outline from './Outline.vue'
import Settings from './Settings.vue'
import * as layout from '../../../Support/layoutTree'
import { initialValue } from '../../../Support/contentLocale'
import { t } from '@atrium/Support/i18n'

/*
 * The block editor: the palette and the structure of the page, the settings of
 * what is selected, and, when given one, a preview between them. Every change
 * emits the whole tree, and nothing is emitted until something changes.
 *
 * Whatever is done with the mouse has a button: adding, ordering, moving to
 * another column and deleting. What happened is said to screen readers, and
 * the keyboard stays on the node that moved.
 */
const props = defineProps({
    modelValue: { type: [Object, Array, String], default: null },
    types: { type: Array, default: () => [] },
    sectionFields: { type: Array, default: () => [] },
    columnFields: { type: Array, default: () => [] },
    maxSections: { type: Number, default: 100 },
    maxColumns: { type: Number, default: 12 },
    maxBlocks: { type: Number, default: 100 },
    version: { type: Number, default: 1 },
    disabled: { type: Boolean, default: false },
    selected: { type: String, default: null },
    // The node the server refused, and why.
    failed: { type: String, default: null },
    problem: { type: String, default: null },
    drawn: { type: Array, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:selected'])

const slots = useSlots()
const root = ref(null)
const said = ref('')
const tree = computed(() => props.modelValue)

function typeLabel(block) {
    return props.types.find((item) => item.type === block.type)?.label || block.type
}

/** "Section 1, column 2, block 1 (Text)" of a node, in the tree given. */
function placeOf(inTree, id) {
    const at = layout.locate(inTree, id)
    if (!at) return ''

    const place = { section: at.s + 1, column: (at.c ?? 0) + 1, block: (at.b ?? 0) + 1 }

    return t(`fields.layout.places.${at.kind}`, at.kind === 'block' ? { ...place, type: typeLabel(at.node) } : place)
}

function announce(key, place) {
    // Emptied first: the same words twice in a row are said twice.
    said.value = ''
    nextTick(() => { said.value = t(`builder.said.${key}`, { place }) })
}

/** Puts the keyboard on the first of these controls that can take it. */
function focus(...controls) {
    nextTick(() => {
        for (const control of controls) {
            const element = root.value?.querySelector(`[data-control="${control}"]:not([disabled])`)

            if (element) {
                element.focus()
                return
            }
        }
    })
}

const select = (id) => emit('update:selected', id)

/** The settings a new node starts with: those its fields have a default for. */
function defaults(fields) {
    return Object.fromEntries(fields
        .filter((field) => field.default !== null && field.default !== undefined)
        .map((field) => [field.name, initialValue(field, undefined)]))
}

const newColumn = () => ({ id: layout.newId('c'), settings: defaults(props.columnFields), blocks: [] })
const newSection = () => ({ id: layout.newId('s'), settings: defaults(props.sectionFields), columns: [newColumn()] })

function newBlock(key) {
    const type = props.types.find((item) => item.type === key)

    return { id: layout.newId('b'), type: type.type, settings: defaults(type.fields) }
}

const columnIsFull = (columnId) => layout.blocksOf(layout.locate(tree.value, columnId)?.column).length >= props.maxBlocks

// Whether a pressed palette button has nowhere to put its block.
const full = computed(() => {
    const at = layout.target(tree.value, props.selected)

    if (at) return columnIsFull(at.columnId)

    return !layout.locate(tree.value, props.selected) && layout.sectionsOf(tree.value).length >= props.maxSections
})

function change(next, key, id) {
    emit('update:modelValue', next)
    announce(key, placeOf(next, id))
}

function addSection() {
    const section = newSection()
    change(layout.addSection(tree.value, props.version, section), 'added', section.id)
    select(section.id)
}

function addColumn(sectionId) {
    const column = newColumn()
    change(layout.addColumn(tree.value, props.version, sectionId, column), 'added', column.id)
    select(column.id)
}

/** A palette button: after what is selected, in a section of its own when the page has no column. */
function addBlock(key) {
    const block = newBlock(key)
    let next = tree.value
    let at = layout.target(next, props.selected)

    if (!at) {
        const found = props.selected ? layout.locate(next, props.selected) : null
        const column = newColumn()
        next = found
            ? layout.addColumn(next, props.version, found.section.id, column)
            : layout.addSection(next, props.version, { id: layout.newId('s'), settings: defaults(props.sectionFields), columns: [column] })
        at = { columnId: column.id, index: 0 }
    }

    change(layout.addBlock(next, props.version, at.columnId, block, at.index), 'added', block.id)
    select(block.id)
}

function dropBlock({ type, column, index }) {
    const block = newBlock(type)
    change(layout.addBlock(tree.value, props.version, column, block, index), 'added', block.id)
    select(block.id)
}

function moveBlock({ id, column, index = null }) {
    change(layout.moveBlock(tree.value, props.version, id, column, index), 'moved', id)
    select(id)
    focus(`select:${id}`)
}

function stepNode({ id, by }) {
    change(layout.step(tree.value, props.version, id, by), 'moved', id)
    const [one, other] = by < 0 ? ['up', 'down'] : ['down', 'up']
    focus(`${one}:${id}`, `${other}:${id}`)
}

function removeNode(id) {
    const at = layout.locate(tree.value, id)
    const around = at.kind === 'section'
        ? layout.sectionsOf(tree.value)
        : at.kind === 'column' ? layout.columnsOf(at.section) : layout.blocksOf(at.column)
    const index = around.findIndex((node) => node.id === id)
    const neighbour = around[index + 1] ?? around[index - 1]
    const parent = at.kind === 'column' ? at.section.id : at.column?.id
    const place = placeOf(tree.value, id)
    const next = layout.remove(tree.value, props.version, id)

    emit('update:modelValue', next)
    announce('removed', place)

    if (props.selected && !layout.locate(next, props.selected)) {
        select(null)
    }

    focus(
        ...(neighbour ? [`select:${neighbour.id}`] : []),
        ...(parent ? [`select:${parent}`] : []),
        'add-section',
    )
}

function setSetting({ id, name, value }) {
    emit('update:modelValue', layout.setSetting(tree.value, props.version, id, name, value))
}
</script>

<template>
    <div
        ref="root"
        class="grid items-start gap-4"
        :class="slots.preview ? 'lg:grid-cols-[17rem_minmax(0,1fr)] xl:grid-cols-[17rem_minmax(0,1fr)_19rem]' : 'lg:grid-cols-2'"
    >
        <p class="sr-only" role="status" aria-live="polite" dusk="builder-announcement">{{ said }}</p>

        <div class="min-w-0 space-y-4" :class="slots.preview ? 'lg:col-start-1 lg:row-start-1' : ''">
            <Palette v-if="!disabled" :types="types" :full="full" @add="addBlock" @drop="dropBlock" />
            <Outline
                :tree="tree"
                :selected="selected"
                :failed="failed"
                :drawn="drawn"
                :type-label="typeLabel"
                :max-sections="maxSections"
                :max-columns="maxColumns"
                :max-blocks="maxBlocks"
                :disabled="disabled"
                @select="select"
                @step="stepNode"
                @remove="removeNode"
                @move="moveBlock"
                @add-section="addSection"
                @add-column="addColumn"
            />
        </div>

        <div v-if="slots.preview" class="min-w-0 lg:sticky lg:top-20 lg:col-start-2 lg:row-start-1 lg:row-span-2">
            <slot name="preview" :type-label="typeLabel" :place-of="placeOf" />
        </div>

        <div class="min-w-0" :class="slots.preview ? 'lg:col-start-1 lg:row-start-2 xl:col-start-3 xl:row-start-1' : ''">
            <Settings
                :tree="tree"
                :selected="selected"
                :types="types"
                :section-fields="sectionFields"
                :column-fields="columnFields"
                :type-label="typeLabel"
                :max-blocks="maxBlocks"
                :problem="selected && selected === failed ? problem : null"
                :disabled="disabled"
                @set="setSetting"
                @move="moveBlock"
            />
        </div>
    </div>
</template>

<style>
/* Where the dragged block will land: a dashed frame, not a colour alone. */
.builder-ghost {
    opacity: 0.6;
    outline: 2px dashed currentColor;
}

.builder-drag {
    cursor: grabbing;
    box-shadow: 0 6px 16px rgb(0 0 0 / 0.25);
}
</style>
