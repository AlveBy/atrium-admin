<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()

const sites = computed(() => page.props.sites || [])
const current = computed(() => page.props.currentSite?.id)
const currentName = computed(() => page.props.currentSite?.name ?? '')

const change = (event) => {
    router.post(route('admin.site'), { site_id: event.target.value }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <!--
        One site: nothing to switch.

        A bare select showed only "Atrium", and nobody could tell that it picks
        the site whose records are edited. So the caption sits in the button
        itself, above the value, in every theme and on a phone; the tooltip and
        aria-label carry the explanation. The native select lies on top with
        transparent text, so a click, the keyboard and the phone's own picker
        all still work.
    -->
    <div
        v-if="sites.length > 1"
        class="relative flex min-w-0 items-center gap-1 rounded-lg px-1 py-1 text-muted hover:bg-hover hover:text-fg sm:gap-2 sm:px-2"
    >
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8.25V18a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 18V8.25m-18 0V6a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 6v2.25m-18 0h18M5.25 6h.008v.008H5.25V6zm2.25 0h.008v.008H7.5V6zm2.25 0h.008v.008H9.75V6z" />
        </svg>
        <span class="flex min-w-0 flex-col text-left leading-tight" aria-hidden="true">
            <span data-switcher-caption class="truncate text-[11px] sm:text-xs">{{ $t('layout.site') }}</span>
            <span class="max-w-14 truncate text-sm font-medium text-fg sm:max-w-32 xl:max-w-48">{{ currentName }}</span>
        </span>
        <svg class="hidden h-4 w-4 shrink-0 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>

        <select
            data-site-switcher
            name="site_id"
            :aria-label="$t('layout.site')"
            :aria-description="$t('layout.site_hint')"
            :title="$t('layout.site_hint')"
            :value="current"
            class="absolute inset-0 h-full w-full cursor-pointer appearance-none rounded-lg bg-transparent text-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            @change="change"
        >
            <option v-for="site in sites" :key="site.id" :value="site.id" class="bg-surface text-fg">
                {{ site.name }}
            </option>
        </select>
    </div>
</template>
