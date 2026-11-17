<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminUserDropdown from './AdminUserDropdown.vue'
import LocaleSwitcher from './LocaleSwitcher.vue'
import SiteSwitcher from './SiteSwitcher.vue'

const props = defineProps({
    user: {
        type: Object,
        default: () => ({})
    },
    branding: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['toggle-sidebar', 'toggle-collapse'])
</script>

<template>
    <!--
        transform-gpu gives the bar a layer of its own: WebKit otherwise
        paints sticky table cells inside a scrolling list above it.
    -->
    <header class="sticky top-0 z-30 transform-gpu h-16 bg-surface border-b border-line flex items-center justify-between gap-1 px-3 sm:gap-2 sm:px-4 lg:px-6">
        <!-- Left side -->
        <div class="flex shrink-0 items-center gap-1 sm:gap-4">
            <!-- Mobile menu button -->
            <button
                @click="$emit('toggle-sidebar')"
                class="lg:hidden p-2 rounded-md text-muted hover:text-fg hover:bg-hover"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Collapse toggle (desktop) -->
            <button
                data-action="collapse-sidebar"
                @click="$emit('toggle-collapse')"
                class="hidden lg:block p-2 rounded-md text-muted hover:text-fg hover:bg-hover"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                </svg>
            </button>

            <!--
                The way to the site. The Bitrix admin always had one; here the
                link only went one way: the public site has an admin bar, but
                there was nothing to get from the panel to the site.

                A plain <a>, not an Inertia link: the public site is a separate
                Blade application that an SPA visit would not render. A new tab,
                so the list open in the panel is not lost.
            -->
            <a
                data-action="visit-site"
                :href="$page.props.currentSite?.url || '/'"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm text-muted hover:bg-hover hover:text-fg"
                :title="$t('layout.visit_site_hint')"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18" />
                </svg>
                <span class="hidden sm:inline">{{ $t('layout.visit_site') }}</span>
            </a>
        </div>

        <!--
            Right side. Narrow gaps on a phone: with the old ones the header was
            wider than the screen and the browser zoomed the whole page out.
        -->
        <div class="flex min-w-0 items-center gap-1 sm:gap-2 lg:gap-3">
            <SiteSwitcher />
            <LocaleSwitcher />

            <!-- User dropdown -->
            <AdminUserDropdown :user="user" />
        </div>
    </header>
</template>
