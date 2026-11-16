<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()

const sites = computed(() => page.props.sites || [])
const current = computed(() => page.props.currentSite?.id)

const change = (event) => {
    router.post(route('admin.site'), { site_id: event.target.value }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <!-- One site: nothing to switch. -->
    <select
        v-if="sites.length > 1"
        data-site-switcher
        name="site_id"
        :aria-label="$t('layout.site')"
        :value="current"
        class="rounded-md border border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        @change="change"
    >
        <option v-for="site in sites" :key="site.id" :value="site.id">
            {{ site.name }}
        </option>
    </select>
</template>
