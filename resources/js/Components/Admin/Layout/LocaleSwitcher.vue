<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()

const locales = computed(() => page.props.locales || {})
const current = computed(() => page.props.locale)

const change = (event) => {
    router.post(route('admin.locale'), { locale: event.target.value }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <select
        data-locale-switcher
        name="locale"
        :aria-label="$t('common.language')"
        :value="current"
        class="rounded-md border border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        @change="change"
    >
        <option v-for="(name, code) in locales" :key="code" :value="code" :lang="code.replace('_', '-')">
            {{ name }}
        </option>
    </select>
</template>
