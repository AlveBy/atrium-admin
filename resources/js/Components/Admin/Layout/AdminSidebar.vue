<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AdminMenuItem from './AdminMenuItem.vue'

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
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-gray-900 text-white transform transition-transform duration-300 lg:translate-x-0"
        :class="[
            open ? 'translate-x-0' : '-translate-x-full',
            collapsed ? 'lg:w-20' : 'lg:w-64',
            'w-64'
        ]"
    >
        <!-- Logo / Branding -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-gray-800">
            <Link
                :href="route('admin.dashboard')"
                class="flex items-center space-x-3"
            >
                <img
                    v-if="branding.logo"
                    :src="branding.logo"
                    :alt="branding.name"
                    class="h-8 w-auto"
                />
                <span
                    v-if="!collapsed"
                    class="text-xl font-bold truncate"
                >
                    {{ branding.name || $t('layout.brand_short') }}
                </span>
            </Link>

            <!-- Close button (mobile) -->
            <button
                @click="$emit('close')"
                class="lg:hidden p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-800"
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
        <div class="lg:hidden border-t border-gray-800 p-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center">
                    <span class="text-lg font-medium">{{ $page.props.auth?.user?.name?.charAt(0) || 'A' }}</span>
                </div>
                <div v-if="!collapsed">
                    <p class="text-sm font-medium">{{ $page.props.auth?.user?.name }}</p>
                    <p class="text-xs text-gray-400">{{ $page.props.auth?.user?.email }}</p>
                </div>
            </div>
        </div>
    </aside>
</template>
