<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, inject, ref } from 'vue'
import AdminFieldRenderer from './AdminFieldRenderer.vue'
import { t } from '@atrium/Support/i18n'

defineOptions({ name: 'TreeFieldNode' })

const props = defineProps({
    item: { type: Object, required: true },
    index: { type: Number, required: true },
    siblings: { type: Number, required: true },
    depth: { type: Number, required: true },
})

const tree = inject('atriumTree')
const position = ref(null)

const key = computed(() => props.item._key)
const isOpen = computed(() => tree.isOpen(key.value))
const children = computed(() => props.item.children || [])
const canNest = computed(() => tree.maxDepth.value === 0 || props.depth < tree.maxDepth.value)

const title = computed(() => {
    const value = props.item[tree.titleField.value]
    return typeof value === 'string' && value.trim() !== '' ? value : t('fields.tree.untitled')
})

/** What the link points at, in a few words, next to the title. */
const summary = computed(() => {
    for (const field of tree.fields.value) {
        const value = props.item[field.name]
        if (field.type === 'link' && value && typeof value === 'object') {
            return value.type === 'url' ? value.url : (value.label || '')
        }
    }
    return ''
})

const inactive = computed(() => props.item.active === false)

function subField(field) {
    // Unique per item: ids, labels and radio groups must not collide.
    return { ...field, name: `${tree.treeName.value}.${key.value}.${field.name}` }
}

function where(event) {
    const box = event.currentTarget.getBoundingClientRect()
    const y = (event.clientY - box.top) / Math.max(box.height, 1)
    return y < 0.3 ? 'before' : (y > 0.7 || !canNest.value ? 'after' : 'inside')
}

function onDragStart(event) {
    if (tree.disabled.value) return
    tree.dragging.value = key.value
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', key.value)
}

function onDragOver(event) {
    const spot = where(event)
    if (tree.canDrop(key.value, spot)) {
        event.preventDefault()
        event.dataTransfer.dropEffect = 'move'
        position.value = spot
    } else {
        position.value = null
    }
}

function onDrop(event) {
    event.preventDefault()
    const spot = position.value
    position.value = null
    if (spot) tree.drop(key.value, spot)
}

function onDragEnd() {
    tree.dragging.value = null
    position.value = null
}

const button = 'p-1 rounded text-muted hover:text-fg hover:bg-hover disabled:opacity-30 disabled:cursor-not-allowed'
</script>

<template>
    <li
        role="treeitem"
        :aria-expanded="children.length ? 'true' : undefined"
        :aria-level="depth"
        :data-tree-item="index"
        :data-tree-key="key"
        :data-tree-depth="depth"
    >
        <div
            class="flex items-center gap-2 rounded-md border bg-surface px-2 py-1.5"
            :class="[
                position === 'inside' ? 'border-accent ring-2 ring-focus' : 'border-line',
                position === 'before' ? 'border-t-4 border-t-accent' : '',
                position === 'after' ? 'border-b-4 border-b-accent' : '',
                tree.dragging.value === key ? 'opacity-50' : '',
            ]"
            :draggable="!tree.disabled.value"
            data-tree-row
            @dragstart="onDragStart"
            @dragover="onDragOver"
            @dragleave="position = null"
            @drop="onDrop"
            @dragend="onDragEnd"
        >
            <span class="cursor-grab select-none text-muted" :title="t('fields.tree.drag')" aria-hidden="true">⋮⋮</span>

            <button
                type="button"
                class="flex-1 min-w-0 text-left"
                data-action="tree-toggle"
                :aria-expanded="isOpen ? 'true' : 'false'"
                @click="tree.toggle(key)"
            >
                <span class="font-medium text-fg" :class="{ 'line-through text-muted': inactive }" data-tree-title>{{ title }}</span>
                <span v-if="summary" class="ml-2 truncate text-sm text-muted">{{ summary }}</span>
            </button>

            <div class="flex items-center gap-0.5 shrink-0">
                <button type="button" :class="button" data-action="tree-up" :disabled="tree.disabled.value || index === 0" :title="t('fields.tree.move_up')" :aria-label="t('fields.tree.move_up')" @click="tree.move(key, -1)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                </button>
                <button type="button" :class="button" data-action="tree-down" :disabled="tree.disabled.value || index === siblings - 1" :title="t('fields.tree.move_down')" :aria-label="t('fields.tree.move_down')" @click="tree.move(key, 1)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <button type="button" :class="button" data-action="tree-outdent" :disabled="tree.disabled.value || depth === 1" :title="t('fields.tree.outdent')" :aria-label="t('fields.tree.outdent')" @click="tree.outdent(key)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button type="button" :class="button" data-action="tree-indent" :disabled="tree.disabled.value || !tree.canIndent(key, index)" :title="t('fields.tree.indent')" :aria-label="t('fields.tree.indent')" @click="tree.indent(key)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
                <button type="button" :class="button" data-action="tree-add-child" :disabled="tree.disabled.value || !canNest || !tree.canAdd.value" :title="t('fields.tree.add_child')" :aria-label="t('fields.tree.add_child')" @click="tree.addChild(key)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </button>
                <button type="button" class="p-1 rounded text-danger hover:text-danger-soft-fg hover:bg-danger-soft disabled:opacity-30" data-action="tree-remove" :disabled="tree.disabled.value" :title="t('common.delete')" :aria-label="t('common.delete')" @click="tree.remove(key)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <div v-if="isOpen" class="mt-1 mb-2 ml-6 space-y-4 rounded-md border border-line bg-surface-alt p-3" data-tree-body>
            <AdminFieldRenderer
                v-for="field in tree.fields.value"
                :key="field.name"
                :field="subField(field)"
                :model-value="item[field.name]"
                @update:model-value="tree.set(key, field.name, $event)"
            />
        </div>

        <ul v-if="children.length" class="mt-2 ml-6 space-y-2 border-l border-line pl-3" role="group">
            <TreeFieldNode
                v-for="(child, childIndex) in children"
                :key="child._key ?? childIndex"
                :item="child"
                :index="childIndex"
                :siblings="children.length"
                :depth="depth + 1"
            />
        </ul>
    </li>
</template>
