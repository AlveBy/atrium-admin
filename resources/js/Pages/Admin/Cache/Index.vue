<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Cache" section: how well the record cache works, and clearing it for
 * one module, one site or everything.
 */
import { ref } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'

const props = defineProps({
    status: Object,
    modules: Array,
    sites: Array,
})

const module = ref(props.modules[0]?.key ?? '')
const site = ref(props.sites[0]?.id ?? '')
const busy = ref(false)

const clear = (scope, extra = {}) => {
    busy.value = true
    router.post(route('admin.cache.clear'), { scope, ...extra }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false },
    })
}

const number = (value) => (value === null || value === undefined ? '—' : new Intl.NumberFormat().format(value))

const bytes = (value) => {
    if (value === null || value === undefined) {
        return '—'
    }

    const units = ['B', 'KB', 'MB', 'GB']
    let size = value
    let unit = 0

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024
        unit++
    }

    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}
</script>

<template>
    <Head :title="$t('menu.cache')" />
    <div class="mb-6">
        <h1 data-page-title="admin.cache" class="text-2xl font-bold text-gray-900">{{ $t('menu.cache') }}</h1>
        <p class="mt-1 text-sm text-gray-600">{{ $t('cache.subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4" data-cache-stat="rate">
            <div class="text-sm text-gray-600">{{ $t('cache.hit_rate') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ status.rate === null ? '—' : `${status.rate}%` }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ $t('cache.hits') }}: {{ number(status.hits) }} · {{ $t('cache.misses') }}: {{ number(status.misses) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4" data-cache-stat="size">
            <div class="text-sm text-gray-600">{{ $t('cache.size') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ bytes(status.bytes) }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ $t('cache.entries') }}: {{ number(status.entries) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4" data-cache-stat="store">
            <div class="text-sm text-gray-600">{{ $t('cache.store') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ status.driver }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ status.tags ? $t('cache.with_tags') : $t('cache.without_tags') }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4" data-cache-stat="state">
            <div class="text-sm text-gray-600">{{ $t('cache.records') }}</div>
            <div class="mt-1 text-2xl font-semibold" :class="status.enabled ? 'text-green-700' : 'text-gray-500'">
                {{ status.enabled ? $t('cache.on') : $t('cache.off') }}
            </div>
            <div class="mt-1 text-xs text-gray-500">{{ $t('cache.pages') }}: {{ status.pages ? $t('cache.on') : $t('cache.off') }}</div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 space-y-6">
        <p class="text-sm text-gray-600">{{ $t('cache.clear_hint') }}</p>

        <div v-if="modules.length" class="flex flex-col sm:flex-row sm:items-end gap-3">
            <label class="flex-1">
                <span class="block text-sm font-medium text-gray-700 mb-1">{{ $t('cache.module') }}</span>
                <select v-model="module" data-cache-module class="w-full rounded-md border-gray-300 text-sm">
                    <option v-for="item in modules" :key="item.key" :value="item.key">{{ item.label }}</option>
                </select>
            </label>
            <AdminButton variant="outline" data-action="clear-module" :disabled="busy" @click="clear('module', { module })">{{ $t('cache.clear_module') }}</AdminButton>
        </div>

        <div v-if="sites.length > 1" class="flex flex-col sm:flex-row sm:items-end gap-3">
            <label class="flex-1">
                <span class="block text-sm font-medium text-gray-700 mb-1">{{ $t('cache.site') }}</span>
                <select v-model="site" data-cache-site class="w-full rounded-md border-gray-300 text-sm">
                    <option v-for="item in sites" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </label>
            <AdminButton variant="outline" data-action="clear-site" :disabled="busy" @click="clear('site', { site })">{{ $t('cache.clear_site') }}</AdminButton>
        </div>

        <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
            <AdminButton variant="danger" data-action="clear-all" :disabled="busy" @click="clear('all')">{{ $t('cache.clear_all') }}</AdminButton>
            <AdminButton variant="secondary" data-action="reset-stats" :disabled="busy" @click="clear('stats')">{{ $t('cache.reset_stats') }}</AdminButton>
        </div>
    </div>
</template>
