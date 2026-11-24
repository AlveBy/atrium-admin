<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The activity log: every change of every module the administrator may
 * open, newest first, narrowed by module, administrator and dates.
 */
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import AdminPagination from '../../../Components/Admin/Table/AdminPagination.vue'
import ActivityEntries from '../../../Components/Admin/History/ActivityEntries.vue'

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, required: true },
    modules: { type: Array, required: true },
    admins: { type: Array, required: true },
})

const form = reactive({ ...props.filters })

const apply = () => {
    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null))
    router.get(route('admin.activity.index'), query, { preserveState: true })
}

const reset = () => {
    Object.keys(form).forEach((key) => { form[key] = '' })
    apply()
}

const field = 'block w-full rounded-md border border-field bg-surface px-3 py-2 text-sm text-fg focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <Head :title="$t('history.title')" />
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-fg">{{ $t('history.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $t('history.subtitle') }}</p>
    </div>

    <AdminCard>
        <form role="search" class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" @submit.prevent="apply" data-history-filters>
            <div>
                <label for="history-module" class="mb-1 block text-sm font-medium text-fg">{{ $t('history.filters.module') }}</label>
                <select id="history-module" v-model="form.module" dusk="history-module" :class="field">
                    <option value="">{{ $t('history.filters.all') }}</option>
                    <option v-for="module in modules" :key="module.key" :value="module.key">{{ module.label }}</option>
                </select>
            </div>
            <div>
                <label for="history-admin" class="mb-1 block text-sm font-medium text-fg">{{ $t('history.filters.admin') }}</label>
                <select id="history-admin" v-model="form.admin" dusk="history-admin" :class="field">
                    <option value="">{{ $t('history.filters.all') }}</option>
                    <option v-for="admin in admins" :key="admin.id" :value="String(admin.id)">{{ admin.name }}</option>
                </select>
            </div>
            <div>
                <label for="history-from" class="mb-1 block text-sm font-medium text-fg">{{ $t('history.filters.from') }}</label>
                <input id="history-from" v-model="form.from" type="date" dusk="history-from" :class="field">
            </div>
            <div>
                <label for="history-to" class="mb-1 block text-sm font-medium text-fg">{{ $t('history.filters.to') }}</label>
                <input id="history-to" v-model="form.to" type="date" dusk="history-to" :class="field">
            </div>
            <div class="flex gap-2">
                <AdminButton type="submit" dusk="history-apply">{{ $t('history.filters.apply') }}</AdminButton>
                <AdminButton type="button" variant="secondary" dusk="history-reset" @click="reset">{{ $t('history.filters.reset') }}</AdminButton>
            </div>
        </form>

        <ActivityEntries :entries="entries.data" with-record />
    </AdminCard>

    <AdminPagination v-if="entries.last_page > 1" :paginator="entries" class="mt-4 rounded-lg" />
</template>
