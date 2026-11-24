<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * A tile a package draws with a Blade view (Atrium\Admin\Dashboard\HtmlGadget):
 * the panel gives it the frame and the title, the server the HTML.
 */
defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})
</script>

<template>
    <section class="bg-surface rounded-lg shadow px-5 py-4" data-gadget="html">
        <h2 class="text-sm font-semibold text-fg">{{ title }}</h2>
        <div class="mt-3 text-sm text-fg" data-gadget-html v-html="data.html" />
    </section>
</template>
