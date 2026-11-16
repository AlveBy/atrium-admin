<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminDataTable from '../../../Components/Admin/Table/AdminDataTable.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'
import { recordActionHref } from '../../../Support/recordActions'

const props = defineProps({
    routeName: String,
    createLabel: String,
    items: Object,
    columns: Array,
    filters: Object,
    title: String,
    // view / create / update / delete — what the administrator's roles allow here.
    can: { type: Object, default: () => ({}) },
    // Buttons on a record that open the create form filled from it.
    actions: { type: Array, default: () => [] },
    // Buttons above the list added by packages ("Fields" of custom fields).
    links: { type: Array, default: () => [] },
})

// Filters from the address (?module=…) that the module declared: kept on
// search and sort, and passed on to the create form.
const listFilters = Object.fromEntries(
    Object.entries(props.filters || {}).filter(([key]) => !['search', 'sort', 'direction'].includes(key))
)

const rowActions = props.actions.map((action) => ({
    ...action,
    href: (item) => recordActionHref(props.routeName, action, item),
}))

const search = ref(props.filters.search || '')
const showDeleteModal = ref(false)
const itemToDelete = ref(null)

const handleSearch = () => {
    router.get(route(`${props.routeName}.index`), { ...listFilters, search: search.value }, {
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
    <AdminLayout :title="title">
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
        </div>

        <AdminDataTable
            :columns="columns"
            :items="items"
            :sort-by="filters.sort"
            :sort-direction="filters.direction"
            :can-delete="can.delete !== false"
            :row-actions="rowActions"
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
    </AdminLayout>
</template>
