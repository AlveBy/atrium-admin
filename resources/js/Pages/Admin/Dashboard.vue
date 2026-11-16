<script setup>
/**
 * Рабочий стол админки — плитки-гаджеты, набор задаётся в
 * config/atrium.php → dashboard.gadgets.
 *
 * Сами плитки живут в пакете admin-panel, здесь только раскладка.
 */
import { computed } from 'vue'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import SiteSpeedGadget from '@atrium/Components/Admin/Dashboard/SiteSpeedGadget.vue'
import PerformanceGadget from '@atrium/Components/Admin/Dashboard/PerformanceGadget.vue'
import RecentElementsGadget from '@atrium/Components/Admin/Dashboard/RecentElementsGadget.vue'

const COMPONENTS = {
    SiteSpeedGadget,
    PerformanceGadget,
    RecentElementsGadget,
}

const props = defineProps({
    gadgets: {
        type: Array,
        default: () => [],
    },
})

// Гаджет из конфига может ссылаться на компонент, которого здесь нет, —
// пропускаем такой, а не роняем всю страницу.
const visible = computed(() => props.gadgets.filter((gadget) => COMPONENTS[gadget.component]))
</script>

<template>
    <AdminLayout title="Рабочий стол">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Рабочий стол</h1>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <component
                :is="COMPONENTS[gadget.component]"
                v-for="gadget in visible"
                :key="gadget.key"
                :title="gadget.title"
                :data="gadget.data"
                :class="gadget.width === 'full' ? 'lg:col-span-2' : ''"
            />
        </div>
    </AdminLayout>
</template>
