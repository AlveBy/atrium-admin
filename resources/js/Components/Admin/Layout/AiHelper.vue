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
 *
 * Playful (Modules → AI, on by default), its face shows what it is doing:
 * brows up and eyes darting while it thinks, a short smile when the answer
 * comes, a sorry look on an error, a wink on hover, a magnifier while it
 * searches the records, glasses over the guides, a pencil while a field is
 * written, a hat at New Year, and now and then a yawn, a look round or a hop.
 * It never opens or speaks on its own. Reduced motion keeps the faces and
 * drops the movement; switched off, it is the calm arch.
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

const playful = computed(() => Boolean(help.value?.playful))
const mood = ref('idle')
const prop = ref(null)
const trick = ref(null)
const reduced = ref(false)
const timers = { mood: null, prop: null, trick: null }
let motion = null
let step = null

/** How long a passing face or costume stays, in milliseconds. */
const LASTS = { happy: 2500, sorry: 4000, hello: 1200, prop: 1800, trick: 1600 }

/** A trick comes after one to two quiet minutes. */
const QUIET = [60000, 120000]

/** The pupils' place by mood; null follows the pointer. */
const GAZE = { thinking: { x: 0, y: -1.1 }, sorry: { x: 0, y: 1 } }

/** What a screen reader hears; the drawing itself is hidden from it. */
const PROP_SAYS = { glass: 'search', glasses: 'read', pencil: 'write' }
const status = computed(() => {
    const said = labels.value.mood || {}
    const key = PROP_SAYS[prop.value] || mood.value

    return said[key] || ''
})

/** Brows by mood: [x1, y1, x2, y2] of the left one and the right one. */
const BROWS = {
    idle: [[18, 6.3, 21.4, 6.3], [26.6, 6.3, 30, 6.3]],
    thinking: [[18, 5.8, 21.4, 5.2], [26.6, 5.2, 30, 5.8]],
    happy: [[18, 6, 21.4, 5.7], [26.6, 5.7, 30, 6]],
    sorry: [[18, 6.9, 21.4, 5.2], [26.6, 5.2, 30, 6.9]],
    hello: [[18, 5.7, 21.4, 5.2], [26.6, 6.6, 30, 6.6]],
    yawn: [[18, 5.8, 21.4, 5.4], [26.6, 5.4, 30, 5.8]],
}
const face = computed(() => (trick.value === 'yawn' ? 'yawn' : mood.value))
const brows = computed(() => BROWS[face.value] || BROWS.idle)
const gaze = computed(() => {
    if (prop.value === 'glasses') {
        return { x: 0, y: 1.1 }
    }

    if (prop.value === 'pencil') {
        return { x: 0.9, y: 0.6 }
    }

    return (playful.value && GAZE[mood.value]) || eyes.value
})

// The conversation lives through page visits, not past the browser tab.
onMounted(() => {
    try {
        messages.value = JSON.parse(sessionStorage.getItem(storageKey.value) || '[]')
    } catch (e) {
        messages.value = []
    }

    window.addEventListener('pointermove', look)
    window.addEventListener('atrium:ai-writing', writing)

    motion = window.matchMedia?.('(prefers-reduced-motion: reduce)') || null
    reduced.value = Boolean(motion?.matches)
    motion?.addEventListener?.('change', moved)

    if (help.value?.holiday === 'new_year') {
        dress('hat')
    }

    rest()
})

onUnmounted(() => {
    stop()
    window.removeEventListener('pointermove', look)
    window.removeEventListener('atrium:ai-writing', writing)
    motion?.removeEventListener?.('change', moved)
    Object.values(timers).forEach(clearTimeout)
})

function moved(event) {
    reduced.value = event.matches
    rest()
}

/** A face for a while, then back to rest; null keeps it until changed. */
function feel(next, lasts = null) {
    clearTimeout(timers.mood)
    clearTimeout(timers.trick)
    trick.value = null
    mood.value = next

    if (lasts) {
        timers.mood = setTimeout(() => {
            mood.value = 'idle'
            rest()
        }, lasts)
    }
}

/** A costume for a moment: only the playful helper dresses up. */
function dress(name) {
    if (!playful.value) {
        return
    }

    clearTimeout(timers.prop)
    prop.value = name
    timers.prop = setTimeout(() => {
        prop.value = null
    }, LASTS.prop)
}

/** At rest the next trick waits for a quiet minute or two. */
function rest() {
    clearTimeout(timers.trick)

    if (!playful.value || reduced.value || mood.value !== 'idle') {
        return
    }

    timers.trick = setTimeout(play, QUIET[0] + Math.random() * (QUIET[1] - QUIET[0]))
}

function play() {
    if (mood.value !== 'idle' || open.value || document.visibilityState === 'hidden') {
        rest()
        return
    }

    trick.value = ['yawn', 'look', 'hop'][Math.floor(Math.random() * 3)]
    timers.trick = setTimeout(() => {
        trick.value = null
        rest()
    }, LASTS.trick)
}

/** A wink for whoever comes near, when it has nothing else to show. */
function greet() {
    if (mood.value === 'idle') {
        feel('hello', LASTS.hello)

        if (help.value?.holiday === 'new_year') {
            dress('hat')
        }
    }
}

function writing() {
    dress('pencil')
}

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

    if (!box || reduced.value) {
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
    feel('idle')
    rest()
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
    step = null
    feel('thinking')
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
        feel('happy', LASTS.happy)
        scrollDown()
        return
    }

    if (data.status === 'failed') {
        fail(data.message)
        return
    }

    // What it is busy with shows on its face: a magnifier or glasses.
    if (data.step && data.step !== step) {
        step = data.step
        dress(step === 'read' ? 'glasses' : 'glass')
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
    feel('sorry', LASTS.sorry)
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
            :data-playful="playful ? 'true' : 'false'"
            dusk="ai-mascot"
            @click="toggle"
            @pointerenter="greet"
            @focus="greet"
        >
            <svg
                viewBox="0 0 48 52"
                class="h-full w-full overflow-visible"
                aria-hidden="true"
                :data-mood="playful ? mood : null"
                :data-prop="prop"
                :data-trick="trick"
                dusk="ai-mascot-face"
            >
                <ellipse class="ai-mascot-shadow fill-fg" cx="24" cy="49" rx="15" ry="2.2" opacity="0.15" />
                <g class="ai-mascot-body">
                    <!-- The Atrium arch: the body and its two legs. -->
                    <path class="fill-fg" d="M6 42V22a18 18 0 0 1 36 0v20H32V22a8 8 0 0 0-16 0v20z" />
                    <!-- Its crossbar: the belt, and the playful helper's mouth. -->
                    <path v-if="playful && face === 'happy'" class="ai-mascot-belt fill-accent" d="M16 28H32V30Q24 38.5 16 30Z" data-mouth="smile" />
                    <path v-else-if="playful && face === 'sorry'" class="ai-mascot-belt fill-accent" d="M16 33.5Q24 25 32 33.5V31Q24 22.5 16 31Z" data-mouth="frown" />
                    <ellipse v-else-if="playful && face === 'yawn'" class="ai-mascot-belt fill-none stroke-accent" cx="24" cy="31" rx="3" ry="3.6" stroke-width="2" data-mouth="yawn" />
                    <rect v-else class="ai-mascot-belt fill-accent" x="16" y="28" width="16" height="5" data-mouth="belt" />
                    <!-- Feet. -->
                    <rect class="fill-fg" x="4" y="41" width="13" height="3.5" rx="1.75" />
                    <rect class="fill-fg" x="31" y="41" width="13" height="3.5" rx="1.75" />
                    <!-- Brows: the shape of the mood, never its colour alone. -->
                    <g v-if="playful" class="stroke-surface" stroke-width="1.4" stroke-linecap="round" data-brows>
                        <line v-for="(brow, index) in brows" :key="index" :x1="brow[0]" :y1="brow[1]" :x2="brow[2]" :y2="brow[3]" />
                    </g>
                    <!-- Eyes on the arch; a little lower under the brows. -->
                    <g :transform="playful ? 'translate(0 0.8)' : null">
                        <g class="ai-mascot-eyes">
                            <template v-if="playful && (face === 'happy' || face === 'yawn')">
                                <!-- Shut: a smile's arcs, or a yawn's lines. -->
                                <path
                                    class="fill-none stroke-surface"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                    :d="face === 'happy' ? 'M16.4 11.9Q19 8.9 21.6 11.9M26.4 11.9Q29 8.9 31.6 11.9' : 'M16.6 11H21.4M26.6 11H31.4'"
                                    data-eyes="shut"
                                />
                            </template>
                            <template v-else>
                                <circle class="fill-surface" cx="19" cy="10.5" r="3" />
                                <circle v-if="!(playful && face === 'hello')" class="fill-surface" cx="29" cy="10.5" r="3" />
                                <path v-else class="fill-none stroke-surface" stroke-width="1.6" stroke-linecap="round" d="M26.4 10.9Q29 12.9 31.6 10.9" data-eyes="wink" />
                                <g class="ai-mascot-pupils">
                                    <circle class="fill-fg" :cx="19 + gaze.x" :cy="10.5 + gaze.y" r="1.4" />
                                    <circle v-if="!(playful && face === 'hello')" class="fill-fg" :cx="29 + gaze.x" :cy="10.5 + gaze.y" :r="prop === 'glass' ? 2 : 1.4" />
                                </g>
                            </template>
                        </g>
                        <!-- Costumes, a moment each. -->
                        <g v-if="prop === 'glass'" class="fill-none stroke-surface" stroke-linecap="round" data-costume="glass">
                            <circle cx="29" cy="10.5" r="4.4" stroke-width="1.3" />
                            <path d="M32.2 13.7L37.4 18.9" stroke-width="2.4" />
                        </g>
                        <g v-else-if="prop === 'glasses'" class="fill-none stroke-surface" stroke-width="1.1" stroke-linecap="round" data-costume="glasses">
                            <circle cx="19" cy="10.5" r="3.9" />
                            <circle cx="29" cy="10.5" r="3.9" />
                            <path d="M22.9 10.2Q24 9.4 25.1 10.2M15.1 10L13.2 9.1M32.9 10L34.8 9.1" />
                        </g>
                    </g>
                </g>
                <g v-if="prop === 'pencil'" transform="translate(43.5 34) rotate(20)">
                    <g data-costume="pencil">
                        <path class="fill-fg" d="M0 0L-1.7-3.6H1.7Z" />
                        <rect class="fill-fg" x="-1.7" y="-13.6" width="3.4" height="10" />
                        <rect class="fill-accent" x="-1.7" y="-16.4" width="3.4" height="2.8" rx="0.8" />
                        <path class="stroke-surface" d="M0-12.4V-4.6" stroke-width="0.7" />
                    </g>
                </g>
                <g v-else-if="prop === 'hat'" transform="rotate(14 24 4)">
                    <g data-costume="hat">
                        <path class="fill-accent" d="M17.5 5.4L24-9.4L30.5 5.4Q24 7.2 17.5 5.4Z" />
                        <circle class="fill-on-accent" cx="22.6" cy="0.4" r="0.9" />
                        <circle class="fill-on-accent" cx="25.8" cy="-3.6" r="0.9" />
                        <circle class="fill-on-accent" cx="24.6" cy="3.2" r="0.9" />
                        <circle class="fill-fg" cx="24" cy="-10" r="1.9" />
                    </g>
                </g>
            </svg>
            <span class="sr-only" role="status" dusk="ai-mascot-status">{{ status }}</span>
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

/* The playful helper: eyes that dart while it thinks, and its tricks. */
.ai-mascot-pupils {
    transform-box: view-box;
}

[data-mood='thinking'] .ai-mascot-pupils {
    animation: ai-mascot-dart 1.2s ease-in-out infinite;
}

[data-trick='look'] .ai-mascot-pupils {
    animation: ai-mascot-look 1.6s ease-in-out;
}

[data-trick='hop'] .ai-mascot-body {
    animation: ai-mascot-hop 0.8s ease-out 2;
}

[data-trick='yawn'] .ai-mascot-body {
    animation: ai-mascot-stretch 1.6s ease-in-out;
}

[data-mood='sorry'] .ai-mascot-body {
    animation: ai-mascot-droop 4s ease-in-out;
}

[data-prop] [data-costume] {
    animation: ai-mascot-pop 0.25s ease-out;
    transform-box: fill-box;
    transform-origin: 50% 100%;
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

@keyframes ai-mascot-dart {
    0%, 100% { transform: translateX(-0.8px); }
    50% { transform: translateX(0.8px); }
}

@keyframes ai-mascot-look {
    0%, 100% { transform: translateX(0); }
    30% { transform: translateX(-1.4px); }
    70% { transform: translateX(1.4px); }
}

@keyframes ai-mascot-stretch {
    0%, 100% { transform: scale(1, 1); }
    45% { transform: scale(0.97, 1.06); }
}

@keyframes ai-mascot-droop {
    0%, 100% { transform: translateY(0) scale(1, 1); }
    20%, 80% { transform: translateY(0.6px) scale(1.02, 0.97); }
}

@keyframes ai-mascot-pop {
    0% { transform: scale(0.4); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

/* Reduced motion: the faces change, nothing moves. */
@media (prefers-reduced-motion: reduce) {
    .ai-mascot *,
    .ai-mascot:hover *,
    .ai-mascot:focus-visible * {
        animation: none !important;
        transition: none !important;
    }
}
</style>
