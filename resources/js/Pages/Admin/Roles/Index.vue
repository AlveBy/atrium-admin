<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'

defineProps({
    roles: Array,
    title: String,
})

const roleToDelete = ref(null)

const destroy = () => {
    router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => { roleToDelete.value = null },
    })
}
</script>

<template>
    <AdminLayout :title="title">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 data-page-title="admin.roles" class="text-2xl font-bold text-fg">{{ title }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $t('permissions.index_hint') }}</p>
            </div>
            <AdminButton data-action="create" :href="route('admin.roles.create')">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $t('common.create') }}
            </AdminButton>
        </div>

        <div class="bg-surface rounded-lg shadow overflow-hidden">
            <p v-if="roles.length === 0" class="p-6 text-sm text-muted">{{ $t('permissions.empty') }}</p>
            <table v-else class="min-w-full text-sm">
                <thead class="bg-surface-alt">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-fg">{{ $t('permissions.name') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-fg">{{ $t('permissions.permissions_count') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-fg">{{ $t('permissions.users_count') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="role in roles" :key="role.id" data-role-row>
                        <td class="px-4 py-2 font-medium text-fg">{{ role.name }}</td>
                        <td class="px-4 py-2 text-fg">{{ role.permissions_count }}</td>
                        <td class="px-4 py-2 text-fg">{{ role.users_count }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <AdminButton size="sm" variant="outline" data-action="edit" :href="route('admin.roles.edit', role.id)">{{ $t('common.edit') }}</AdminButton>
                            <AdminButton size="sm" variant="danger" data-action="delete" class="ml-2" @click="roleToDelete = role">{{ $t('common.delete') }}</AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AdminModal :show="roleToDelete !== null" :title="$t('permissions.delete_title')" @close="roleToDelete = null">
            <p class="text-muted">
                <strong>{{ roleToDelete?.name }}</strong> — {{ $t('permissions.delete_confirm') }}
            </p>
            <template #footer>
                <AdminButton variant="secondary" @click="roleToDelete = null">{{ $t('common.cancel') }}</AdminButton>
                <AdminButton variant="danger" data-action="delete-confirm" @click="destroy">{{ $t('common.delete') }}</AdminButton>
            </template>
        </AdminModal>
    </AdminLayout>
</template>
