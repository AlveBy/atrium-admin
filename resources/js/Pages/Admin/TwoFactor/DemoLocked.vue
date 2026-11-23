<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * "My sign-in security" on a public demo (docs/demo.md): everybody is the
 * same person there, so nothing may be changed and the page has no forms,
 * only a word on why.
 */
import { Head } from '@inertiajs/vue3'
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
</script>

<template>
    <Head :title="l('security.title')" />
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-fg">{{ l('security.title') }}</h1>
    </div>

    <div dusk="a2f-security" class="max-w-3xl">
        <p role="status" dusk="a2f-demo-locked" class="p-4 bg-surface border border-line text-fg rounded">{{ l('security.demo_locked') }}</p>
    </div>
</template>
