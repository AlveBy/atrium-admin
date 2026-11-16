<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The installed module packages, with a link to the settings of those that
 * have any. Submodules come right under their parent, indented.
 */
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'

defineProps({
    modules: Array,
    title: String,
})
</script>

<template>
    <AdminLayout :title="title">
        <div class="mb-6">
            <h1 data-page-title="admin.modules" class="text-2xl font-bold text-fg">{{ title }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $t('modules.index_hint') }}</p>
        </div>

        <div class="bg-surface rounded-lg shadow overflow-hidden">
            <p v-if="modules.length === 0" class="p-6 text-sm text-muted">{{ $t('modules.empty') }}</p>
            <table v-else class="min-w-full text-sm">
                <thead class="bg-surface-alt">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-fg">{{ $t('modules.module') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-fg">{{ $t('modules.package') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="module in modules" :key="module.name" :data-module="module.name" :data-parent="module.parent">
                        <td class="py-2 pr-4" :class="module.parent ? 'pl-10' : 'pl-4'">
                            <div class="font-medium text-fg"><span v-if="module.parent" aria-hidden="true" class="mr-1 text-muted">↳</span>{{ module.label }}</div>
                            <div v-if="module.description" class="text-muted">{{ module.description }}</div>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs text-muted">{{ module.name }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <AdminButton
                                v-if="module.settings"
                                size="sm"
                                variant="outline"
                                data-action="settings"
                                :href="route('admin.modules.settings', module.key)"
                            >
                                {{ $t('modules.settings') }}
                            </AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
