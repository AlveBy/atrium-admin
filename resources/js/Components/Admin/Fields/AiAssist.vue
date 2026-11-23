<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, inject, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminModal from '../UI/AdminModal.vue'
import AdminButton from '../UI/AdminButton.vue'
import { ajaxHeaders, responseErrorMessage } from '../../../Support/csrf'
import { aiBridge, enlist } from '../../../Support/aiBridge'

/**
 * The "Generate", "Improve" and "Translate" buttons of a field marked ->ai().
 * Drawn only when alveby/atrium-ai is installed and a provider has a key (the
 * shared "ai" prop). The text is offered in a dialog: the editor accepts or
 * edits it, and nothing is saved until the form is.
 *
 * Under the field, as links; but a field in the visual editor has them in the
 * editor's toolbar (see aiBridge), where the selected piece is at hand: with a
 * piece selected, "Improve" works on that piece alone and swaps only it.
 */
const props = defineProps({
    field: { type: Object, required: true },
    locale: { type: String, default: null },
    translatable: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'apply-all'])

const page = usePage()
const ai = computed(() => page.props.ai || null)
const module = inject('aiModule', null)
const formData = inject('formData', ref({}))
const locales = computed(() => page.props.contentLocales || [])
const labels = computed(() => ai.value?.labels || {})
// A public demo: everybody sees what everybody types.
const demo = computed(() => Boolean(ai.value?.demo))

const canGenerate = computed(() => Boolean(ai.value && module && props.field.ai && ai.value.tasks.includes(props.field.ai.task)))
const canTranslate = computed(() => Boolean(canGenerate.value && props.translatable && locales.value.length > 1 && ai.value.tasks.includes('translate')))

const open = ref(false)
const mode = ref('generate')
// The piece of the visual editor being improved, when one is selected.
const piece = ref(null)
const working = ref(false)
const error = ref(null)
const value = ref('')
const values = ref({})
const provider = ref(null)
let timer = null
let run = 0

const titles = { generate: 'dialog_title', translate: 'translate_title', improve: 'improve_title' }
const title = computed(() => (labels.value[titles[mode.value]] || '')
    .replace(':field', props.field.label || props.field.name))

// The text the field holds now (in the language being edited), without tags:
// "Improve" has something to work on only when there is any.
const hasText = computed(() => {
    const raw = formData.value?.[props.field.name]
    const text = props.translatable && raw && typeof raw === 'object' ? raw[props.locale] : raw

    return String(text ?? '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').trim() !== ''
})
const canImprove = computed(() => Boolean(canGenerate.value && hasText.value && ai.value.tasks.includes('improve')))

// With the visual editor on screen the toolbar has the buttons.
const inToolbar = computed(() => Boolean(aiBridge.editors[props.field.name]))

const note = computed(() => [labels.value.check, provider.value ? (labels.value.by || '').replace(':provider', provider.value) : null]
    .filter(Boolean)
    .join(' '))

const others = computed(() => locales.value.filter((item) => item.code !== props.locale))

function stop() {
    clearTimeout(timer)
    timer = null
    run++
}

onUnmounted(stop)

async function start(next) {
    stop()
    const current = run
    mode.value = next
    piece.value = next === 'improve' ? (aiBridge.editors[props.field.name]?.pick() ?? null) : null
    open.value = true
    working.value = true
    error.value = null
    value.value = ''
    values.value = {}
    provider.value = null
    // The helper in the corner picks up a pencil (AiHelper.vue).
    window.dispatchEvent(new CustomEvent('atrium:ai-writing'))

    try {
        const response = await fetch(ai.value.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                module,
                field: props.field.name,
                values: formData.value,
                locale: props.locale,
                translate: next === 'translate',
                improve: next === 'improve',
                selection: piece.value?.html ?? null,
            }),
        })

        if (!response.ok) {
            const data = response.status === 422 ? await response.clone().json().catch(() => ({})) : {}
            throw new Error(data.errors?.field?.[0] || await responseErrorMessage(response, labels.value.error))
        }

        const data = await response.json()
        settle(data, current)
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
        provider.value = data.provider || null
        value.value = data.value ?? ''
        values.value = { ...(data.values || {}) }
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
        const response = await fetch(`${ai.value.url}/${id}`, {
            credentials: 'same-origin',
            headers: ajaxHeaders(),
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

function fail(message) {
    working.value = false
    error.value = message || labels.value.error
}

function close() {
    stop()
    open.value = false
}

function accept() {
    const editor = aiBridge.editors[props.field.name]

    if (mode.value === 'translate') {
        // A language cleared in the dialog keeps the text it had.
        emit('apply-all', Object.fromEntries(Object.entries(values.value).filter(([, text]) => String(text ?? '').trim() !== '')))
    } else if (piece.value && editor) {
        editor.replace(piece.value, value.value)
    } else {
        emit('apply', value.value)
    }

    close()
}

// What the toolbar of the visual editor draws (and starts) in place of the links.
function menu() {
    const picked = Boolean(aiBridge.editors[props.field.name]?.pick())
    const items = [{ mode: 'generate', label: labels.value.generate }]

    if (canImprove.value) {
        items.push({ mode: 'improve', label: picked ? labels.value.improve_selection : labels.value.improve })
    }

    if (canTranslate.value) {
        items.push({ mode: 'translate', label: labels.value.translate })
    }

    return items
}

const unlist = canGenerate.value
    ? enlist('assists', props.field.name, { title: () => labels.value.menu, menu, run: start })
    : null

onUnmounted(() => unlist?.())
</script>

<template>
    <div v-if="canGenerate" data-ai>
        <div v-if="!inToolbar" class="mt-1 flex justify-end gap-3 text-xs">
            <button
                type="button"
                class="inline-flex items-center gap-1 text-accent hover:text-accent-hover disabled:text-muted disabled:cursor-not-allowed"
                :data-ai-generate="field.name"
                dusk="ai-generate"
                @click="start('generate')"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 1l1.8 5.2L17 8l-5.2 1.8L10 15l-1.8-5.2L3 8l5.2-1.8L10 1zm6 11l.9 2.1L19 15l-2.1.9L16 18l-.9-2.1L13 15l2.1-.9L16 12z" />
                </svg>
                {{ labels.generate }}
            </button>
            <button
                v-if="canImprove"
                type="button"
                class="text-accent hover:text-accent-hover disabled:text-muted disabled:cursor-not-allowed"
                :data-ai-improve="field.name"
                dusk="ai-improve"
                @click="start('improve')"
            >
                {{ labels.improve }}
            </button>
            <button
                v-if="canTranslate"
                type="button"
                class="text-accent hover:text-accent-hover disabled:text-muted disabled:cursor-not-allowed"
                :data-ai-translate="field.name"
                dusk="ai-translate"
                @click="start('translate')"
            >
                {{ labels.translate }}
            </button>
        </div>

        <AdminModal :show="open" :title="title" max-width="2xl" @close="close">
            <p v-if="demo" class="mb-3 rounded-md border border-line bg-surface-alt px-3 py-2 text-xs text-fg" data-ai-demo-notice>
                {{ labels.demo_notice }}
            </p>

            <p v-if="working" class="text-sm text-fg" data-ai-working dusk="ai-working">{{ labels.working }}</p>

            <div v-else-if="error" class="rounded-md border border-danger bg-danger-soft px-4 py-3 text-sm text-danger-soft-fg" data-ai-error dusk="ai-error">
                {{ error }}
            </div>

            <div v-else-if="mode === 'translate'" class="space-y-4">
                <label v-for="item in others" :key="item.code" class="block text-sm">
                    <span class="mb-1 block font-medium text-fg">{{ item.name }}</span>
                    <textarea
                        v-model="values[item.code]"
                        rows="4"
                        class="w-full rounded-md border border-field bg-surface px-3 py-2 text-sm text-fg focus:border-focus focus:outline-none focus:ring-1 focus:ring-focus"
                        :data-ai-result="item.code"
                        :dusk="`ai-result-${item.code}`"
                    />
                </label>
            </div>

            <textarea
                v-else
                v-model="value"
                rows="8"
                class="w-full rounded-md border border-field bg-surface px-3 py-2 text-sm text-fg focus:border-focus focus:outline-none focus:ring-1 focus:ring-focus"
                data-ai-result
                dusk="ai-result"
            />

            <p v-if="!working && !error" class="mt-2 text-xs text-fg">
                {{ note }}
            </p>

            <template #footer>
                <AdminButton variant="outline" @click="close">{{ labels.cancel }}</AdminButton>
                <AdminButton v-if="error" variant="secondary" @click="start(mode)">{{ labels.retry }}</AdminButton>
                <AdminButton v-else :disabled="working" data-ai-accept dusk="ai-accept" @click="accept">{{ labels.accept }}</AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
