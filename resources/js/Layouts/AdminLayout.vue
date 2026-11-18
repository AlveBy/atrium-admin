<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { ref, computed } from 'vue'
import { usePage, Head } from '@inertiajs/vue3'
import AdminSidebar from '../Components/Admin/Layout/AdminSidebar.vue'
import AdminHeader from '../Components/Admin/Layout/AdminHeader.vue'
import AiHelper from '../Components/Admin/Layout/AiHelper.vue'

const props = defineProps({
    title: {
        type: String,
        default: ''
    }
})

const page = usePage()

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)

const branding = computed(() => page.props.branding || {})
const menu = computed(() => page.props.menu || [])
const user = computed(() => page.props.auth?.user || {})

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
}

const toggleCollapse = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value
}

const closeSidebar = () => {
    sidebarOpen.value = false
}
</script>

<template>
    <Head :title="title || ''" />

    <div class="min-h-screen bg-page">
        <!-- Mobile sidebar overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="closeSidebar"
        />

        <!-- Sidebar -->
        <AdminSidebar
            :open="sidebarOpen"
            :collapsed="sidebarCollapsed"
            :menu="menu"
            :branding="branding"
            @close="closeSidebar"
        />

        <!-- Main content area -->
        <div
            class="transition-all duration-300"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
        >
            <!-- Header -->
            <AdminHeader
                :user="user"
                :branding="branding"
                @toggle-sidebar="toggleSidebar"
                @toggle-collapse="toggleCollapse"
            />

            <!-- Page content -->
            <main data-page-content="true" class="p-4 lg:p-6">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" data-flash="success" class="mb-4 p-4 bg-success-soft border border-success text-success-soft-fg rounded">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" data-flash="error" class="mb-4 p-4 bg-danger-soft border border-danger text-danger-soft-fg rounded">
                    {{ $page.props.flash.error }}
                </div>

                <slot />
            </main>
        </div>

        <AiHelper />
    </div>
</template>
