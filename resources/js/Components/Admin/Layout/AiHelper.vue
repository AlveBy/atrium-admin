<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { ajaxHeaders, responseErrorMessage } from '../../../Support/csrf'

/**
 * The helper in the corner of the admin: the Atrium arch come alive. A click
 * opens a chat that answers from the guides and reads the records of the
 * modules the admin may view (alveby/atrium-ai). Drawn only when that module
 * is installed and a provider has a key (the shared "ai.help" prop).
 */
const page = usePage()
const help = computed(() => page.props.ai?.help || null)
const labels = computed(() => help.value?.labels || {})
const demoNotice = computed(() => (page.props.ai?.demo ? page.props.ai?.labels?.demo_notice || '' : ''))
const pollUrl = computed(() => page.props.ai?.url || '')
const storageKey = computed(() => `atrium-ai-help:${page.props.auth?.user?.id ?? ''}`)

const open = ref(false)
const working = ref(false)
const question = ref('')
const messages = ref([])
const list = ref(null)
const input = ref(null)
const eyes = ref({ x: 0, y: 0 })
const mascot = ref(null)
let timer = null
let run = 0

// The conversation lives through page visits, not past the browser tab.
onMounted(() => {
    try {
        messages.value = JSON.parse(sessionStorage.getItem(storageKey.value) || '[]')
    } catch (e) {
        messages.value = []
    }

    window.addEventListener('pointermove', look)
})

onUnmounted(() => {
    stop()
    window.removeEventListener('pointermove', look)
})

watch(messages, (value) => {
    try {
        sessionStorage.setItem(storageKey.value, JSON.stringify(value.slice(-20)))
    } catch (e) {
        // A full or blocked storage only costs the history.
    }
}, { deep: true })

/** The pupils follow the pointer, a little. */
function look(event) {
    const box = mascot.value?.getBoundingClientRect()

    if (!box) {
        return
    }

    const dx = event.clientX - (box.left + box.width / 2)
    const dy = event.clientY - (box.top + box.height / 3)
    const length = Math.hypot(dx, dy) || 1

    eyes.value = { x: (dx / length) * 0.9, y: (dy / length) * 0.9 }
}

function stop() {
    clearTimeout(timer)
    timer = null
    run++
}

async function toggle() {
    open.value = !open.value

    if (open.value) {
        await nextTick()
        scrollDown()
        input.value?.focus()
    }
}

function clear() {
    stop()
    working.value = false
    messages.value = []
    input.value?.focus()
}

async function scrollDown() {
    await nextTick()

    if (list.value) {
        list.value.scrollTop = list.value.scrollHeight
    }
}

async function send() {
    const text = question.value.trim()

    if (text === '' || working.value) {
        return
    }

    const history = messages.value
        .filter((item) => !item.error)
        .slice(-10)
        .map(({ role, text: said }) => ({ role, text: said }))

    messages.value.push({ role: 'user', text })
    question.value = ''
    working.value = true
    scrollDown()

    stop()
    const current = run

    try {
        const response = await fetch(help.value.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ question: text, history }),
        })

        if (!response.ok) {
            throw new Error(await responseErrorMessage(response, labels.value.error))
        }

        settle(await response.json(), current)
    } catch (e) {
        if (current === run) {
            fail(e.message)
        }
    }
}

function settle(data, current) {
    if (current !== run) {
        return
    }

    if (data.status === 'done') {
        working.value = false
        messages.value.push({ role: 'assistant', text: data.value || '' })
        scrollDown()
        return
    }

    if (data.status === 'failed') {
        fail(data.message)
        return
    }

    timer = setTimeout(() => poll(data.id, current), 1500)
}

async function poll(id, current) {
    try {
        const response = await fetch(`${pollUrl.value}/${id}`, { credentials: 'same-origin', headers: ajaxHeaders() })

        if (!response.ok) {
            throw new Error(await responseErrorMessage(response, labels.value.error))
        }

        settle(await response.json(), current)
    } catch (e) {
        if (current === run) {
            fail(e.message)
        }
    }
}

function fail(message) {
    working.value = false
    messages.value.push({ role: 'assistant', text: message || labels.value.error, error: true })
    scrollDown()
}

function keydown(event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault()
        send()
    }
}

const escapes = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }
const escape = (text) => String(text).replace(/[&<>"']/g, (c) => escapes[c])

/**
 * The little Markdown the helper is asked for: paragraphs, "- " lists,
 * **bold** and links. Everything is escaped first; a link stays a link only
 * when it points inside this site.
 */
function render(text) {
    const inline = (line) => escape(line)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (all, label, href) => {
            const local = href.startsWith('/') && !href.startsWith('//')

            return local ? `<a href="${href}" class="underline text-accent hover:text-accent-hover" data-ai-link dusk="ai-link">${label}</a>` : label
        })

    const blocks = []
    let items = []

    const flush = () => {
        if (items.length) {
            blocks.push(`<ul class="list-disc pl-5 space-y-1">${items.map((item) => `<li>${item}</li>`).join('')}</ul>`)
            items = []
        }
    }

    for (const line of String(text).split(/\r?\n/)) {
        const item = line.match(/^\s*[-*]\s+(.*)$/)

        if (item) {
            items.push(inline(item[1]))
        } else {
            flush()

            if (line.trim() !== '') {
                blocks.push(`<p>${inline(line)}</p>`)
            }
        }
    }

    flush()

    return blocks.join('')
}

/** Links to the admin open as an Inertia visit, keeping the chat. */
function follow(event) {
    const link = event.target.closest?.('a[data-ai-link]')

    if (link) {
        event.preventDefault()
        router.visit(link.getAttribute('href'))
    }
}

function escapeKey(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false
    }
}
</script>

<template>
    <div v-if="help" class="fixed bottom-4 right-4 z-40 flex flex-col items-end gap-3" dusk="ai-helper" @keydown="escapeKey">
        <section
            v-if="open"
            class="flex w-[22rem] max-w-[calc(100vw-2rem)] max-h-[min(36rem,calc(100vh-8rem))] flex-col rounded-lg border border-line bg-surface text-fg shadow-xl"
            role="dialog"
            :aria-label="labels.title"
            dusk="ai-chat"
        >
            <header class="flex items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                <h2 class="text-sm font-semibold">{{ labels.title }}</h2>
                <div class="flex items-center gap-3 text-xs">
                    <button v-if="messages.length" type="button" class="text-muted hover:text-fg" dusk="ai-clear" @click="clear">{{ labels.clear }}</button>
                    <button type="button" class="text-muted hover:text-fg" :aria-label="labels.close" dusk="ai-close" @click="open = false">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 4.3 10 9l4.7-4.7 1 1L11 10l4.7 4.7-1 1L10 11l-4.7 4.7-1-1L9 10 4.3 5.3z" /></svg>
                    </button>
                </div>
            </header>

            <div ref="list" class="flex-1 space-y-3 overflow-y-auto px-4 py-3 text-sm" aria-live="polite" @click="follow">
                <p class="rounded-lg bg-surface-alt px-3 py-2">{{ labels.hello }}</p>

                <div
                    v-for="(message, index) in messages"
                    :key="index"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <p
                        v-if="message.role === 'user'"
                        class="max-w-[85%] whitespace-pre-wrap rounded-lg bg-accent-soft px-3 py-2 text-accent-soft-fg"
                        dusk="ai-question"
                    >{{ message.text }}</p>
                    <div
                        v-else
                        class="max-w-[90%] space-y-2 rounded-lg px-3 py-2"
                        :class="message.error ? 'border border-danger bg-danger-soft text-danger-soft-fg' : 'bg-surface-alt'"
                        :dusk="message.error ? 'ai-error' : 'ai-answer'"
                        v-html="render(message.text)"
                    />
                </div>

                <p v-if="working" class="text-muted" dusk="ai-thinking">{{ labels.thinking }}</p>
            </div>

            <form class="border-t border-line p-3" @submit.prevent="send">
                <div class="flex items-end gap-2">
                    <textarea
                        ref="input"
                        v-model="question"
                        rows="2"
                        maxlength="2000"
                        class="flex-1 resize-none rounded-md border border-field bg-surface px-3 py-2 text-sm text-fg focus:border-focus focus:outline-none focus:ring-1 focus:ring-focus"
                        :placeholder="labels.placeholder"
                        :aria-label="labels.placeholder"
                        dusk="ai-input"
                        @keydown="keydown"
                    />
                    <button
                        type="submit"
                        class="rounded-md bg-accent px-3 py-2 text-sm font-medium text-on-accent hover:bg-accent-hover disabled:opacity-50"
                        :disabled="working || question.trim() === ''"
                        dusk="ai-send"
                    >{{ labels.send }}</button>
                </div>
                <p v-if="demoNotice" class="mt-2 text-xs text-fg" dusk="ai-demo-notice">{{ demoNotice }}</p>
                <p class="mt-2 text-xs text-muted">{{ labels.note }}</p>
            </form>
        </section>

        <button
            ref="mascot"
            type="button"
            class="ai-mascot group relative h-16 w-16 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            :class="{ 'is-thinking': working, 'is-open': open }"
            :aria-label="labels.open"
            :aria-expanded="open"
            :title="labels.open"
            dusk="ai-mascot"
            @click="toggle"
        >
            <svg viewBox="0 0 48 52" class="h-full w-full overflow-visible" aria-hidden="true">
                <ellipse class="ai-mascot-shadow fill-fg" cx="24" cy="49" rx="15" ry="2.2" opacity="0.15" />
                <g class="ai-mascot-body">
                    <!-- The Atrium arch: the body and its two legs. -->
                    <path class="fill-fg" d="M6 42V22a18 18 0 0 1 36 0v20H32V22a8 8 0 0 0-16 0v20z" />
                    <!-- Its crossbar, the belt. -->
                    <rect class="ai-mascot-belt fill-accent" x="16" y="28" width="16" height="5" />
                    <!-- Feet. -->
                    <rect class="fill-fg" x="4" y="41" width="13" height="3.5" rx="1.75" />
                    <rect class="fill-fg" x="31" y="41" width="13" height="3.5" rx="1.75" />
                    <!-- Eyes on the arch. -->
                    <g class="ai-mascot-eyes">
                        <circle class="fill-surface" cx="19" cy="10.5" r="3" />
                        <circle class="fill-surface" cx="29" cy="10.5" r="3" />
                        <circle class="fill-fg" :cx="19 + eyes.x" :cy="10.5 + eyes.y" r="1.4" />
                        <circle class="fill-fg" :cx="29 + eyes.x" :cy="10.5 + eyes.y" r="1.4" />
                    </g>
                </g>
            </svg>
        </button>
    </div>
</template>

<style scoped>
.ai-mascot-body {
    transform-box: fill-box;
    transform-origin: 50% 100%;
    animation: ai-mascot-breathe 3.2s ease-in-out infinite;
}

.ai-mascot-eyes {
    transform-box: fill-box;
    transform-origin: 50% 50%;
    animation: ai-mascot-blink 5s infinite;
}

.ai-mascot-shadow {
    transform-box: fill-box;
    transform-origin: 50% 50%;
    animation: ai-mascot-shadow 3.2s ease-in-out infinite;
}

.ai-mascot:hover .ai-mascot-body,
.ai-mascot:focus-visible .ai-mascot-body {
    animation: ai-mascot-hop 0.6s ease-out;
}

.ai-mascot.is-thinking .ai-mascot-body {
    animation: ai-mascot-sway 1s ease-in-out infinite;
}

.ai-mascot.is-thinking .ai-mascot-belt {
    animation: ai-mascot-pulse 1s ease-in-out infinite;
}

@keyframes ai-mascot-breathe {
    0%, 100% { transform: scale(1, 1); }
    50% { transform: scale(1.03, 0.97); }
}

@keyframes ai-mascot-shadow {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.06); }
}

@keyframes ai-mascot-blink {
    0%, 92%, 100% { transform: scaleY(1); }
    95% { transform: scaleY(0.1); }
}

@keyframes ai-mascot-hop {
    0% { transform: translateY(0) scale(1, 1); }
    25% { transform: translateY(0) scale(1.08, 0.9); }
    55% { transform: translateY(-6px) scale(0.96, 1.05); }
    80% { transform: translateY(0) scale(1.04, 0.96); }
    100% { transform: translateY(0) scale(1, 1); }
}

@keyframes ai-mascot-sway {
    0%, 100% { transform: rotate(-4deg); }
    50% { transform: rotate(4deg); }
}

@keyframes ai-mascot-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}

@media (prefers-reduced-motion: reduce) {
    .ai-mascot-body,
    .ai-mascot-eyes,
    .ai-mascot-shadow,
    .ai-mascot-belt {
        animation: none !important;
    }
}
</style>
