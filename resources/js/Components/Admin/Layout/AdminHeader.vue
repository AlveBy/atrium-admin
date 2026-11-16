<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminUserDropdown from './AdminUserDropdown.vue'

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
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-6">
        <!-- Left side -->
        <div class="flex items-center space-x-4">
            <!-- Mobile menu button -->
            <button
                @click="$emit('toggle-sidebar')"
                class="lg:hidden p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Collapse toggle (desktop) -->
            <button
                @click="$emit('toggle-collapse')"
                class="hidden lg:block p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                </svg>
            </button>

            <!--
                Дорога на сайт. В Битриксе она в админке была всегда, у нас же
                обратная ссылка существовала только в одну сторону: панель
                администратора на публичной части есть, а уйти на неё из
                админки было нечем (просьба заказчика 15.08.2026).

                Обычная <a>, не Inertia-ссылка: публичная часть — отдельное
                приложение на Blade, и SPA-переход её не отрисует. Новой
                вкладкой, чтобы открытый список заявок не терялся.
            -->
            <a
                data-action="visit-site"
                href="/"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                title="Открыть сайт в новой вкладке"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18" />
                </svg>
                <span class="hidden sm:inline">Перейти на сайт</span>
            </a>
        </div>

        <!-- Right side -->
        <div class="flex items-center space-x-4">
            <!-- User dropdown -->
            <AdminUserDropdown :user="user" />
        </div>
    </header>
</template>
