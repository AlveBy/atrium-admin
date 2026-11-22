<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { nextTick, ref, useId, watch } from 'vue'

const props = defineProps({
    show: Boolean,
    maxWidth: {
        type: String,
        default: 'md', // sm, md, lg, xl, 2xl
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    title: String,
})

const emit = defineEmits(['close'])

const close = () => {
    if (props.closeable) {
        emit('close')
    }
}

const maxWidthClass = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
}

const titleId = useId()
const panel = ref(null)
// Where the keyboard was before the window opened: it goes back there.
let returnFocus = null

watch(() => props.show, async (show) => {
    if (show) {
        document.body.style.overflow = 'hidden'
        returnFocus = document.activeElement
        await nextTick()
        // The last button of the footer is the window's answer ("Delete",
        // "Continue"); without a footer, the first control inside.
        const buttons = panel.value?.querySelectorAll('[data-modal-footer] button')
        const target = buttons?.length ? buttons[buttons.length - 1] : panel.value?.querySelector('button, [href], input, select, textarea')
        target?.focus()
    } else {
        document.body.style.overflow = ''
        returnFocus?.focus?.()
        returnFocus = null
    }
})

// Tab stays inside the window while it is open.
const trapTab = (event) => {
    const controls = [...(panel.value?.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select, textarea') || [])]
    if (!controls.length) return
    const first = controls[0]
    const last = controls[controls.length - 1]
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                data-modal="true"
                class="fixed inset-0 z-50 overflow-y-auto"
                @keydown.esc="close"
                @keydown.tab="trapTab"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="close"
                />

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div
                            v-if="show"
                            ref="panel"
                            role="dialog"
                            aria-modal="true"
                            :aria-labelledby="title ? titleId : null"
                            class="relative transform overflow-hidden rounded-lg bg-surface shadow-xl transition-all sm:my-8 sm:w-full"
                            :class="maxWidthClass[maxWidth]"
                        >
                            <!-- Header -->
                            <div v-if="title || $slots.header" class="px-6 py-4 border-b border-line">
                                <slot name="header">
                                    <h3 :id="titleId" class="text-lg font-medium text-fg">{{ title }}</h3>
                                </slot>
                                <button
                                    v-if="closeable"
                                    type="button"
                                    @click="close"
                                    class="absolute top-4 right-4 rounded text-muted hover:text-fg focus:outline-none focus:ring-2 focus:ring-focus"
                                    :aria-label="$t('common.close')"
                                >
                                    <svg class="h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Content -->
                            <div class="p-6">
                                <slot />
                            </div>

                            <!-- Footer -->
                            <div v-if="$slots.footer" data-modal-footer class="px-6 py-4 border-t border-line bg-surface-alt flex justify-end space-x-3">
                                <slot name="footer" />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
