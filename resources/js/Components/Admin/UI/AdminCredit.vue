<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/*
 * "Made by AlveSoft Solutions": the developer's credit at the bottom of the
 * sidebar and under the sign-in form. Small and quiet, and switched off with
 * `atrium.credit` = false: Atrium is MIT, nothing requires it.
 *
 * The logo is a background, because which one fits depends on what it sits
 * on: the colour logo on a light background, the white one on a dark one.
 * atrium.css chooses it per theme and per surface (`--atrium-credit-logo`).
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    // What it sits on: the sidebar or the page background.
    on: { type: String, default: 'page' },
    // The collapsed sidebar has room for the logo only.
    compact: { type: Boolean, default: false },
})

const page = usePage()
const shown = computed(() => page.props.credit !== false)
const onSidebar = computed(() => props.on === 'sidebar')
</script>

<template>
    <div v-if="shown" data-credit :data-credit-on="onSidebar ? 'sidebar' : 'page'">
        <a
            data-credit-link
            href="https://alve.by/en/"
            target="_blank"
            rel="noopener"
            :aria-label="compact ? $t('layout.made_by') : undefined"
            :title="compact ? $t('layout.made_by') : undefined"
            class="inline-flex flex-col gap-1.5 rounded-md text-xs transition-colors focus:outline-none focus-visible:ring-2"
            :class="onSidebar
                ? 'items-start text-sidebar-muted hover:text-sidebar-fg focus-visible:ring-sidebar-accent'
                : 'items-center text-muted hover:text-fg focus-visible:ring-focus'"
        >
            <span
                data-credit-logo
                aria-hidden="true"
                class="block h-4 bg-contain bg-no-repeat"
                :class="compact ? 'w-12 bg-center' : ['w-[4.75rem]', onSidebar ? 'bg-left' : 'bg-center']"
                style="background-image: var(--atrium-credit-logo)"
            />
            <span v-if="!compact" data-credit-text>{{ $t('layout.made_by') }}</span>
        </a>
    </div>
</template>
