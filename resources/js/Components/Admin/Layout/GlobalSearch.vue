<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ajaxHeaders } from '@atrium/Support/csrf'
import { t } from '@atrium/Support/i18n'

/*
 * One box over every module, for editors. Shown only when a module (the
 * search module) shares `adminSearch` with the address to ask: the core
 * itself has nothing to search across.
 *
 * The answer is { groups: [{ module, label, items: [{ id, label, url, hint }] }] }.
 *
 * Below 1280px the header has no room for a field next to the site and
 * language switchers, so a button opens it in a panel under the header.
 */
const page = usePage()
const config = computed(() => page.props.adminSearch || null)

const query = ref('')
const groups = ref([])
const open = ref(false)
const loading = ref(false)
const failed = ref(false)
const expanded = ref(false)
const input = ref(null)
let timer = null
let request = 0

const found = computed(() => groups.value.reduce((sum, group) => sum + group.items.length, 0))

async function search() {
    const text = query.value.trim()
    if (text.length < 2 || !config.value?.url) {
        groups.value = []
        return
    }
    const ticket = ++request
    loading.value = true
    failed.value = false
    try {
        const response = await fetch(`${config.value.url}?${new URLSearchParams({ q: text })}`, {
            headers: ajaxHeaders(),
            credentials: 'same-origin',
        })
        if (!response.ok) throw new Error(String(response.status))
        const body = await response.json()
        if (ticket === request) groups.value = body.groups || []
    } catch (e) {
        if (ticket === request) {
            groups.value = []
            failed.value = true
        }
    } finally {
        if (ticket === request) loading.value = false
    }
}

watch(query, () => {
    open.value = true
    clearTimeout(timer)
    timer = setTimeout(search, 250)
})

function close() {
    open.value = false
    expanded.value = false
}

async function toggle() {
    expanded.value = !expanded.value
    if (expanded.value) {
        await nextTick()
        input.value?.focus()
    }
}

function onBlur(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) close()
}

// The header outlives visits: a visit starts the box afresh, as it did when the
// header was rebuilt every time.
const stopNavigate = router.on('navigate', () => {
    query.value = ''
    groups.value = []
    close()
})

onBeforeUnmount(() => {
    clearTimeout(timer)
    stopNavigate()
})
</script>

<template>
    <div v-if="config" class="relative hidden md:block" data-admin-search @focusout="onBlur" @keydown.esc="close">
        <button
            type="button"
            class="rounded-lg p-2 text-muted hover:bg-hover hover:text-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-focus xl:hidden"
            :aria-label="t('layout.search_everywhere')"
            :title="t('layout.search_everywhere')"
            :aria-expanded="expanded"
            aria-controls="admin-global-search-panel"
            data-admin-search-toggle
            @click="toggle"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
            </svg>
        </button>

        <div
            id="admin-global-search-panel"
            class="xl:static xl:block xl:w-auto xl:max-w-none xl:p-0 xl:shadow-none xl:border-0 xl:bg-transparent"
            :class="expanded ? 'fixed right-4 top-16 z-40 w-[28rem] max-w-[calc(100vw-2rem)] rounded-md border border-line bg-surface p-2 shadow-lg' : 'hidden'"
        >
            <label class="sr-only" for="admin-global-search">{{ t('layout.search_everywhere') }}</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                </svg>
                <input
                    id="admin-global-search"
                    ref="input"
                    v-model="query"
                    type="search"
                    autocomplete="off"
                    class="w-full rounded-md border border-field bg-surface py-2 pl-8 pr-3 text-sm focus:border-focus focus:ring-focus xl:w-72"
                    :placeholder="t('layout.search_everywhere')"
                    @focus="open = query.trim().length >= 2"
                />
            </div>

            <div
                v-if="open && query.trim().length >= 2"
                class="absolute left-0 z-40 mt-1 max-h-[70vh] w-full max-w-[calc(100vw-2rem)] overflow-y-auto rounded-md border border-line bg-surface shadow-lg xl:w-[28rem]"
                role="listbox"
                data-admin-search-results
            >
                <p v-if="loading && !found" class="px-4 py-3 text-sm text-muted">{{ t('common.loading') }}</p>
                <p v-else-if="failed" class="px-4 py-3 text-sm text-danger">{{ t('layout.search_failed') }}</p>
                <p v-else-if="!found" class="px-4 py-3 text-sm text-muted" data-admin-search-empty>{{ t('layout.search_nothing') }}</p>
                <section v-for="group in groups" v-else :key="group.module" :data-admin-search-group="group.module">
                    <h3 class="bg-surface-alt px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted">{{ group.label }}</h3>
                    <ul>
                        <li v-for="item in group.items" :key="item.id">
                            <Link :href="item.url" class="block px-4 py-2 text-sm hover:bg-hover focus:bg-hover focus:outline-none" @click="close">
                                <span class="font-medium text-fg">{{ item.label }}</span>
                                <span v-if="item.hint" class="block truncate text-xs text-muted">{{ item.hint }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
