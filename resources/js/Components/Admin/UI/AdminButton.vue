<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    type: {
        type: String,
        default: 'button'
    },
    href: {
        type: String,
        default: null
    },
    variant: {
        type: String,
        default: 'primary', // primary, secondary, danger, success, warning
        validator: (value) => ['primary', 'secondary', 'danger', 'success', 'warning', 'outline'].includes(value)
    },
    size: {
        type: String,
        default: 'md', // sm, md, lg
        validator: (value) => ['sm', 'md', 'lg'].includes(value)
    },
    loading: Boolean,
    disabled: Boolean,
    block: Boolean,
})

const classes = computed(() => {
    const base = 'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed'

    const variants = {
        primary: 'bg-accent text-on-accent hover:bg-accent-hover focus:ring-focus',
        secondary: 'border border-line bg-off text-off-fg hover:border-field focus:ring-focus',
        danger: 'bg-danger text-on-danger hover:bg-danger-hover focus:ring-danger',
        success: 'border border-success bg-success-soft text-success-soft-fg hover:border-fg focus:ring-focus',
        warning: 'border border-warning-soft-fg bg-warning-soft text-warning-soft-fg hover:border-fg focus:ring-focus',
        outline: 'border border-field bg-surface text-fg hover:bg-hover focus:ring-focus',
    }

    const sizes = {
        sm: 'px-2.5 py-1.5 text-xs',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base',
    }

    return [
        base,
        variants[props.variant],
        sizes[props.size],
        props.block ? 'w-full' : '',
    ].join(' ')
})

const component = computed(() => props.href ? Link : 'button')
</script>

<template>
    <component
        :is="component"
        :href="href"
        :type="href ? null : type"
        :disabled="disabled || loading"
        :class="classes"
    >
        <svg
            v-if="loading"
            class="animate-spin -ml-1 mr-2 h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <slot />
    </component>
</template>
