<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, onMounted, provide, ref } from 'vue'
import TreeFieldNode from './TreeFieldNode.vue'
import { t } from '@atrium/Support/i18n'

/*
 * Items in a tree, ordered and nested by drag and drop (or by the arrow
 * buttons, which do the same from the keyboard). Each item has the fields
 * the server lists in `fields`; `children` holds the items below it.
 *
 * Every change works on a copy and emits the whole tree: the form keeps one
 * value, and the server receives exactly what is on screen.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: null },
    fields: { type: Array, default: () => [] },
    titleField: { type: String, default: 'title' },
    maxDepth: { type: Number, default: 0 },
    maxItems: { type: Number, default: 500 },
    addLabel: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

let lastKey = 0
const open = ref(new Set())
const dragging = ref(null)

const items = computed(() => Array.isArray(props.modelValue) ? props.modelValue : [])

function count(list) {
    return list.reduce((sum, item) => sum + 1 + count(item.children || []), 0)
}

const canAdd = computed(() => !props.disabled && count(items.value) < props.maxItems)

function copy(list) {
    return JSON.parse(JSON.stringify(list))
}

/** Keys for v-for and for drag and drop; the server ignores them. */
function keyed(list) {
    for (const item of list) {
        if (item._key === undefined) {
            item._key = `k${++lastKey}`
        }
        item.children = Array.isArray(item.children) ? item.children : []
        keyed(item.children)
    }
    return list
}

function change(mutate) {
    const tree = keyed(copy(items.value))
    mutate(tree)
    emit('update:modelValue', tree)
}

/** The item with this key: its list, its index and the item itself. */
function locate(tree, key, parent = null) {
    for (let index = 0; index < tree.length; index++) {
        if (tree[index]._key === key) {
            return { list: tree, index, item: tree[index], parent }
        }
        const found = locate(tree[index].children, key, tree[index])
        if (found) {
            return found
        }
    }
    return null
}

function depthOf(tree, key, depth = 1) {
    for (const item of tree) {
        if (item._key === key) return depth
        const found = depthOf(item.children, key, depth + 1)
        if (found) return found
    }
    return 0
}

function height(item) {
    return 1 + Math.max(0, ...(item.children || []).map(height))
}

function contains(item, key) {
    return item._key === key || (item.children || []).some((child) => contains(child, key))
}

function blank() {
    const item = { children: [] }
    for (const field of props.fields) {
        item[field.name] = field.default ?? (field.type === 'checkbox' ? false : '')
    }
    item._key = `k${++lastKey}`
    return item
}

function fits(depth, item) {
    return props.maxDepth === 0 || depth + height(item) - 1 <= props.maxDepth
}

const operations = {
    fields: computed(() => props.fields),
    titleField: computed(() => props.titleField),
    treeName: computed(() => props.name),
    disabled: computed(() => props.disabled),
    maxDepth: computed(() => props.maxDepth),
    canAdd,
    open,
    dragging,

    isOpen: (key) => open.value.has(key),
    toggle(key) {
        const next = new Set(open.value)
        next.has(key) ? next.delete(key) : next.add(key)
        open.value = next
    },
    set(key, field, value) {
        change((tree) => {
            const found = locate(tree, key)
            if (found) found.item[field] = value
        })
    },
    addChild(key) {
        const item = blank()
        change((tree) => {
            const found = locate(tree, key)
            if (found && fits(depthOf(tree, key) + 1, item)) found.item.children.push(item)
        })
        open.value = new Set([...open.value, item._key])
    },
    remove(key) {
        change((tree) => {
            const found = locate(tree, key)
            if (found) found.list.splice(found.index, 1)
        })
    },
    move(key, step) {
        change((tree) => {
            const found = locate(tree, key)
            const to = found ? found.index + step : -1
            if (found && to >= 0 && to < found.list.length) {
                found.list.splice(found.index, 1)
                found.list.splice(to, 0, found.item)
            }
        })
    },
    /** Under the item above, as its last child. */
    indent(key) {
        change((tree) => {
            const found = locate(tree, key)
            if (!found || found.index === 0) return
            const above = found.list[found.index - 1]
            if (!fits(depthOf(tree, key) + 1, found.item)) return
            found.list.splice(found.index, 1)
            above.children.push(found.item)
        })
    },
    /** Out of its parent, right after it. */
    outdent(key) {
        change((tree) => {
            const found = locate(tree, key)
            if (!found || !found.parent) return
            const parent = locate(tree, found.parent._key)
            found.list.splice(found.index, 1)
            parent.list.splice(parent.index + 1, 0, found.item)
        })
    },
    canIndent(key, index) {
        if (index === 0) return false
        const found = locate(items.value, key)
        return !!found && fits(depthOf(items.value, key) + 1, found.item)
    },
    canDrop(targetKey, position) {
        if (!dragging.value || dragging.value === targetKey) return false
        const dragged = locate(items.value, dragging.value)
        if (!dragged || contains(dragged.item, targetKey)) return false
        const depth = depthOf(items.value, targetKey) + (position === 'inside' ? 1 : 0)
        return fits(depth, dragged.item)
    },
    drop(targetKey, position) {
        if (!operations.canDrop(targetKey, position)) return
        const key = dragging.value
        change((tree) => {
            const dragged = locate(tree, key)
            dragged.list.splice(dragged.index, 1)
            const target = locate(tree, targetKey)
            if (position === 'inside') {
                target.item.children.push(dragged.item)
            } else {
                target.list.splice(target.index + (position === 'after' ? 1 : 0), 0, dragged.item)
            }
        })
        dragging.value = null
    },
}

provide('atriumTree', operations)

function add() {
    if (!canAdd.value) return
    const item = blank()
    change((tree) => tree.push(item))
    open.value = new Set([...open.value, item._key])
}

// Items from the server come without keys: give them keys once, so that
// dragging and the open/closed state follow the item, not its position.
onMounted(() => {
    const missing = (list) => list.some((item) => item._key === undefined || missing(item.children || []))
    if (missing(items.value)) {
        emit('update:modelValue', keyed(copy(items.value)))
    }
})
</script>

<template>
    <div class="tree-field" :data-tree="name">
        <label v-if="label" class="block text-sm font-medium text-fg mb-2">
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <ul v-if="items.length" class="space-y-2" role="tree" :aria-label="label">
            <TreeFieldNode
                v-for="(item, index) in items"
                :key="item._key ?? index"
                :item="item"
                :index="index"
                :siblings="items.length"
                :depth="1"
            />
        </ul>

        <p v-else class="text-muted text-sm py-2">
            {{ t('fields.tree.empty', { add: addLabel || t('fields.tree.add') }) }}
        </p>

        <button
            v-if="canAdd"
            type="button"
            data-action="tree-add"
            class="mt-3 inline-flex items-center px-3 py-2 text-sm font-medium text-accent bg-accent-soft hover:bg-accent-soft rounded-md border border-accent-soft transition-colors"
            @click="add"
        >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ addLabel || t('fields.tree.add') }}
        </button>

        <p v-if="hint" class="mt-1 text-sm text-muted">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-sm text-danger" data-tree-error>{{ error }}</p>
    </div>
</template>
