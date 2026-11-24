<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import { ajaxHeaders } from '../../../Support/csrf'
import { t } from '../../../Support/i18n'

const props = defineProps({
    routeName: String,
    // columns: value, label, total, cards, more. ordered: the cards keep a place in their column.
    board: Object,
    // What narrows the list (search, filters), asked again for more cards.
    filters: { type: Object, default: () => ({}) },
    // Without it the cards stay where they are.
    canMove: { type: Boolean, default: true },
})

// The board is drawn from a copy: a card moves at once, and goes back if the server refuses.
const clone = (columns) => JSON.parse(JSON.stringify(columns)).map((column) => ({ ...column, loading: false, page: 1 }))
const columns = ref(clone(props.board.columns))
const message = ref('')
const lists = {}
const sorters = []

watch(() => props.board, (board) => { columns.value = clone(board.columns) })

const keyOf = (value) => (value === null ? '' : String(value))

const find = (value) => columns.value.find((column) => keyOf(column.value) === value)

const failure = async (response) => {
    const body = await response.json().catch(() => ({}))
    const first = Object.values(body.errors ?? {}).flat()[0]

    return first ?? t('views.kanban.failed')
}

/**
 * Moves a card to a column, below the card `after` (null: to the top), and
 * saves it. The page shows it there first; a refusal puts it back.
 */
const move = async (id, to, after) => {
    const before = JSON.parse(JSON.stringify(columns.value))
    const from = columns.value.find((column) => column.cards.some((card) => card.id === id))
    const target = find(keyOf(to))
    const card = from.cards.find((one) => one.id === id)
    const same = from === target

    if (same && !props.board.ordered) {
        return
    }

    from.cards = from.cards.filter((one) => one.id !== id)
    const index = after === null ? 0 : target.cards.findIndex((one) => one.id === after) + 1
    target.cards.splice(index, 0, card)

    if (!same) {
        from.total -= 1
        target.total += 1
    }

    message.value = ''

    try {
        const response = await fetch(route(`${props.routeName}.move`, id), {
            method: 'PATCH',
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ value: to, after }),
        })

        if (!response.ok) {
            throw new Error(await failure(response))
        }

        Object.assign(card, (await response.json()).card)
    } catch (error) {
        columns.value = before.map((column) => ({ ...column, loading: false }))
        message.value = error instanceof TypeError ? t('views.kanban.failed') : error.message
    }
}

const more = async (column) => {
    column.loading = true
    message.value = ''

    try {
        const query = new URLSearchParams({ ...props.filters, page: column.page + 1 })

        if (column.value !== null) {
            query.set('column', column.value)
        }

        const response = await fetch(`${route(`${props.routeName}.cards`)}?${query}`, { headers: ajaxHeaders() })

        if (!response.ok) {
            throw new Error(await failure(response))
        }

        const body = await response.json()
        column.cards.push(...body.cards.filter((card) => !column.cards.some((one) => one.id === card.id)))
        column.more = body.more
        column.page += 1
    } catch (error) {
        message.value = error instanceof TypeError ? t('views.kanban.failed') : error.message
    } finally {
        column.loading = false
    }
}

// The select on a card does what a drag does, for a keyboard or a touch screen.
const moveTo = (card, event) => {
    const to = event.target.value
    event.target.value = ''

    move(card.id, to === '__none' ? null : to, null)
}

const attach = () => {
    sorters.splice(0).forEach((sorter) => sorter.destroy())

    if (!props.canMove) {
        return
    }

    for (const element of Object.values(lists)) {
        sorters.push(Sortable.create(element, {
            group: 'kanban',
            draggable: '[data-card]',
            animation: 120,
            forceFallback: true,
            fallbackTolerance: 4,
            fallbackOnBody: true,
            ghostClass: 'kanban-ghost',
            fallbackClass: 'kanban-drag',
            onEnd: (event) => {
                // Vue draws the cards: the node Sortable moved goes back, and the state moves the card.
                event.item.remove()
                event.from.insertBefore(event.item, event.from.children[event.oldIndex] ?? null)

                const toValue = event.to.dataset.column
                const siblings = [...event.to.querySelectorAll('[data-card]')].filter((node) => node !== event.item)
                const above = siblings[event.newIndex - 1]
                const after = above ? Number(above.dataset.card) : null

                move(Number(event.item.dataset.card), toValue === '' ? null : toValue, after)
            },
        }))
    }
}

onMounted(() => nextTick(attach))
watch(() => [props.board, props.canMove], () => nextTick(attach))
onBeforeUnmount(() => sorters.forEach((sorter) => sorter.destroy()))
</script>

<template>
    <div data-kanban>
        <p v-if="message" role="alert" data-kanban-error class="mb-3 rounded-lg border border-danger bg-danger-soft px-4 py-2 text-sm font-medium text-danger-soft-fg">
            {{ message }}
        </p>

        <div class="flex items-start gap-4 overflow-x-auto pb-4">
            <section
                v-for="column in columns"
                :key="keyOf(column.value)"
                :data-kanban-column="keyOf(column.value)"
                :aria-label="column.label"
                class="w-72 shrink-0 rounded-lg bg-hover p-3"
                :class="column.value === null ? 'border-2 border-dashed border-field' : 'border border-line'"
            >
                <header class="mb-3 flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-fg">{{ column.label }}</h2>
                    <span data-column-count class="text-xs text-muted">{{ $t('views.kanban.count', { count: column.total }) }}</span>
                </header>

                <ul
                    :ref="(element) => { if (element) lists[keyOf(column.value)] = element }"
                    :data-column="keyOf(column.value)"
                    class="min-h-12 space-y-2"
                >
                    <li
                        v-for="card in column.cards"
                        :key="card.id"
                        :data-card="card.id"
                        class="rounded-md border border-field bg-surface p-3 shadow-sm"
                        :class="canMove ? 'cursor-grab' : ''"
                    >
                        <Link :href="card.url" class="block text-sm font-semibold text-fg underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-focus">
                            {{ card.title }}
                        </Link>
                        <dl v-if="card.fields.length" class="mt-1 space-y-0.5 text-xs text-muted">
                            <div v-for="field in card.fields" :key="field.label" class="flex gap-1">
                                <dt>{{ field.label }}:</dt>
                                <dd class="text-fg">{{ field.text || '—' }}</dd>
                            </div>
                        </dl>
                        <label v-if="canMove" class="mt-2 block text-xs text-muted">
                            <span class="sr-only">{{ $t('views.kanban.move_to') }}</span>
                            <select
                                data-move-to
                                class="w-full rounded border border-field bg-surface px-1.5 py-1 text-xs text-fg focus:border-focus focus:ring-focus"
                                @change="moveTo(card, $event)"
                            >
                                <option value="">{{ $t('views.kanban.move_to') }}…</option>
                                <option
                                    v-for="other in columns.filter((one) => one !== column)"
                                    :key="keyOf(other.value)"
                                    :value="other.value === null ? '__none' : other.value"
                                >
                                    {{ other.label }}
                                </option>
                            </select>
                        </label>
                    </li>
                </ul>

                <p v-if="!column.cards.length" data-column-empty class="py-2 text-center text-xs text-muted">{{ $t('views.kanban.empty') }}</p>

                <button
                    v-if="column.more"
                    type="button"
                    data-column-more
                    :disabled="column.loading"
                    class="mt-3 w-full rounded-md border border-field bg-surface px-3 py-1.5 text-sm text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus disabled:opacity-50"
                    @click="more(column)"
                >
                    {{ $t('views.kanban.more') }}
                </button>
            </section>
        </div>
    </div>
</template>

<style>
.kanban-ghost {
    opacity: 0.45;
    outline: 2px dashed currentColor;
}

.kanban-drag {
    cursor: grabbing;
    box-shadow: 0 6px 16px rgb(0 0 0 / 0.25);
}
</style>
