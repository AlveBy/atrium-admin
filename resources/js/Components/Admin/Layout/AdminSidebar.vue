<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AdminMenuItem from './AdminMenuItem.vue'
import AtriumLogo from '../UI/AtriumLogo.vue'

const props = defineProps({
    open: Boolean,
    collapsed: Boolean,
    menu: {
        type: Array,
        default: () => []
    },
    branding: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['close'])

const page = usePage()

const currentRoute = computed(() => page.url)
</script>

<template>
    <!-- Mobile sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-sidebar text-sidebar-fg border-r border-sidebar-line transform transition-transform duration-300 lg:translate-x-0"
        :class="[
            open ? 'translate-x-0' : '-translate-x-full',
            collapsed ? 'lg:w-20' : 'lg:w-64',
            'w-64'
        ]"
    >
        <!-- Logo / Branding -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-sidebar-line">
            <Link
                :href="route('admin.dashboard')"
                data-sidebar-brand
                class="flex min-w-0 items-center space-x-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-accent"
            >
                <img
                    v-if="branding.logo"
                    :src="branding.logo"
                    :alt="branding.name"
                    class="h-8 w-auto"
                />
                <span
                    v-if="branding.logo && !collapsed"
                    class="text-xl font-bold truncate"
                >
                    {{ branding.name || $t('layout.brand_short') }}
                </span>
                <!-- No logo of their own: the Atrium mark with the panel name. -->
                <AtriumLogo
                    v-if="!branding.logo"
                    :name="branding.name || 'Atrium'"
                    :wordmark="!collapsed"
                    :size="28"
                />
            </Link>

            <!-- Close button (mobile) -->
            <button
                @click="$emit('close')"
                class="lg:hidden p-2 rounded-md text-sidebar-muted hover:text-sidebar-fg hover:bg-sidebar-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-accent"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4 px-3">
            <ul class="space-y-1">
                <AdminMenuItem
                    v-for="item in menu"
                    :key="item.route"
                    :item="item"
                    :collapsed="collapsed"
                />
            </ul>
        </nav>

        <!-- User info (mobile only) -->
        <div class="lg:hidden border-t border-sidebar-line p-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-sidebar-active text-sidebar-active-fg flex items-center justify-center">
                    <span class="text-lg font-medium">{{ $page.props.auth?.user?.name?.charAt(0) || 'A' }}</span>
                </div>
                <div v-if="!collapsed">
                    <p class="text-sm font-medium">{{ $page.props.auth?.user?.name }}</p>
                    <p class="text-xs text-sidebar-muted">{{ $page.props.auth?.user?.email }}</p>
                </div>
            </div>
        </div>
    </aside>
</template>
