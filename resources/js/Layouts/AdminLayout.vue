<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { inject } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminShell from './AdminShell.vue'
import { shellKey } from '../Support/shell'

/**
 * The wrapper pages used to put around themselves. The frame is now the
 * default layout of the app and lives on between visits (AdminShell), so a
 * page needs no wrapper and sets its title with <Head>.
 *
 * Kept so that a page that still wraps itself keeps working: inside the
 * frame it only sets the title, and outside it (a page that opted out of the
 * default layout) it brings the frame along. Either way there is one frame.
 */
defineProps({
    title: {
        type: String,
        default: ''
    }
})

const framed = inject(shellKey, false)
</script>

<template>
    <Head :title="title || ''" />

    <slot v-if="framed" />
    <AdminShell v-else>
        <slot />
    </AdminShell>
</template>
