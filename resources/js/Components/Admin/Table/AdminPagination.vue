<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    paginator: {
        type: Object,
        required: true
    }
})

const pages = computed(() => {
    const current = props.paginator.current_page
    const last = props.paginator.last_page
    const delta = 2
    const range = []
    const rangeWithDots = []

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
            range.push(i)
        }
    }

    let prev = null
    for (const i of range) {
        if (prev) {
            if (i - prev === 2) {
                rangeWithDots.push(prev + 1)
            } else if (i - prev !== 1) {
                rangeWithDots.push('...')
            }
        }
        rangeWithDots.push(i)
        prev = i
    }

    return rangeWithDots
})
</script>

<template>
    <div class="bg-surface px-4 py-3 border-t border-line sm:px-6">
        <div class="flex items-center justify-between">
            <div class="text-sm text-fg">
                {{ $t('resource.pagination.showing', { from: paginator.from, to: paginator.to, total: paginator.total }) }}
            </div>
            <nav class="inline-flex -space-x-px rounded-md shadow-sm">
                <!-- Previous -->
                <Link
                    v-if="paginator.prev_page_url"
                    :href="paginator.prev_page_url"
                    class="relative inline-flex items-center rounded-l-md px-2 py-2 text-muted ring-1 ring-inset ring-field hover:bg-hover"
                    preserve-scroll
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    </svg>
                </Link>
                <span v-else class="relative inline-flex items-center rounded-l-md px-2 py-2 text-muted ring-1 ring-inset ring-field">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    </svg>
                </span>

                <!-- Pages -->
                <template v-for="page in pages" :key="page">
                    <span
                        v-if="page === '...'"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-fg ring-1 ring-inset ring-field"
                    >
                        ...
                    </span>
                    <Link
                        v-else
                        :href="`${paginator.path}?page=${page}`"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-field"
                        :class="page === paginator.current_page
                            ? 'z-10 bg-accent text-on-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus'
                            : 'text-fg hover:bg-hover'"
                        preserve-scroll
                    >
                        {{ page }}
                    </Link>
                </template>

                <!-- Next -->
                <Link
                    v-if="paginator.next_page_url"
                    :href="paginator.next_page_url"
                    class="relative inline-flex items-center rounded-r-md px-2 py-2 text-muted ring-1 ring-inset ring-field hover:bg-hover"
                    preserve-scroll
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                </Link>
                <span v-else class="relative inline-flex items-center rounded-r-md px-2 py-2 text-muted ring-1 ring-inset ring-field">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                </span>
            </nav>
        </div>
    </div>
</template>
