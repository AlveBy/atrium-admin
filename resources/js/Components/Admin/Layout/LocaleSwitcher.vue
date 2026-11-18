<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()

const locales = computed(() => page.props.locales || {})
const current = computed(() => page.props.locale)
const currentName = computed(() => locales.value[current.value] ?? current.value)
// On a phone the value is the code: pt_BR → PT.
const currentCode = computed(() => String(current.value ?? '').split('_')[0].toUpperCase())

const change = (event) => {
    router.post(route('admin.locale'), { locale: event.target.value }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <!--
        The language of the panel itself, kept per administrator. It does not
        pick the language of the content: that is the switch on the edit form,
        so the caption says "admin language" in so many words.

        The caption sits in the button itself, above the value, in every theme
        and on a phone; the tooltip and aria-label carry the explanation. The
        native select lies on top with transparent text, so a click, the
        keyboard and the phone's own picker all still work.
    -->
    <div class="relative flex min-w-0 items-center gap-1 rounded-lg px-1 py-1 text-muted hover:bg-hover hover:text-fg sm:gap-2 sm:px-2">
        <svg class="hidden h-5 w-5 shrink-0 min-[360px]:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
        </svg>
        <span class="flex min-w-0 flex-col text-left leading-tight" aria-hidden="true">
            <span data-switcher-caption class="break-words text-[11px] sm:truncate sm:text-xs">{{ $t('layout.admin_language') }}</span>
            <span class="hidden truncate text-sm font-medium text-fg sm:inline" :lang="String(current).replace('_', '-')">{{ currentName }}</span>
            <span class="text-sm font-medium text-fg sm:hidden">{{ currentCode }}</span>
        </span>
        <svg class="hidden h-4 w-4 shrink-0 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>

        <select
            data-locale-switcher
            name="locale"
            :aria-label="$t('layout.admin_language')"
            :aria-description="$t('layout.admin_language_hint')"
            :title="$t('layout.admin_language_hint')"
            :value="current"
            class="absolute inset-0 h-full w-full cursor-pointer appearance-none rounded-lg bg-transparent text-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            @change="change"
        >
            <option v-for="(name, code) in locales" :key="code" :value="code" :lang="code.replace('_', '-')" class="bg-surface text-fg">
                {{ name }}
            </option>
        </select>
    </div>
</template>
