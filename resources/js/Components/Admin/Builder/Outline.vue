<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { onBeforeUnmount, onMounted, onUpdated, ref } from 'vue'
import LayoutControls from '../Fields/LayoutControls.vue'
import { BLOCKS, sortable } from './sortable'
import { SMALL_BUTTON } from './classes'
import { blocksOf, columnsOf, object, sectionsOf } from '../../../Support/layoutTree'
import { t } from '@atrium/Support/i18n'

/*
 * The page as a list: its sections, their columns and the blocks in them.
 * A node is picked here, moved with its arrow buttons and deleted; a block is
 * also dragged to any column of any section.
 */
const props = defineProps({
    tree: { type: [Object, Array, String], default: null },
    selected: { type: String, default: null },
    // The node the server found a problem in.
    failed: { type: String, default: null },
    // Ids drawn in the preview, or null where there is none.
    drawn: { type: Array, default: null },
    typeLabel: { type: Function, required: true },
    maxSections: { type: Number, required: true },
    maxColumns: { type: Number, required: true },
    maxBlocks: { type: Number, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'step', 'remove', 'move', 'add-section', 'add-column'])

const root = ref(null)
const sorters = new Map()

const room = (list) => list.querySelectorAll('[data-node-block]').length < props.maxBlocks

/** A column that appeared gets drag and drop; one that went loses it. */
function attach() {
    for (const [element, sorter] of sorters) {
        if (!element.isConnected || props.disabled) {
            sorter.destroy()
            sorters.delete(element)
        }
    }

    if (props.disabled) return

    for (const element of root.value.querySelectorAll('[data-column]')) {
        if (sorters.has(element)) continue

        sorters.set(element, sortable(element, {
            // A full column takes nothing from outside.
            group: { name: BLOCKS, put: (to) => room(to.el) },
            draggable: '[data-node-block]',
            onDrop: (event) => emit('move', {
                id: event.item.dataset.nodeBlock,
                column: event.to.dataset.column,
                index: event.newDraggableIndex,
            }),
        }))
    }
}

onMounted(attach)
onUpdated(attach)
onBeforeUnmount(() => sorters.forEach((sorter) => sorter.destroy()))

const span = (column) => object(column.settings).span ?? 12
const missing = (block) => props.drawn !== null && !props.drawn.includes(block.id)
const nameButton = 'flex min-w-0 flex-1 items-center gap-1.5 rounded px-1 py-0.5 text-left text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus'
// Selected: a thick border and a mark, not a colour alone.
const frame = (id, plain) => (props.selected === id ? 'border-2 border-accent' : plain)
</script>

<template>
    <section ref="root" :aria-label="t('builder.structure')" dusk="builder-outline">
        <ol v-if="sectionsOf(tree).length" class="space-y-3" :aria-label="t('fields.layout.sections')">
            <li
                v-for="(section, s) in sectionsOf(tree)"
                :key="section.id"
                class="rounded-md bg-surface-alt p-2"
                :class="frame(section.id, 'border border-field')"
                :dusk="`layout-section-${s}`"
                :data-node="section.id"
            >
                <div class="flex items-center gap-2">
                    <button type="button" :class="[nameButton, 'font-semibold']" :aria-current="selected === section.id ? 'true' : null" :data-control="`select:${section.id}`" :dusk="`layout-select-section-${s}`" @click="emit('select', section.id)">
                        <span v-if="selected === section.id" aria-hidden="true">▶</span>
                        <span class="truncate">{{ t('fields.layout.section', { section: s + 1 }) }}</span>
                        <span v-if="failed === section.id" class="text-sm font-semibold text-danger">⚠ {{ t('builder.has_error') }}</span>
                    </button>
                    <LayoutControls
                        :target="t('fields.layout.section', { section: s + 1 })"
                        prefix="layout-section"
                        :position="`${s}`"
                        :node="section.id"
                        :first="s === 0"
                        :last="s === sectionsOf(tree).length - 1"
                        :disabled="disabled"
                        @up="emit('step', { id: section.id, by: -1 })"
                        @down="emit('step', { id: section.id, by: 1 })"
                        @remove="emit('remove', section.id)"
                    />
                </div>

                <ol v-if="columnsOf(section).length" class="mt-2 space-y-2">
                    <li
                        v-for="(column, c) in columnsOf(section)"
                        :key="column.id"
                        class="rounded-md bg-surface p-2"
                        :class="frame(column.id, 'border border-line')"
                        :dusk="`layout-column-${s}-${c}`"
                        :data-node="column.id"
                    >
                        <div class="flex items-center gap-2">
                            <button type="button" :class="[nameButton, 'text-sm font-medium']" :aria-current="selected === column.id ? 'true' : null" :data-control="`select:${column.id}`" :dusk="`layout-select-column-${s}-${c}`" @click="emit('select', column.id)">
                                <span v-if="selected === column.id" aria-hidden="true">▶</span>
                                <span class="truncate">{{ t('fields.layout.column', { column: c + 1 }) }}</span>
                                <span class="text-xs font-normal text-muted" :title="t('fields.layout.span')">{{ span(column) }}/12</span>
                                <span v-if="failed === column.id" class="text-sm font-semibold text-danger">⚠ {{ t('builder.has_error') }}</span>
                            </button>
                            <LayoutControls
                                :target="t('fields.layout.places.column', { section: s + 1, column: c + 1 })"
                                prefix="layout-column"
                                :position="`${s}-${c}`"
                                :node="column.id"
                                :first="c === 0"
                                :last="c === columnsOf(section).length - 1"
                                :disabled="disabled"
                                @up="emit('step', { id: column.id, by: -1 })"
                                @down="emit('step', { id: column.id, by: 1 })"
                                @remove="emit('remove', column.id)"
                            />
                        </div>

                        <!-- The drop target stays when it is empty: a dashed box that says what goes there. -->
                        <ol
                            :data-column="column.id"
                            class="mt-2 min-h-10 space-y-1.5"
                            :aria-label="t('fields.layout.places.column', { section: s + 1, column: c + 1 })"
                            :dusk="`layout-list-${s}-${c}`"
                        >
                            <li
                                v-for="(block, b) in blocksOf(column)"
                                :key="block.id"
                                class="rounded-md bg-surface px-1.5 py-1"
                                :class="[frame(block.id, 'border border-field'), disabled ? '' : 'cursor-grab']"
                                :data-node-block="block.id"
                                :data-node="block.id"
                                :data-block-type="block.type"
                                :dusk="`layout-block-${s}-${c}-${b}`"
                            >
                                <div class="flex items-center gap-1.5">
                                    <svg v-if="!disabled" class="h-4 w-4 shrink-0 text-muted" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 7a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 8.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM18 5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-1.5 8.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM18 19a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" /></svg>
                                    <button type="button" :class="[nameButton, 'text-sm font-medium']" :aria-current="selected === block.id ? 'true' : null" :data-control="`select:${block.id}`" :dusk="`layout-block-toggle-${s}-${c}-${b}`" @click="emit('select', block.id)">
                                        <span v-if="selected === block.id" aria-hidden="true">▶</span>
                                        <span class="truncate">{{ typeLabel(block) }}</span>
                                    </button>
                                    <LayoutControls
                                        :target="t('fields.layout.places.block', { section: s + 1, column: c + 1, block: b + 1, type: typeLabel(block) })"
                                        prefix="layout-block"
                                        :position="`${s}-${c}-${b}`"
                                        :node="block.id"
                                        :first="b === 0"
                                        :last="b === blocksOf(column).length - 1"
                                        :disabled="disabled"
                                        @up="emit('step', { id: block.id, by: -1 })"
                                        @down="emit('step', { id: block.id, by: 1 })"
                                        @remove="emit('remove', block.id)"
                                    />
                                </div>
                                <p v-if="failed === block.id" class="mt-1 text-sm font-semibold text-danger" :dusk="`layout-block-failed-${s}-${c}-${b}`">⚠ {{ t('builder.has_error') }}</p>
                                <p v-if="missing(block)" class="mt-1 text-xs text-muted" :dusk="`layout-block-missing-${s}-${c}-${b}`">○ {{ t('builder.not_drawn') }}</p>
                            </li>
                            <li v-if="!blocksOf(column).length" class="flex min-h-10 items-center justify-center rounded-md border-2 border-dashed border-field px-2 text-center text-xs text-muted" :dusk="`layout-drop-${s}-${c}`">
                                {{ disabled ? t('fields.layout.no_blocks') : t('builder.drop_here') }}
                            </li>
                        </ol>
                    </li>
                </ol>
                <p v-else class="mt-2 text-sm text-muted">{{ t('fields.layout.no_columns') }}</p>

                <button v-if="!disabled" type="button" :class="[SMALL_BUTTON, 'mt-2']" :disabled="columnsOf(section).length >= maxColumns" :dusk="`layout-add-column-${s}`" @click="emit('add-column', section.id)">
                    <span aria-hidden="true">+</span> {{ t('fields.layout.add_column') }}
                </button>
            </li>
        </ol>
        <p v-else class="text-sm text-muted" dusk="layout-empty">{{ t('fields.layout.empty') }}</p>

        <button v-if="!disabled" type="button" :class="[SMALL_BUTTON, 'mt-3']" :disabled="sectionsOf(tree).length >= maxSections" data-control="add-section" dusk="layout-add-section" @click="emit('add-section')">
            <span aria-hidden="true">+</span> {{ t('fields.layout.add_section') }}
        </button>
    </section>
</template>
