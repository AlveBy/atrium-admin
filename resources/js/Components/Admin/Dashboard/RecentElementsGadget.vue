<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * "Recent items" — the most recently changed items of one module: the
 * picture, the date and the name linking to the edit form, "All items" below.
 */
defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-surface rounded-lg shadow px-5 py-4" data-gadget="recent">
        <h2 class="text-sm font-semibold text-fg">{{ title || $t('dashboard.recent.title') }}</h2>

        <ul v-if="data.items.length" class="mt-3 divide-y divide-line">
            <li v-for="item in data.items" :key="item.id" class="flex gap-3 py-2">
                <img
                    v-if="item.image"
                    :src="item.image"
                    alt=""
                    class="h-12 w-12 flex-none rounded object-cover bg-off"
                >
                <div v-else class="h-12 w-12 flex-none rounded bg-off" />

                <div class="min-w-0">
                    <div v-if="item.date" class="text-xs text-muted">{{ item.date }}</div>
                    <a
                        v-if="item.url"
                        :href="item.url"
                        class="text-sm text-accent hover:underline line-clamp-2"
                    >{{ item.name }}</a>
                    <span v-else class="text-sm text-fg line-clamp-2">{{ item.name }}</span>
                </div>
            </li>
        </ul>

        <p v-else class="mt-3 text-sm text-muted">{{ $t('dashboard.recent.empty') }}</p>

        <a
            v-if="data.allUrl"
            :href="data.allUrl"
            class="mt-3 inline-block text-sm text-accent hover:underline"
        >{{ data.allLabel }}</a>
    </div>
</template>
