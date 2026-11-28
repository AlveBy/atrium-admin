<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ajaxHeaders, responseErrorMessage } from '../../../Support/csrf'
import { t } from '@atrium/Support/i18n'

/*
 * The page as the site draws it: the server renders the tree of the editor
 * with the theme, and a frame shows the result. A click picks the node under
 * it; links and forms of the page do nothing here, and its scripts do not run
 * (the server forbids them).
 */
const props = defineProps({
    url: { type: String, required: true },
    tree: { type: [Object, Array, String], default: null },
    language: { type: String, required: true },
    selected: { type: String, default: null },
    // What the mark on the selected node says.
    labelOf: { type: Function, required: true },
})

const emit = defineEmits(['select', 'drawn'])

/** The narrowest page drawn; a smaller frame shows it scaled down. */
const PAGE = 800
const PAUSE = 400
const NODES = '[data-block-id], [data-column-id], [data-section-id]'
// A dashed frame with a light edge around it, seen on any background, and the
// node's name above it, so that it covers nothing of the node.
const MARKS = `
[data-section-id], [data-column-id], [data-block-id] { cursor: pointer; }
[data-builder-selected] { position: relative; outline: 3px dashed #111827; outline-offset: 2px; box-shadow: 0 0 0 7px #ffffff; }
[data-builder-selected]::before { content: attr(data-builder-selected); position: absolute; bottom: calc(100% + 5px); left: -5px; z-index: 2147483647; padding: 2px 8px; background: #111827; color: #ffffff; font: 600 13px/1.4 system-ui, sans-serif; white-space: nowrap; pointer-events: none; }
`

const box = ref(null)
const frame = ref(null)
const html = ref('')
const state = ref('loading')
const error = ref('')
const width = ref(PAGE)

const scale = computed(() => Math.min(1, width.value / PAGE))
const size = computed(() => ({
    width: `${Math.max(width.value, PAGE)}px`,
    height: `${100 / scale.value}%`,
    transform: `scale(${scale.value})`,
}))

let timer = null
let request = null
let scrolled = 0
let observer = null

async function draw() {
    request?.abort()
    const mine = new AbortController()
    request = mine

    try {
        const response = await fetch(props.url, {
            method: 'POST',
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ tree: props.tree ?? null, language: props.language }),
            signal: mine.signal,
        })

        if (!response.ok) {
            throw new Error(await responseErrorMessage(response, t('builder.preview.failed')))
        }

        const page = await response.text()

        if (mine !== request) return

        error.value = ''

        // The same page again: the frame stays, and tells of no load.
        if (page === html.value) {
            state.value = 'ready'
            return
        }

        scrolled = frame.value?.contentWindow?.scrollY ?? 0
        html.value = page
    } catch (problem) {
        if (mine !== request) return

        error.value = problem instanceof TypeError ? t('builder.preview.failed') : problem.message
        state.value = 'failed'
    }
}

function schedule() {
    state.value = 'loading'
    clearTimeout(timer)
    timer = setTimeout(draw, PAUSE)
}

function picked(event) {
    event.preventDefault()
    event.stopPropagation()

    const node = event.target.closest?.(NODES)

    if (node) {
        emit('select', node.dataset.blockId ?? node.dataset.columnId ?? node.dataset.sectionId)
    }
}

function mark(show) {
    const view = frame.value?.contentWindow
    const page = frame.value?.contentDocument
    if (!view || !page) return

    page.querySelector('[data-builder-selected]')?.removeAttribute('data-builder-selected')

    if (!props.selected) return

    const id = CSS.escape(props.selected)
    const node = page.querySelector(`[data-block-id="${id}"], [data-column-id="${id}"], [data-section-id="${id}"]`)
    if (!node) return

    node.setAttribute('data-builder-selected', props.labelOf(props.selected))

    // The frame scrolls, not the editor around it.
    const place = node.getBoundingClientRect()

    if (show && (place.top < 0 || place.top > view.innerHeight - 60)) {
        view.scrollBy(0, place.top - 40)
    }
}

function loaded() {
    const page = frame.value?.contentDocument
    if (!page || !html.value) return

    const style = page.createElement('style')
    style.textContent = MARKS
    page.head.appendChild(style)

    page.addEventListener('click', picked, true)
    page.addEventListener('auxclick', picked, true)
    page.addEventListener('submit', (event) => event.preventDefault(), true)

    frame.value.contentWindow.scrollTo(0, scrolled)
    emit('drawn', [...page.querySelectorAll('[data-block-id]')].map((node) => node.dataset.blockId))
    mark(false)
    state.value = 'ready'
}

watch(() => [JSON.stringify(props.tree ?? null), props.language], schedule)
watch(() => props.selected, () => mark(true))

onMounted(() => {
    observer = new ResizeObserver(([entry]) => { width.value = entry.contentRect.width || PAGE })
    observer.observe(box.value)
    draw()
})

onBeforeUnmount(() => {
    clearTimeout(timer)
    request?.abort()
    request = null
    observer?.disconnect()
})
</script>

<template>
    <section :aria-label="t('builder.preview.title')" :aria-busy="state === 'loading' ? 'true' : 'false'" dusk="builder-preview" :data-state="state">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-fg">{{ t('builder.preview.title') }}</h2>
            <p class="text-sm text-muted" role="status">
                <template v-if="state === 'loading'">⟳ {{ t('builder.preview.updating') }}</template>
                <template v-else-if="state === 'ready'">{{ t('builder.preview.hint') }}</template>
            </p>
        </div>

        <p v-if="state === 'failed'" role="alert" class="mb-2 rounded-md border-2 border-danger bg-danger-soft px-3 py-2 text-sm font-medium text-danger-soft-fg" dusk="builder-preview-error">⚠ {{ error }}</p>

        <div ref="box" class="relative h-[75vh] overflow-hidden rounded-md border border-field bg-surface">
            <iframe
                ref="frame"
                :srcdoc="html"
                :style="size"
                :title="t('builder.preview.title')"
                sandbox="allow-same-origin allow-scripts"
                class="absolute left-0 top-0 origin-top-left border-0"
                dusk="builder-frame"
                @load="loaded"
            />
        </div>
    </section>
</template>
