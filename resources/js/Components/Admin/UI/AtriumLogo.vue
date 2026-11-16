<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

// The Atrium mark, variant B "Arch": an arched entrance that is also the
// letter A. `on` picks the colours: the sidebar's own logo colours, or the
// text and accent colours of a page surface (sign-in, two-factor).
defineProps({
    name: { type: String, default: 'Atrium' },
    on: { type: String, default: 'sidebar' }, // sidebar | surface
    size: { type: Number, default: 28 },
    wordmark: { type: Boolean, default: true },
})
</script>

<template>
    <span class="inline-flex items-center gap-2.5" data-atrium-logo>
        <svg
            :width="size"
            :height="size"
            viewBox="0 0 48 48"
            aria-hidden="true"
            class="block flex-none"
        >
            <path
                d="M6 42V22a18 18 0 0 1 36 0v20H32V22a8 8 0 0 0-16 0v20z"
                :class="on === 'surface' ? 'fill-fg' : 'fill-mark'"
            />
            <rect
                x="16"
                y="28"
                width="16"
                height="5"
                :class="on === 'surface' ? 'fill-accent' : 'fill-mark-accent'"
            />
        </svg>
        <span
            v-if="wordmark"
            class="font-brand font-semibold tracking-[-0.01em] truncate"
            :class="on === 'surface' ? 'text-fg' : 'text-sidebar-fg'"
            :style="{ fontSize: Math.round(size * 0.72) + 'px' }"
        >{{ name }}</span>
    </span>
</template>
