<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The history of one record: what the activity log has about it, from its
 * form's "History" button.
 */
import { Head, Link } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import AdminPagination from '../../../Components/Admin/Table/AdminPagination.vue'
import ActivityEntries from '../../../Components/Admin/History/ActivityEntries.vue'

defineProps({
    entries: { type: Object, required: true },
    title: { type: String, required: true },
    moduleLabel: { type: String, required: true },
    indexUrl: { type: String, required: true },
    editUrl: { type: String, required: true },
})
</script>

<template>
    <Head :title="title" />
    <nav class="mb-6 text-sm" aria-label="Breadcrumb">
        <Link :href="indexUrl" class="text-muted hover:text-accent">{{ moduleLabel }}</Link>
    </nav>

    <div class="max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-fg">{{ title }}</h1>
            <AdminButton :href="editUrl" variant="secondary" data-action="back-to-record">{{ $t('history.back') }}</AdminButton>
        </div>

        <AdminCard>
            <ActivityEntries :entries="entries.data" />
        </AdminCard>

        <AdminPagination v-if="entries.last_page > 1" :paginator="entries" class="mt-4 rounded-lg" />
    </div>
</template>
