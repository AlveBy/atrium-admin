<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Admin dashboard — gadget tiles; the set is configured in
 * config/atrium.php → dashboard.gadgets.
 *
 * The tiles themselves live in Components/Admin/Dashboard, this is only the layout.
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

// A gadget from the config may name a component that is not here — skip it
// rather than break the whole page.
const visible = computed(() => props.gadgets.filter((gadget) => COMPONENTS[gadget.component]))
</script>

<template>
    <AdminLayout :title="$t('dashboard.title')">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ $t('dashboard.title') }}</h1>
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
