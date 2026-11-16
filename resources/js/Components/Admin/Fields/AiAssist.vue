<script setup>
import { computed, inject, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminModal from '../UI/AdminModal.vue'
import AdminButton from '../UI/AdminButton.vue'
import { ajaxHeaders, responseErrorMessage } from '../../../Support/csrf'

/**
 * The "Generate" and "Translate" buttons under a field marked ->ai(). Drawn
 * only when alveby/atrium-ai is installed and a provider has a key (the
 * shared "ai" prop). The text is offered in a dialog: the editor accepts or
 * edits it, and nothing is saved until the form is.
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

const canGenerate = computed(() => Boolean(ai.value && module && props.field.ai && ai.value.tasks.includes(props.field.ai.task)))
const canTranslate = computed(() => Boolean(canGenerate.value && props.translatable && locales.value.length > 1 && ai.value.tasks.includes('translate')))

const open = ref(false)
const translating = ref(false)
const working = ref(false)
const error = ref(null)
const value = ref('')
const values = ref({})
const provider = ref(null)
let timer = null
let run = 0

const title = computed(() => ((translating.value ? labels.value.translate_title : labels.value.dialog_title) || '')
    .replace(':field', props.field.label || props.field.name))

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

async function start(translate) {
    stop()
    const current = run
    translating.value = translate
    open.value = true
    working.value = true
    error.value = null
    value.value = ''
    values.value = {}
    provider.value = null

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
                translate,
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
    if (translating.value) {
        // A language cleared in the dialog keeps the text it had.
        emit('apply-all', Object.fromEntries(Object.entries(values.value).filter(([, text]) => String(text ?? '').trim() !== '')))
    } else {
        emit('apply', value.value)
    }

    close()
}
</script>

<template>
    <div v-if="canGenerate" class="mt-1 flex justify-end gap-3 text-xs" data-ai>
        <button
            type="button"
            class="inline-flex items-center gap-1 text-blue-700 hover:text-blue-900 disabled:text-gray-400 disabled:cursor-not-allowed"
            :data-ai-generate="field.name"
            @click="start(false)"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 1l1.8 5.2L17 8l-5.2 1.8L10 15l-1.8-5.2L3 8l5.2-1.8L10 1zm6 11l.9 2.1L19 15l-2.1.9L16 18l-.9-2.1L13 15l2.1-.9L16 12z" />
            </svg>
            {{ labels.generate }}
        </button>
        <button
            v-if="canTranslate"
            type="button"
            class="text-blue-700 hover:text-blue-900 disabled:text-gray-400 disabled:cursor-not-allowed"
            :data-ai-translate="field.name"
            @click="start(true)"
        >
            {{ labels.translate }}
        </button>

        <AdminModal :show="open" :title="title" max-width="2xl" @close="close">
            <p v-if="working" class="text-sm text-gray-700" data-ai-working>{{ labels.working }}</p>

            <div v-else-if="error" class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" data-ai-error>
                {{ error }}
            </div>

            <div v-else-if="translating" class="space-y-4">
                <label v-for="item in others" :key="item.code" class="block text-sm">
                    <span class="mb-1 block font-medium text-gray-900">{{ item.name }}</span>
                    <textarea
                        v-model="values[item.code]"
                        rows="4"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        :data-ai-result="item.code"
                    />
                </label>
            </div>

            <textarea
                v-else
                v-model="value"
                rows="8"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                data-ai-result
            />

            <p v-if="!working && !error" class="mt-2 text-xs text-gray-700">
                {{ note }}
            </p>

            <template #footer>
                <AdminButton variant="outline" @click="close">{{ labels.cancel }}</AdminButton>
                <AdminButton v-if="error" variant="secondary" @click="start(translating)">{{ labels.retry }}</AdminButton>
                <AdminButton v-else :disabled="working" data-ai-accept @click="accept">{{ labels.accept }}</AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
