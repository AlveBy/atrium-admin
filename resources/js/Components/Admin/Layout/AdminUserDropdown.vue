<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { applyTheme } from '../../../Support/theme'

const props = defineProps({
    user: {
        type: Object,
        default: () => ({})
    }
})

const isOpen = ref(false)
const dropdownRef = ref(null)

const toggle = () => {
    isOpen.value = !isOpen.value
}

const close = () => {
    isOpen.value = false
}

const page = usePage()
const themes = computed(() => page.props.themes || [])
const currentTheme = computed(() => page.props.theme)

// Applied at once, then kept on the account.
const pickTheme = (theme) => {
    applyTheme(theme)
    router.put(route('admin.profile.theme'), { theme }, {
        preserveScroll: true,
        preserveState: true,
    })
}

const logout = () => {
    router.post(route('admin.logout'))
}

// Handle click outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        close()
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div ref="dropdownRef" class="relative shrink-0">
        <!-- Trigger -->
        <button
            data-action="user-menu"
            @click="toggle"
            :aria-expanded="isOpen ? 'true' : 'false'"
            class="flex items-center gap-3 p-2 rounded-lg hover:bg-hover transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
        >
            <div class="w-8 h-8 rounded-full bg-accent-soft flex items-center justify-center text-accent-soft-fg font-semibold">
                {{ user.name?.charAt(0)?.toUpperCase() || 'A' }}
            </div>
            <div class="hidden md:block text-left">
                <p class="text-sm font-medium text-fg">{{ user.name }}</p>
                <p class="text-xs text-muted">{{ user.email }}</p>
            </div>
            <svg class="hidden sm:block w-4 h-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown menu -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-56 bg-surface rounded-lg shadow-lg border border-line py-1 z-50"
            >
                <!-- User info -->
                <div class="px-4 py-3 border-b border-line">
                    <p class="text-sm font-medium text-fg">{{ user.name }}</p>
                    <p class="text-xs text-muted truncate">{{ user.email }}</p>
                </div>

                <!-- Menu items -->
                <div class="py-1">
                    <Link
                        :href="route('admin.profile.edit')"
                        class="flex items-center px-4 py-2 text-sm text-fg hover:bg-hover"
                        @click="close"
                    >
                        <svg class="w-4 h-4 mr-3 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {{ $t('layout.user_menu.profile') }}
                    </Link>
                </div>

                <!-- Colour theme -->
                <div class="border-t border-line py-1" role="radiogroup" :aria-label="$t('layout.theme.label')">
                    <p class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-muted">
                        {{ $t('layout.theme.label') }}
                    </p>
                    <button
                        v-for="theme in themes"
                        :key="theme"
                        type="button"
                        role="radio"
                        :aria-checked="theme === currentTheme ? 'true' : 'false'"
                        :data-theme-option="theme"
                        class="flex items-center w-full gap-3 px-4 py-2 text-sm text-fg hover:bg-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus"
                        :class="theme === currentTheme ? 'font-semibold' : ''"
                        @click="pickTheme(theme)"
                    >
                        <!-- A tiny preview drawn by the theme itself: its sidebar and accent. -->
                        <span
                            v-if="theme !== 'system'"
                            :data-theme="theme"
                            class="flex h-4 w-6 flex-none overflow-hidden rounded-sm ring-1 ring-field"
                            aria-hidden="true"
                        >
                            <span class="w-2 bg-sidebar" />
                            <span class="flex flex-1 items-center justify-center bg-page">
                                <span class="h-1.5 w-1.5 rounded-full bg-accent" />
                            </span>
                        </span>
                        <span v-else class="flex h-4 w-6 flex-none overflow-hidden rounded-sm ring-1 ring-field" aria-hidden="true">
                            <span data-theme="graphite" class="flex-1 bg-page" />
                            <span data-theme="night" class="flex-1 bg-page" />
                        </span>
                        <span class="flex-1 text-left">{{ $t(`layout.theme.${theme}`) }}</span>
                        <!-- The choice is marked by a shape, not only by weight. -->
                        <svg v-if="theme === currentTheme" class="h-4 w-4 flex-none text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>

                <!-- Logout -->
                <div class="border-t border-line py-1">
                    <button
                        data-action="logout"
                        @click="logout"
                        class="flex items-center w-full px-4 py-2 text-sm text-danger hover:bg-danger-soft"
                    >
                        <svg class="w-4 h-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        {{ $t('layout.user_menu.logout') }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
