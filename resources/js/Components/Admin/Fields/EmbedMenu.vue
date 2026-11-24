<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, onUnmounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { fetchItems, insertEmbed, rememberName } from '../../../Support/embeds'
import { t } from '@atrium/Support/i18n'

/*
 * The editor's "Insert" button: the embed types modules registered (a form,
 * a block…), then one item of the type picked from a searchable list, which
 * lands at the caret. The button is not drawn when the administrator may use
 * no type at all.
 *
 * `caret` is the position read when the toolbar was pressed (caretPosition()
 * in Support/editor.js): by the time an item is picked the editor has long
 * lost focus. The menu stops its own mousedowns, or the toolbar would read
 * the caret again — from the search box, where there is none.
 */
const props = defineProps({
    editor: { type: Object, default: null },
    caret: { type: Object, default: null },
})

const page = usePage()
const types = computed(() => page.props.embeds || [])

const open = ref(false)
const type = ref(null)
const query = ref('')
const items = ref([])
const loading = ref(false)
const failed = ref(false)
const root = ref(null)
let at = null
let timer = null
let ticket = 0

function toggle() {
    open.value = !open.value
    type.value = null
    // The toolbar's mousedown has already read the caret by the time of the click.
    at = props.caret
}

function choose(next) {
    type.value = next
    query.value = ''
    search()
}

async function search() {
    const mine = ++ticket
    loading.value = true
    failed.value = false

    try {
        const found = await fetchItems(type.value.url, { q: query.value })

        if (mine === ticket) items.value = found
    } catch (e) {
        if (mine === ticket) {
            items.value = []
            failed.value = true
        }
    } finally {
        if (mine === ticket) loading.value = false
    }
}

function pick(item) {
    rememberName(type.value.type, item.id, item.label)
    insertEmbed(props.editor, { type: type.value.type, id: item.id }, at)
    open.value = false
}

function outside(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false
}

watch(query, () => {
    clearTimeout(timer)
    timer = setTimeout(search, 250)
})

watch(open, (now) => {
    document[now ? 'addEventListener' : 'removeEventListener']('mousedown', outside)
})

onUnmounted(() => {
    clearTimeout(timer)
    document.removeEventListener('mousedown', outside)
})
</script>

<template>
    <div v-if="types.length" ref="root" class="relative" @keydown.esc="open = false">
        <button
            type="button"
            data-editor-button="embed"
            class="p-2 rounded hover:bg-hover"
            :class="{ 'bg-accent-soft text-accent-soft-fg': open }"
            :title="t('fields.editor.embed')"
            :aria-label="t('fields.editor.embed')"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="toggle"
        >
            <svg class="w-4 h-4 block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2" /><line x1="12" y1="8" x2="12" y2="16" /><line x1="8" y1="12" x2="16" y2="12" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute left-0 top-full z-20 mt-1 w-72 rounded-md border border-field bg-surface py-1 shadow-lg"
            data-embed-menu
            @mousedown.stop
        >
            <template v-if="!type">
                <button
                    v-for="item in types"
                    :key="item.type"
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-sm text-fg hover:bg-hover"
                    :data-embed-type="item.type"
                    @click="choose(item)"
                >
                    {{ item.label }}
                </button>
            </template>

            <template v-else>
                <div class="flex items-center gap-2 px-3 pb-1 text-sm font-medium text-fg">
                    <button type="button" class="text-accent hover:underline" data-embed-back @click="type = null">←</button>
                    {{ type.label }}
                </div>
                <div class="px-3 pb-1">
                    <input
                        v-model="query"
                        type="search"
                        data-embed-search
                        class="w-full px-2 py-1 text-sm border border-field rounded-md bg-surface text-fg"
                        :placeholder="t('fields.editor.embed_search')"
                        :aria-label="t('fields.editor.embed_search')"
                    />
                </div>
                <ul class="max-h-60 overflow-y-auto">
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            class="block w-full px-3 py-1.5 text-left text-sm text-fg hover:bg-hover"
                            :data-embed-item="item.id"
                            @click="pick(item)"
                        >
                            {{ item.label }}
                        </button>
                    </li>
                </ul>
                <p v-if="failed" class="px-3 py-1.5 text-sm text-danger" data-embed-failed>{{ t('fields.editor.embed_failed') }}</p>
                <p v-else-if="!loading && !items.length" class="px-3 py-1.5 text-sm text-muted" data-embed-empty>{{ t('fields.editor.embed_empty') }}</p>
            </template>
        </div>
    </div>
</template>
