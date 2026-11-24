<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The trash: what was deleted in the modules the administrator may open,
 * newest first, to restore or to delete for good.
 */
import { reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'
import AdminPagination from '../../../Components/Admin/Table/AdminPagination.vue'

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, required: true },
    modules: { type: Array, required: true },
    days: { type: Number, required: true },
})

const form = reactive({ ...props.filters })

const apply = () => {
    router.get(route('admin.trash.index'), form.module ? { module: form.module } : {}, { preserveState: true })
}

const locale = () => (usePage().props.locale || 'en').replace('_', '-')

const when = (at) => new Date(at).toLocaleString(locale(), { dateStyle: 'medium', timeStyle: 'short' })

// "30 days" in the administrator's language, plural forms included.
const period = () => new Intl.NumberFormat(locale(), { style: 'unit', unit: 'day', unitDisplay: 'long' }).format(props.days)

const restore = (entry) => router.post(entry.restoreUrl)

const deleting = ref(null)

const destroy = () => {
    router.delete(deleting.value.deleteUrl, { preserveScroll: true, onFinish: () => { deleting.value = null } })
}

const field = 'block w-full rounded-md border border-field bg-surface px-3 py-2 text-sm text-fg focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <Head :title="$t('trash.title')" />
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-fg">{{ $t('trash.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $t('trash.subtitle') }}</p>
        <p class="mt-1 text-sm text-muted" data-trash-keeps>{{ days > 0 ? $t('trash.keeps', { days: period() }) : $t('trash.keeps_forever') }}</p>
    </div>

    <AdminCard>
        <form role="search" class="mb-4 flex flex-wrap items-end gap-4" @submit.prevent="apply" data-trash-filters>
            <div class="min-w-56">
                <label for="trash-module" class="mb-1 block text-sm font-medium text-fg">{{ $t('trash.module') }}</label>
                <select id="trash-module" v-model="form.module" dusk="trash-module" :class="field">
                    <option value="">{{ $t('history.filters.all') }}</option>
                    <option v-for="module in modules" :key="module.key" :value="module.key">{{ module.label }}</option>
                </select>
            </div>
            <AdminButton type="submit" dusk="trash-apply">{{ $t('history.filters.apply') }}</AdminButton>
        </form>

        <p v-if="!entries.data.length" data-trash-empty class="py-8 text-center text-muted">{{ $t('trash.empty') }}</p>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm" data-trash-entries>
                <thead>
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-fg">{{ $t('trash.record') }}</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-fg">{{ $t('trash.module') }}</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-fg">{{ $t('trash.deleted_by') }}</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-fg">{{ $t('trash.deleted_at') }}</th>
                        <th scope="col" class="px-3 py-2"><span class="sr-only">{{ $t('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="entry in entries.data" :key="entry.id" :data-trash-entry="entry.id">
                        <td class="px-3 py-2 text-fg" data-trash-record>{{ entry.record }}</td>
                        <td class="px-3 py-2 text-muted">{{ entry.module }}</td>
                        <td class="px-3 py-2 text-muted">{{ entry.admin || $t('history.system') }}</td>
                        <td class="px-3 py-2 text-muted"><time :datetime="entry.at">{{ when(entry.at) }}</time></td>
                        <td class="px-3 py-2">
                            <div class="flex justify-end gap-2">
                                <AdminButton v-if="entry.restoreUrl" size="sm" variant="secondary" data-action="trash-restore" @click="restore(entry)">{{ $t('trash.restore') }}</AdminButton>
                                <AdminButton v-if="entry.deleteUrl" size="sm" variant="danger" data-action="trash-delete" @click="deleting = entry">{{ $t('trash.delete') }}</AdminButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminCard>

    <AdminPagination v-if="entries.last_page > 1" :paginator="entries" class="mt-4 rounded-lg" />

    <AdminModal :show="deleting !== null" :title="$t('trash.confirm_title')" @close="deleting = null">
        <p class="text-fg">{{ $t('trash.confirm') }}</p>
        <p v-if="deleting" class="mt-2 font-medium text-fg">{{ deleting.record }}</p>

        <template #footer>
            <AdminButton data-action="trash-delete-cancel" variant="secondary" @click="deleting = null">{{ $t('common.cancel') }}</AdminButton>
            <AdminButton data-action="trash-delete-confirm" variant="danger" @click="destroy">{{ $t('trash.delete') }}</AdminButton>
        </template>
    </AdminModal>
</template>
