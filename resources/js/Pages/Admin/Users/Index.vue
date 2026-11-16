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

const props = defineProps({
    items: Object,
    columns: Array,
    filters: Object,
    title: String,
    can: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
const showDeleteModal = ref(false)
const itemToDelete = ref(null)

const handleSearch = () => {
    router.get(route('admin.users.index'), { search: search.value }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const handleSort = ({ column, direction }) => {
    router.get(route('admin.users.index'), {
        ...props.filters,
        sort: column,
        direction: direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const handleEdit = (item) => {
    router.visit(route('admin.users.edit', item.id))
}

const confirmDelete = (item) => {
    itemToDelete.value = item
    showDeleteModal.value = true
}

const handleDelete = () => {
    if (itemToDelete.value) {
        router.delete(route('admin.users.destroy', itemToDelete.value.id), {
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
        <!-- Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $t('account.users.total', { count: items.total }) }}
                </p>
            </div>
            <AdminButton v-if="can.create !== false" data-action="create" :href="route('admin.users.create')">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $t('common.create') }}
            </AdminButton>
        </div>

        <!-- Search -->
        <div class="mb-6">
            <div class="flex gap-4">
                <div class="flex-1 max-w-md">
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('account.users.search_placeholder')"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                        @keyup.enter="handleSearch"
                    />
                </div>
                <AdminButton @click="handleSearch" variant="secondary">
                    {{ $t('common.search') }}
                </AdminButton>
            </div>
        </div>

        <!-- Table -->
        <AdminDataTable
            :columns="columns"
            :items="items"
            :sort-by="filters.sort"
            :sort-direction="filters.direction"
            :can-delete="can.delete !== false"
            @sort="handleSort"
            @edit="handleEdit"
            @delete="confirmDelete"
        />

        <!-- Delete Confirmation Modal -->
        <AdminModal
            :show="showDeleteModal"
            :title="$t('account.users.delete_title')"
            @close="showDeleteModal = false"
        >
            <p class="text-gray-600">
                {{ $t('account.users.delete_confirm') }}
                <strong v-if="itemToDelete">{{ itemToDelete.name }}</strong>
            </p>
            <p class="mt-2 text-sm text-red-600">
                {{ $t('account.users.delete_irreversible') }}
            </p>

            <template #footer>
                <AdminButton variant="secondary" @click="showDeleteModal = false">
                    {{ $t('common.cancel') }}
                </AdminButton>
                <AdminButton variant="danger" @click="handleDelete">
                    {{ $t('common.delete') }}
                </AdminButton>
            </template>
        </AdminModal>
    </AdminLayout>
</template>
