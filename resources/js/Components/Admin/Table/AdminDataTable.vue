<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminPagination from './AdminPagination.vue'

const props = defineProps({
    columns: {
        type: Array,
        required: true
    },
    items: {
        type: Object, // Laravel paginator
        required: true
    },
    sortBy: {
        type: String,
        default: ''
    },
    sortDirection: {
        type: String,
        default: 'asc'
    },
    loading: Boolean,
    // Without the right to delete there is no delete button.
    canDelete: {
        type: Boolean,
        default: true
    },
    // [{ type, name, label, new_tab, href(item) }]: record buttons shown
    // before the edit button. A create form or a link is followed; a server
    // action (type "record") is handed to the page as an `action` event.
    rowActions: {
        type: Array,
        default: () => []
    },
    // Rows get checkboxes when the list has bulk actions; v-model:selected
    // holds the ids of the checked rows.
    selectable: Boolean,
    selected: {
        type: Array,
        default: () => []
    },
})

const emit = defineEmits(['sort', 'edit', 'delete', 'action', 'update:selected'])

const pageIds = computed(() => (props.items.data || []).map((item) => item.id))
const allSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => props.selected.includes(id)))
// Some but not all: the header box shows a dash, not a tick.
const someSelected = computed(() => !allSelected.value && pageIds.value.some((id) => props.selected.includes(id)))

const toggleAll = () => {
    emit('update:selected', allSelected.value
        ? props.selected.filter((id) => !pageIds.value.includes(id))
        : [...new Set([...props.selected, ...pageIds.value])])
}

const toggleRow = (id) => {
    emit('update:selected', props.selected.includes(id) ? props.selected.filter((other) => other !== id) : [...props.selected, id])
}

// What a screen reader says for a row's box: the first text column, as the
// row reads, or its number.
const rowTitle = (item) => {
    const column = props.columns.find((candidate) => !['image', 'boolean'].includes(candidate.type))
    const value = column ? item[column.name] : null

    return value === null || value === undefined || value === '' ? `#${item.id}` : String(value)
}

const handleSort = (column) => {
    if (!column.sortable) return

    const direction = props.sortBy === column.name && props.sortDirection === 'asc' ? 'desc' : 'asc'
    emit('sort', { column: column.name, direction })
}

// Gallery columns hold a JSON array. Binding that straight to :src yields
// src="a.jpg,b.jpg" and a broken image, so show the first entry.
const imageSrc = (value) => {
    const first = Array.isArray(value) ? (value.length ? value[0] : null) : value
    if (!first) return null
    // Gallery entries are path strings; older rows may hold the whole upload
    // response object, which would otherwise render as src="[object Object]".
    const raw = typeof first === 'object' ? (first.url ?? first.path ?? null) : first
    if (typeof raw !== 'string' || raw === '') return null
    // A path on the public disk (demo/laptop.svg) is stored without /storage/,
    // the way the form and the site read it; as it is, it resolves against
    // /admin/... and the picture is broken.
    if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('/')) return raw
    return '/storage/' + raw
}

const getSortIcon = (column) => {
    if (props.sortBy !== column.name) return null
    return props.sortDirection === 'asc' ? 'up' : 'down'
}

// Link column: the address lives in a sibling column of the same row (for a
// form submission, the page it was sent from). Rows without an address stay
// plain text, otherwise old rows would turn into links to nowhere.
const cellLink = (item, column) => {
    if (!column.urlFrom) return null

    const url = item[column.urlFrom]
    const text = item[column.name]

    // Without text the link would be empty and unclickable.
    if (text === null || text === undefined || text === '') return null

    return typeof url === 'string' && url !== '' ? url : null
}

// Long values are truncated so one row does not stretch the table across two
// screens; the full value is shown as a tooltip.
const cellTitle = (item, column) => {
    if (column.type === 'image' || column.type === 'boolean') return null

    const value = item[column.name]

    return value === null || value === undefined || value === '' ? null : String(value)
}
</script>

<template>
    <!-- isolate keeps the pinned actions column (z-10) inside the card, under the header bar. -->
    <div class="isolate bg-surface rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <!--
                border-separate is required: Tailwind collapses table borders
                by default, and with collapsed borders Chrome does not keep
                position:sticky on cells — the actions column would scroll off
                the edge with the rest.
            -->
            <table class="min-w-full border-separate border-spacing-0">
                <thead class="bg-surface-alt">
                    <tr>
                        <th v-if="selectable" scope="col" class="w-10 border-b border-line px-3 py-2">
                            <input
                                type="checkbox"
                                data-select="page"
                                class="h-4 w-4 rounded border-field text-accent focus:ring-2 focus:ring-focus"
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                :disabled="!pageIds.length"
                                :aria-label="$t('actions.bulk.select_page')"
                                :title="$t('actions.bulk.select_page')"
                                @change="toggleAll"
                            />
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.name"
                            :data-column="column.name"
                            :data-sortable="column.sortable ? 'true' : 'false'"
                            class="border-b border-line px-3 py-2 text-left text-xs font-medium text-muted uppercase tracking-wider whitespace-nowrap"
                            :class="{ 'cursor-pointer hover:bg-hover': column.sortable }"
                            :style="column.width ? { width: column.width } : {}"
                            @click="handleSort(column)"
                        >
                            <div class="flex items-center space-x-1">
                                <span>{{ column.label }}</span>
                                <template v-if="column.sortable">
                                    <svg v-if="getSortIcon(column) === 'up'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                    <svg v-else-if="getSortIcon(column) === 'down'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </template>
                            </div>
                        </th>
                        <!--
                            Actions are pinned to the right edge: a list can
                            have many columns (phone, dates, service…), and
                            without this the edit button slid off the screen.
                            In Bitrix the edit buttons are always visible.
                        -->
                        <th class="sticky right-0 z-10 border-b border-l border-line bg-surface-alt px-3 py-2 text-right text-xs font-medium text-muted uppercase tracking-wider">
                            {{ $t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface">
                    <tr v-if="loading">
                        <td :colspan="columns.length + (selectable ? 2 : 1)" class="px-6 py-12 text-center">
                            <svg class="animate-spin h-8 w-8 text-accent mx-auto" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </td>
                    </tr>
                    <tr v-else-if="!items.data?.length">
                        <td :colspan="columns.length + (selectable ? 2 : 1)" class="px-6 py-12 text-center text-muted">
                            {{ $t('resource.table.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="item in items.data"
                        :key="item.id"
                        :data-row-id="item.id"
                        :data-selected="selected.includes(item.id) ? 'true' : null"
                        class="hover:bg-hover"
                        :class="selected.includes(item.id) ? 'bg-accent-soft' : 'bg-surface'"
                    >
                        <td v-if="selectable" class="w-10 border-b border-line px-3 py-2">
                            <input
                                type="checkbox"
                                data-select="row"
                                class="h-4 w-4 rounded border-field text-accent focus:ring-2 focus:ring-focus"
                                :checked="selected.includes(item.id)"
                                :aria-label="$t('actions.bulk.select_row', { title: rowTitle(item) })"
                                @change="toggleRow(item.id)"
                            />
                        </td>
                        <td
                            v-for="column in columns"
                            :key="column.name"
                            class="border-b border-line px-3 py-2 whitespace-nowrap text-sm max-w-xs truncate"
                            :title="cellTitle(item, column)"
                        >
                            <slot :name="`cell-${column.name}`" :item="item" :value="item[column.name]">
                                <!-- Boolean column -->
                                <template v-if="column.type === 'boolean'">
                                    <!-- On and off differ by shape too: a filled dot or a ring. -->
                                    <span v-if="item[column.name]" data-badge="on" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border border-badge-line text-xs font-semibold bg-accent-soft text-accent-soft-fg">
                                        <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true" />
                                        {{ column.trueLabel || $t('common.yes') }}
                                    </span>
                                    <span v-else data-badge="off" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border border-badge-line text-xs font-semibold bg-off text-off-fg">
                                        <span class="h-2 w-2 rounded-full border-2 border-current" aria-hidden="true" />
                                        {{ column.falseLabel || $t('common.no') }}
                                    </span>
                                </template>
                                <!-- Image column -->
                                <template v-else-if="column.type === 'image'">
                                    <img
                                        v-if="imageSrc(item[column.name])"
                                        :src="imageSrc(item[column.name])"
                                        :alt="column.label"
                                        class="h-10 w-10 rounded object-cover"
                                    />
                                    <span v-else class="text-muted">—</span>
                                </template>
                                <!-- Date column -->
                                <template v-else-if="column.type === 'date'">
                                    {{ item[column.name] ? new Date(item[column.name]).toLocaleDateString('ru-RU') : '—' }}
                                </template>
                                <!-- Default text -->
                                <template v-else>
                                    <a
                                        v-if="cellLink(item, column)"
                                        :href="cellLink(item, column)"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-accent hover:text-accent-hover hover:underline"
                                    >{{ item[column.name] }}</a>
                                    <span v-else class="text-fg">{{ item[column.name] ?? '—' }}</span>
                                </template>
                            </slot>
                        </td>
                        <td class="sticky right-0 z-10 border-b border-l border-line bg-inherit px-3 py-2 whitespace-nowrap text-right text-sm font-medium">
                            <slot name="actions" :item="item">
                                <template v-for="action in rowActions" :key="action.name">
                                    <button
                                        v-if="action.type === 'record'"
                                        type="button"
                                        :data-action="action.name"
                                        class="mr-1 inline-flex items-center rounded px-2 py-1 text-xs text-accent hover:bg-accent-soft hover:text-accent-hover focus:outline-none focus:ring-2 focus:ring-focus"
                                        @click="$emit('action', { action, item })"
                                    >
                                        <svg class="mr-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        {{ action.label }}
                                    </button>
                                    <a
                                        v-else-if="action.type === 'link' && action.href(item)"
                                        :href="action.href(item)"
                                        :target="action.new_tab ? '_blank' : null"
                                        :rel="action.new_tab ? 'noopener' : null"
                                        :data-action="action.name"
                                        class="mr-1 inline-flex items-center rounded px-2 py-1 text-xs text-accent hover:bg-accent-soft hover:text-accent-hover focus:outline-none focus:ring-2 focus:ring-focus"
                                    >
                                        <svg class="mr-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        {{ action.label }}
                                        <span v-if="action.new_tab" class="sr-only">{{ $t('actions.new_tab') }}</span>
                                    </a>
                                    <Link
                                        v-else-if="action.type === 'create'"
                                        :href="action.href(item)"
                                        :data-action="action.name"
                                        class="mr-1 inline-flex items-center rounded px-2 py-1 text-xs text-accent hover:bg-accent-soft hover:text-accent-hover"
                                    >
                                        <svg class="mr-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        {{ action.label }}
                                    </Link>
                                </template>
                                <button
                                    data-action="edit"
                                    @click="$emit('edit', item)"
                                    class="inline-flex items-center rounded p-1.5 text-accent hover:bg-accent-soft hover:text-accent-hover"
                                    :title="$t('common.edit')"
                                    :aria-label="$t('common.edit')"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="canDelete"
                                    data-action="delete"
                                    @click="$emit('delete', item)"
                                    class="ml-1 inline-flex items-center rounded p-1.5 text-danger hover:bg-danger-soft hover:text-danger-soft-fg"
                                    :title="$t('common.delete')"
                                    :aria-label="$t('common.delete')"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <AdminPagination v-if="items.total > items.per_page" :paginator="items" />
    </div>
</template>
