<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The import of a module's records: upload a CSV or Excel file, then map its
 * columns on the next screen. The earlier imports are listed below with what
 * they did.
 */
import { computed } from 'vue'
import { Link, useForm, usePage, Head } from '@inertiajs/vue3'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ImportStatus from '@atrium/Components/Admin/Transfer/ImportStatus.vue'

const props = defineProps({
    // The module's page, whose list this import leaves for and returns to: "admin.news".
    routeName: { type: String, required: true },
    title: { type: String, required: true },
    imports: { type: Array, required: true },
    maxSize: { type: Number, required: true },
})

const page = usePage()
const locale = computed(() => String(page.props.locale || 'en').replace('_', '-'))

const form = useForm({ file: null })

function upload() {
    form.post(route(`${props.routeName}.import.store`), { forceFormData: true })
}

function date(iso) {
    return iso ? new Date(iso).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' }) : ''
}
</script>

<template>
    <Head :title="`${$t('import.title')}: ${title}`" />
    <Link :href="route(`${routeName}.index`)" class="text-sm text-accent hover:text-accent-hover hover:underline" dusk="import-module">← {{ title }}</Link>
    <h1 class="mt-3 mb-6 text-2xl font-bold text-fg">{{ $t('import.title') }}: {{ title }}</h1>

    <AdminCard :title="$t('import.index.upload')" class="mb-6">
        <form class="flex flex-col gap-3" dusk="import-upload" @submit.prevent="upload">
            <p class="text-sm text-muted">{{ $t('import.index.hint', { size: Math.round(maxSize / 1024) }) }}</p>
            <label class="block">
                <span class="sr-only">{{ $t('import.index.choose') }}</span>
                <input
                    type="file"
                    accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="block w-full text-sm text-fg file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-2 file:text-sm file:font-medium file:text-on-accent hover:file:bg-accent-hover"
                    dusk="import-file"
                    @change="form.file = $event.target.files[0] ?? null"
                >
            </label>
            <p v-if="form.errors.file" class="text-sm text-danger" role="alert" dusk="import-file-error">{{ form.errors.file }}</p>
            <div>
                <AdminButton type="submit" :disabled="!form.file || form.processing" dusk="import-submit">{{ $t('import.index.submit') }}</AdminButton>
            </div>
        </form>
    </AdminCard>

    <AdminCard :title="$t('import.index.history')" no-padding>
        <p v-if="imports.length === 0" class="p-6 text-sm text-muted" dusk="import-empty">{{ $t('import.index.empty') }}</p>
        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm" dusk="import-history">
                <thead class="bg-surface-alt">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ $t('import.index.file') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ $t('import.index.status') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ $t('import.index.result') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ $t('import.index.user') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-medium text-fg">{{ $t('import.index.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="item in imports" :key="item.id" class="hover:bg-hover" dusk="import-row">
                        <td class="px-4 py-3">
                            <Link :href="route(`${routeName}.import.show`, item.id)" class="text-accent hover:text-accent-hover hover:underline break-all">{{ item.original_name }}</Link>
                        </td>
                        <td class="px-4 py-3"><ImportStatus :status="item.status" /></td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $t('import.index.counts', item) }}</td>
                        <td class="px-4 py-3 text-muted">{{ item.user ?? $t('import.index.console') }}</td>
                        <td class="px-4 py-3 text-muted whitespace-nowrap">{{ date(item.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminCard>
</template>
