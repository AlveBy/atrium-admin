<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, ref, watch } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminDataTable from '../../../Components/Admin/Table/AdminDataTable.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'
import { recordActionHref, runRecordAction } from '../../../Support/recordActions'
import { tc } from '../../../Support/i18n'

const props = defineProps({
    routeName: String,
    createLabel: String,
    items: Object,
    columns: Array,
    filters: Object,
    title: String,
    // view / create / update / delete — what the administrator's roles allow here.
    can: { type: Object, default: () => ({}) },
    // Buttons on a record: a filled create form, a link, a server action.
    actions: { type: Array, default: () => [] },
    // Where the link buttons of each row lead: id => name => address.
    actionUrls: { type: [Object, Array], default: () => ({}) },
    // Buttons over the checked rows ("Delete" and the module's own).
    bulkActions: { type: Array, default: () => [] },
    // import / export — whether the module has them and the roles allow them.
    transfer: { type: Object, default: () => ({}) },
    // Buttons above the list added by packages ("Fields" of custom fields).
    links: { type: Array, default: () => [] },
    // Filters drawn above the list, the module's and other modules': name, type, label, options.
    filterControls: { type: Array, default: () => [] },
})

// Filters from the address (?module=…) that the module declared: kept on
// search and sort, and passed on to the create form.
const listFilters = computed(() => Object.fromEntries(
    Object.entries(props.filters || {}).filter(([key]) => !['search', 'sort', 'direction'].includes(key))
))

// What is chosen in each drawn filter, '' for any. A change reloads the
// list from its first page, keeping the search and the sort.
const chosen = ref(Object.fromEntries(props.filterControls.map((filter) => [filter.name, props.filters?.[filter.name] ?? ''])))
const activeFilters = computed(() => props.filterControls.filter((filter) => chosen.value[filter.name] !== '').length)

const applyFilters = () => {
    const query = { ...listFilters.value, search: search.value, sort: props.filters.sort, direction: props.filters.direction }

    for (const filter of props.filterControls) {
        if (chosen.value[filter.name] === '') {
            delete query[filter.name]
        } else {
            query[filter.name] = chosen.value[filter.name]
        }
    }

    router.get(route(`${props.routeName}.index`), query, { preserveState: true, preserveScroll: true })
}

const resetFilters = () => {
    for (const filter of props.filterControls) {
        chosen.value[filter.name] = ''
    }

    applyFilters()
}

const rowActions = props.actions.map((action) => ({
    ...action,
    href: (item) => recordActionHref(props.routeName, action, item, props.actionUrls),
}))

const selected = ref([])
// After a search, a page turn or an action, rows that left the page leave
// the selection too: a bulk button acts only on what is in front of the eyes.
watch(() => props.items.data, (rows) => {
    const ids = (rows || []).map((row) => row.id)
    selected.value = selected.value.filter((id) => ids.includes(id))
})

// A button that asks first: { action, run }.
const pending = ref(null)

const ask = (action, run) => {
    if (action.confirm) {
        pending.value = { action, run }
    } else {
        run()
    }
}

const confirmPending = () => {
    const { run } = pending.value
    pending.value = null
    run()
}

const handleRecordAction = ({ action, item }) => ask(action, () => runRecordAction(props.routeName, action, item))

const handleBulkAction = (action) => ask(action, () => {
    router.post(route(`${props.routeName}.bulk`, { action: action.name }), { ids: selected.value }, { preserveScroll: true })
})

const search = ref(props.filters.search || '')
const showDeleteModal = ref(false)
const itemToDelete = ref(null)

const handleSearch = () => {
    router.get(route(`${props.routeName}.index`), { ...listFilters.value, search: search.value }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const handleSort = ({ column, direction }) => {
    router.get(route(`${props.routeName}.index`), {
        ...props.filters,
        sort: column,
        direction: direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// The export is what the list shows now: the same search, sort and filters, every page of it.
const exportAs = (format) => {
    const query = Object.fromEntries(Object.entries(props.filters || {}).filter(([, value]) => value !== '' && value !== null))

    window.location.assign(route(`${props.routeName}.export`, { ...query, format }))
}

const handleEdit = (item) => {
    router.visit(route(`${props.routeName}.edit`, item.id))
}

const confirmDelete = (item) => {
    itemToDelete.value = item
    showDeleteModal.value = true
}

const handleDelete = () => {
    if (itemToDelete.value) {
        router.delete(route(`${props.routeName}.destroy`, itemToDelete.value.id), {
            onSuccess: () => {
                showDeleteModal.value = false
                itemToDelete.value = null
            }
        })
    }
}
</script>

<template>
    <Head :title="title" />
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 :data-page-title="routeName" class="text-2xl font-bold text-fg">{{ title }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $t('resource.index.total', { count: items.total }) }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <AdminButton
                v-for="link in links"
                :key="link.name"
                :data-link="link.name"
                :href="link.href"
                variant="secondary"
            >
                {{ link.label }}
            </AdminButton>
            <AdminButton v-if="transfer.import" data-action="import" :href="route(`${props.routeName}.import`)" variant="secondary">
                {{ $t('import.title') }}
            </AdminButton>
            <AdminButton v-if="transfer.export" data-action="export-csv" variant="secondary" @click="exportAs('csv')">
                {{ $t('export.title') }} {{ $t('export.csv') }}
            </AdminButton>
            <AdminButton v-if="transfer.export" data-action="export-xlsx" variant="secondary" @click="exportAs('xlsx')">
                {{ $t('export.title') }} {{ $t('export.xlsx') }}
            </AdminButton>
            <AdminButton v-if="can.create !== false" data-action="create" :href="route(`${props.routeName}.create`, listFilters)">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $t('common.create') }}
            </AdminButton>
        </div>
    </div>

    <div class="mb-6">
        <div class="flex gap-4">
            <div class="flex-1 max-w-md">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="$t('resource.index.search_placeholder')"
                    data-search-input="true"
                    class="w-full px-4 py-2 border border-field rounded-lg focus:ring-focus focus:border-focus"
                    @keyup.enter="handleSearch"
                />
            </div>
            <AdminButton data-action="search" @click="handleSearch" variant="secondary">
                {{ $t('resource.index.search_button') }}
            </AdminButton>
        </div>

        <!--
            Filters of the list. A chosen one shows its value in bold and
            the reset button counts them: not only a colour tells them.
        -->
        <div
            v-if="filterControls.length"
            role="group"
            :aria-label="$t('resource.filters.title')"
            data-filters="true"
            class="mt-3 flex flex-wrap items-end gap-3"
        >
            <div v-for="filter in filterControls" :key="filter.name" class="flex flex-col gap-1">
                <label :for="`filter-${filter.name}`" class="text-sm font-medium text-fg">{{ filter.label }}</label>
                <select
                    :id="`filter-${filter.name}`"
                    v-model="chosen[filter.name]"
                    :data-filter="filter.name"
                    :data-active="chosen[filter.name] !== '' || null"
                    class="rounded-lg border border-field bg-surface px-3 py-2 text-sm text-fg focus:border-focus focus:ring-focus"
                    :class="{ 'font-semibold': chosen[filter.name] !== '' }"
                    @change="applyFilters"
                >
                    <option value="">{{ $t('resource.filters.any') }}</option>
                    <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <button
                v-if="activeFilters"
                type="button"
                data-filters-reset="true"
                class="rounded px-2 py-2 text-sm text-fg underline hover:no-underline focus:outline-none focus:ring-2 focus:ring-focus"
                @click="resetFilters"
            >
                {{ $t('resource.filters.reset') }} ({{ activeFilters }})
            </button>
        </div>
    </div>

    <!--
        The bar above the list while rows are checked. The count is read
        out as it changes; "Delete" is told apart by its icon, not only
        by its colour.
    -->
    <p class="sr-only" aria-live="polite">{{ selected.length ? tc('actions.bulk.selected', selected.length) : '' }}</p>
    <div
        v-if="bulkActions.length && selected.length"
        role="region"
        :aria-label="$t('actions.bulk.region')"
        data-bulk-bar="true"
        class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-accent bg-accent-soft px-4 py-2 text-accent-soft-fg"
    >
        <span data-bulk-count class="text-sm font-semibold">{{ tc('actions.bulk.selected', selected.length) }}</span>
        <AdminButton
            v-for="action in bulkActions"
            :key="action.name"
            :data-bulk-action="action.name"
            :variant="action.name === 'delete' ? 'danger' : 'secondary'"
            size="sm"
            @click="handleBulkAction(action)"
        >
            <svg v-if="action.name === 'delete'" class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            {{ action.label }}
        </AdminButton>
        <button
            type="button"
            data-bulk-clear
            class="ml-auto rounded px-2 py-1 text-sm underline hover:no-underline focus:outline-none focus:ring-2 focus:ring-focus"
            @click="selected = []"
        >
            {{ $t('actions.bulk.clear') }}
        </button>
    </div>

    <AdminDataTable
        :columns="columns"
        :items="items"
        :sort-by="filters.sort"
        :sort-direction="filters.direction"
        :can-delete="can.delete !== false"
        :row-actions="rowActions"
        :selectable="bulkActions.length > 0"
        v-model:selected="selected"
        @action="handleRecordAction"
        @sort="handleSort"
        @edit="handleEdit"
        @delete="confirmDelete"
    />

    <AdminModal
        :show="showDeleteModal"
        :title="$t('resource.delete.title')"
        @close="showDeleteModal = false"
    >
        <p class="text-muted">
            {{ $t('resource.delete.confirm') }}
        </p>

        <template #footer>
            <AdminButton data-action="delete-cancel" variant="secondary" @click="showDeleteModal = false">
                {{ $t('common.cancel') }}
            </AdminButton>
            <AdminButton data-action="delete-confirm" variant="danger" @click="handleDelete">
                {{ $t('common.delete') }}
            </AdminButton>
        </template>
    </AdminModal>

    <AdminModal
        :show="pending !== null"
        :title="$t('actions.confirm_title')"
        @close="pending = null"
    >
        <p data-action-question class="text-fg">{{ pending?.action.confirm }}</p>

        <template #footer>
            <AdminButton data-action="action-cancel" variant="secondary" @click="pending = null">
                {{ $t('common.cancel') }}
            </AdminButton>
            <AdminButton
                data-action="action-confirm"
                :variant="pending?.action.name === 'delete' ? 'danger' : 'primary'"
                @click="confirmPending"
            >
                {{ pending?.action.label }}
            </AdminButton>
        </template>
    </AdminModal>
</template>
