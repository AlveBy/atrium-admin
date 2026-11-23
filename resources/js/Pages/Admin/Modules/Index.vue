<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The installed module packages, with a link to the settings of those that
 * have any. Submodules come right under their parent, indented. A
 * superadmin also switches modules off and on, and sees the packages
 * required but not installed and the catalogue on Packagist (loaded after
 * the page).
 */
import { Deferred, router, Head } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'

defineProps({
    modules: Array,
    required: Array,
    canManage: Boolean,
    catalogue: Object,
    title: String,
})

const toggle = (module) => {
    router.post(route(module.enabled ? 'admin.modules.disable' : 'admin.modules.enable', module.key), {}, { preserveScroll: true })
}

const installCommand = (name) => `php artisan atrium:module:install ${name}`
</script>

<template>
    <Head :title="title" />
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
                <tr
                    v-for="module in modules"
                    :key="module.name"
                    :data-module="module.name"
                    :data-parent="module.parent"
                    :data-enabled="module.enabled ? 'true' : 'false'"
                >
                    <td class="py-2 pr-4" :class="module.parent ? 'pl-10' : 'pl-4'">
                        <div class="font-medium" :class="module.enabled ? 'text-fg' : 'text-muted'">
                            <span v-if="module.parent" aria-hidden="true" class="mr-1 text-muted">↳</span>{{ module.label }}
                            <span v-if="!module.enabled" class="ml-2 rounded bg-surface-alt px-1.5 py-0.5 text-xs font-normal text-muted">{{ $t('modules.status_disabled') }}</span>
                        </div>
                        <div v-if="module.description" class="text-muted">{{ module.description }}</div>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-muted">{{ module.name }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                        <AdminButton
                            v-if="module.settings"
                            size="sm"
                            variant="outline"
                            data-action="settings"
                            :href="route('admin.modules.settings', module.key)"
                        >
                            {{ $t('modules.settings') }}
                        </AdminButton>
                        <AdminButton
                            v-if="canManage"
                            size="sm"
                            :variant="module.enabled ? 'secondary' : 'success'"
                            :data-action="module.enabled ? 'disable' : 'enable'"
                            @click="toggle(module)"
                        >
                            {{ module.enabled ? $t('modules.disable') : $t('modules.enable') }}
                        </AdminButton>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <section v-if="canManage && required.length" data-section="required" class="mt-8">
        <h2 class="text-lg font-semibold text-fg">{{ $t('modules.required_title') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ $t('modules.required_hint') }}</p>
        <ul class="mt-3 bg-surface rounded-lg shadow divide-y divide-line text-sm">
            <li v-for="module in required" :key="module.name" :data-required="module.name" class="px-4 py-3">
                <div class="font-medium text-fg">{{ module.label }}</div>
                <div v-if="module.description" class="text-muted">{{ module.description }}</div>
                <code class="mt-1 block font-mono text-xs text-fg select-all">{{ installCommand(module.name) }}</code>
            </li>
        </ul>
    </section>

    <section v-if="canManage" data-section="catalogue" class="mt-8">
        <h2 class="text-lg font-semibold text-fg">{{ $t('modules.catalogue_title') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ $t('modules.catalogue_hint') }}</p>
        <Deferred data="catalogue">
            <template #fallback>
                <p class="mt-3 text-sm text-muted">{{ $t('modules.catalogue_loading') }}</p>
            </template>
            <p v-if="catalogue.packages === null" data-catalogue="unavailable" class="mt-3 text-sm text-muted">{{ $t('modules.catalogue_unavailable') }}</p>
            <p v-else-if="catalogue.packages.length === 0" class="mt-3 text-sm text-muted">{{ $t('modules.catalogue_empty') }}</p>
            <ul v-else class="mt-3 bg-surface rounded-lg shadow divide-y divide-line text-sm">
                <li v-for="item in catalogue.packages" :key="item.name" :data-package="item.name" :data-status="item.status" class="px-4 py-3">
                    <div class="flex flex-wrap items-baseline gap-x-3">
                        <a :href="item.url" target="_blank" rel="noopener" class="font-mono text-sm font-medium text-fg hover:underline">{{ item.name }}</a>
                        <span v-if="item.status !== 'available'" class="rounded bg-surface-alt px-1.5 py-0.5 text-xs text-muted">{{ $t(`modules.status_${item.status}`) }}</span>
                        <span class="text-xs text-muted">{{ $t('modules.downloads', { count: item.downloads }) }}</span>
                    </div>
                    <div v-if="item.description" class="text-muted">{{ item.description }}</div>
                    <template v-if="item.status === 'available' || item.status === 'required'">
                        <code v-if="item.status === 'available'" class="mt-1 block font-mono text-xs text-fg select-all">composer require {{ item.name }}</code>
                        <code class="mt-1 block font-mono text-xs text-fg select-all">{{ installCommand(item.name) }}</code>
                    </template>
                </li>
            </ul>
        </Deferred>
    </section>
</template>
