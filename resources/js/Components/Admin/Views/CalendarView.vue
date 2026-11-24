<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import { ajaxHeaders } from '../../../Support/csrf'
import { t } from '../../../Support/i18n'

const props = defineProps({
    routeName: String,
    // mode, date, from, to, today, field, timed, truncated, events (id, title, start, end, url).
    calendar: Object,
    // What narrows the list (search, filters): kept when the calendar turns a page.
    filters: { type: Object, default: () => ({}) },
    // What a click on a day opens the form with, besides the day.
    createQuery: { type: Object, default: () => ({}) },
    // Without it the records stay where they are.
    canMove: { type: Boolean, default: true },
    canCreate: { type: Boolean, default: true },
})

const MODES = ['month', 'week', 'list']
const DAY = 86400000

// The days are plain dates, written 2026-12-31 and counted in UTC, so no clock change moves a record.
const parse = (day) => Date.UTC(Number(day.slice(0, 4)), Number(day.slice(5, 7)) - 1, Number(day.slice(8, 10)))
const format = (ms) => new Date(ms).toISOString().slice(0, 10)
const addDays = (day, count) => format(parse(day) + count * DAY)
const shift = (stamp, count) => (stamp === null ? null : addDays(stamp, count) + stamp.slice(10))
const dayOf = (stamp) => stamp.slice(0, 10)

// The calendar is drawn from a copy: a record moves at once, and goes back if the server refuses.
const events = ref(JSON.parse(JSON.stringify(props.calendar.events)))
const message = ref('')
const lists = {}
const sorters = []

watch(() => props.calendar, (calendar) => { events.value = JSON.parse(JSON.stringify(calendar.events)) })

const locale = computed(() => (usePage().props.locale || 'en').replace('_', '-'))
const formatted = (day, options) => new Intl.DateTimeFormat(locale.value, { ...options, timeZone: 'UTC' }).format(parse(day))

const title = computed(() => (props.calendar.mode === 'week'
    ? `${formatted(props.calendar.from, { dateStyle: 'medium' })} — ${formatted(props.calendar.to, { dateStyle: 'medium' })}`
    : formatted(props.calendar.date, { month: 'long', year: 'numeric' })))

const weekdays = computed(() => Array.from({ length: 7 }, (_, index) => formatted(addDays(props.calendar.from, index), { weekday: 'short' })))

const month = computed(() => props.calendar.date.slice(0, 7))

const days = computed(() => {
    const count = (parse(props.calendar.to) - parse(props.calendar.from)) / DAY + 1

    return Array.from({ length: count }, (_, index) => {
        const day = addDays(props.calendar.from, index)

        return { day, outside: props.calendar.mode === 'month' && day.slice(0, 7) !== month.value, today: day === props.calendar.today }
    })
})

const sorted = computed(() => events.value
    .filter((event) => event.start !== null)
    .sort((a, b) => (a.start < b.start ? -1 : a.start > b.start ? 1 : a.id - b.id)))

// The records on a day: those that start there and those that last through it.
const on = (day) => sorted.value.filter((event) => dayOf(event.start) <= day && day <= dayOf(event.end ?? event.start))

// The list is the records by the day they start, or by the first day shown for those that began before it.
const listed = computed(() => {
    const groups = new Map()

    for (const event of sorted.value) {
        const day = dayOf(event.start) < props.calendar.from ? props.calendar.from : dayOf(event.start)

        groups.set(day, [...(groups.get(day) ?? []), event])
    }

    return [...groups.entries()]
})

const time = (stamp) => (props.calendar.timed && stamp && stamp.length > 10 ? stamp.slice(11, 16) : '')

const go = (query) => {
    router.get(route(`${props.routeName}.index`), { ...props.filters, view: 'calendar', mode: props.calendar.mode, date: props.calendar.date, ...query }, { preserveScroll: true })
}

const step = (direction) => {
    const date = props.calendar.date

    if (props.calendar.mode === 'week') {
        return addDays(date, 7 * direction)
    }

    const first = new Date(parse(`${date.slice(0, 7)}-01`))

    return format(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + direction, 1))
}

const createUrl = (day) => route(`${props.routeName}.create`, { ...props.createQuery, [props.calendar.field]: day })

const create = (day) => {
    if (props.canCreate) {
        router.visit(createUrl(day))
    }
}

const failure = async (response) => {
    const body = await response.json().catch(() => ({}))

    return Object.values(body.errors ?? {}).flat()[0] ?? t('views.calendar.failed')
}

/**
 * Moves a record to a day, with its time and its length, and saves it. The
 * page shows it there first; a refusal puts it back.
 */
const move = async (id, day) => {
    const item = events.value.find((event) => event.id === id)
    const days = (parse(day) - parse(dayOf(item.start))) / DAY

    if (days === 0) {
        return
    }

    const before = JSON.parse(JSON.stringify(events.value))
    item.start = shift(item.start, days)
    item.end = shift(item.end, days)
    message.value = ''

    try {
        const response = await fetch(route(`${props.routeName}.move`, id), {
            method: 'PATCH',
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ view: 'calendar', day }),
        })

        if (!response.ok) {
            throw new Error(await failure(response))
        }

        Object.assign(item, (await response.json()).event)
    } catch (error) {
        events.value = before
        message.value = error instanceof TypeError ? t('views.calendar.failed') : error.message
    }
}

// The date on a row does what a drag does, for a keyboard, a touch screen and the list.
const moveTo = (event, input) => {
    if (input.target.value) {
        move(event.id, input.target.value)
    }
}

const attach = () => {
    sorters.splice(0).forEach((sorter) => sorter.destroy())

    if (!props.canMove || props.calendar.mode === 'list') {
        return
    }

    for (const element of Object.values(lists)) {
        sorters.push(Sortable.create(element, {
            group: 'calendar',
            draggable: '[data-event]',
            // A record that goes on from the day before is moved by its first day.
            filter: '[data-continued]',
            preventOnFilter: false,
            sort: false,
            animation: 120,
            forceFallback: true,
            fallbackTolerance: 4,
            fallbackOnBody: true,
            ghostClass: 'calendar-ghost',
            fallbackClass: 'calendar-drag',
            onEnd: (event) => {
                // Vue draws the records: the node Sortable moved goes back, and the state moves the record.
                event.item.remove()
                event.from.insertBefore(event.item, event.from.children[event.oldIndex] ?? null)

                if (event.to !== event.from) {
                    move(Number(event.item.dataset.event), event.to.dataset.day)
                }
            },
        }))
    }
}

onMounted(() => nextTick(attach))
watch(() => [props.calendar, props.canMove], () => nextTick(attach))
onBeforeUnmount(() => sorters.forEach((sorter) => sorter.destroy()))
</script>

<template>
    <div data-calendar :data-mode="calendar.mode">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    data-calendar-nav="previous"
                    class="rounded-md border border-field bg-surface px-3 py-1.5 text-sm text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus"
                    :aria-label="$t('views.calendar.previous')"
                    @click="go({ date: step(-1) })"
                >
                    <span aria-hidden="true">‹</span>
                </button>
                <button
                    type="button"
                    data-calendar-nav="today"
                    class="rounded-md border border-field bg-surface px-3 py-1.5 text-sm text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus"
                    @click="go({ date: calendar.today })"
                >
                    {{ $t('views.calendar.today') }}
                </button>
                <button
                    type="button"
                    data-calendar-nav="next"
                    class="rounded-md border border-field bg-surface px-3 py-1.5 text-sm text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus"
                    :aria-label="$t('views.calendar.next')"
                    @click="go({ date: step(1) })"
                >
                    <span aria-hidden="true">›</span>
                </button>
                <h2 data-calendar-title aria-live="polite" class="ml-2 text-lg font-semibold text-fg">{{ title }}</h2>
            </div>

            <div role="group" :aria-label="$t('views.calendar.modes')" class="inline-flex rounded-lg border border-field bg-surface p-0.5">
                <button
                    v-for="mode in MODES"
                    :key="mode"
                    type="button"
                    :data-calendar-mode="mode"
                    :aria-pressed="mode === calendar.mode"
                    class="rounded-md px-3 py-1.5 text-sm text-fg focus:outline-none focus:ring-2 focus:ring-focus"
                    :class="mode === calendar.mode ? 'bg-accent-soft font-semibold text-accent-soft-fg underline underline-offset-4' : 'hover:bg-hover'"
                    @click="go({ mode })"
                >
                    {{ $t(`views.calendar.${mode}`) }}
                </button>
            </div>
        </div>

        <p v-if="message" role="alert" data-calendar-error class="mb-3 rounded-lg border border-danger bg-danger-soft px-4 py-2 text-sm font-medium text-danger-soft-fg">
            {{ message }}
        </p>
        <p v-if="calendar.truncated" role="status" data-calendar-truncated class="mb-3 rounded-lg border border-field bg-warning-soft px-4 py-2 text-sm font-medium text-warning-soft-fg">
            {{ $t('views.calendar.truncated', { count: events.length }) }}
        </p>

        <div v-if="calendar.mode !== 'list'" class="overflow-x-auto pb-4">
            <div class="min-w-[44rem]">
                <div class="grid grid-cols-7 gap-px text-center text-xs font-semibold text-muted">
                    <div v-for="name in weekdays" :key="name" class="py-1">{{ name }}</div>
                </div>
                <div class="grid grid-cols-7 gap-px rounded-lg border border-line bg-line">
                    <div
                        v-for="cell in days"
                        :key="cell.day"
                        :data-calendar-day="cell.day"
                        class="flex flex-col bg-surface p-1.5"
                        :class="[
                            calendar.mode === 'week' ? 'min-h-48' : 'min-h-28',
                            cell.outside ? 'bg-hover' : '',
                            cell.today ? 'outline outline-2 -outline-offset-2 outline-accent' : '',
                            canCreate ? 'cursor-pointer' : '',
                        ]"
                        @click.self="create(cell.day)"
                    >
                        <div class="mb-1 flex items-center justify-between" @click.self="create(cell.day)">
                            <component
                                :is="canCreate ? Link : 'span'"
                                v-bind="canCreate ? { href: createUrl(cell.day), 'data-day-add': cell.day, 'aria-label': $t('views.calendar.add', { date: formatted(cell.day, { dateStyle: 'medium' }) }) } : {}"
                                class="inline-flex min-w-6 items-center justify-center rounded-full px-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-focus"
                                :class="[
                                    cell.today ? 'bg-accent font-bold text-on-accent' : cell.outside ? 'text-muted' : 'font-semibold text-fg',
                                    canCreate ? 'hover:underline' : '',
                                ]"
                            >
                                {{ Number(cell.day.slice(8, 10)) }}
                            </component>
                            <span v-if="cell.today" class="text-[0.65rem] font-semibold uppercase text-fg">{{ $t('views.calendar.today') }}</span>
                        </div>

                        <ul
                            :ref="(element) => { if (element) lists[cell.day] = element }"
                            :data-day="cell.day"
                            class="min-h-6 flex-1 space-y-1"
                            @click.self="create(cell.day)"
                        >
                            <li
                                v-for="event in on(cell.day)"
                                :key="`${event.id}-${cell.day}`"
                                :data-event="event.id"
                                :data-continued="dayOf(event.start) !== cell.day || null"
                                class="rounded border bg-surface px-1.5 py-1 text-xs shadow-sm"
                                :class="[
                                    dayOf(event.start) === cell.day ? 'border-field border-l-4 border-l-accent' : 'border-dashed border-field',
                                    canMove && dayOf(event.start) === cell.day ? 'cursor-grab' : '',
                                ]"
                            >
                                <Link :href="event.url" class="block text-fg underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-focus">
                                    <span v-if="dayOf(event.start) !== cell.day" aria-hidden="true">↳ </span>
                                    <span v-if="time(event.start) && dayOf(event.start) === cell.day" class="font-semibold">{{ time(event.start) }}</span>
                                    {{ event.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div v-else data-calendar-list>
            <p v-if="!listed.length" data-calendar-empty class="py-6 text-center text-sm text-muted">{{ $t('views.calendar.empty') }}</p>

            <section v-for="[day, group] in listed" :key="day" :data-calendar-day="day" class="mb-4">
                <h3 class="mb-1 flex items-center gap-2 border-b border-line pb-1 text-sm font-semibold text-fg">
                    {{ formatted(day, { dateStyle: 'full' }) }}
                    <span v-if="day === calendar.today" class="rounded-full bg-accent px-2 py-0.5 text-xs font-bold text-on-accent">{{ $t('views.calendar.today') }}</span>
                    <Link
                        v-if="canCreate"
                        :href="createUrl(day)"
                        :data-day-add="day"
                        class="ml-auto text-xs font-normal text-fg underline hover:no-underline focus:outline-none focus:ring-2 focus:ring-focus"
                    >
                        {{ $t('views.calendar.add', { date: formatted(day, { dateStyle: 'medium' }) }) }}
                    </Link>
                </h3>
                <ul class="space-y-1">
                    <li
                        v-for="event in group"
                        :key="event.id"
                        :data-event="event.id"
                        class="flex flex-wrap items-center gap-3 rounded border border-field border-l-4 border-l-accent bg-surface px-3 py-2 text-sm"
                    >
                        <span v-if="time(event.start)" class="w-24 shrink-0 font-semibold text-fg">
                            {{ time(event.start) }}<template v-if="event.end && time(event.end)"> – {{ dayOf(event.end) === dayOf(event.start) ? time(event.end) : `${formatted(dayOf(event.end), { dateStyle: 'short' })} ${time(event.end)}` }}</template>
                        </span>
                        <Link :href="event.url" class="min-w-0 flex-1 text-fg underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-focus">{{ event.title }}</Link>
                        <label v-if="canMove" class="text-xs text-muted">
                            <span class="sr-only">{{ $t('views.calendar.move_to') }}</span>
                            <input
                                type="date"
                                data-move-to
                                :value="dayOf(event.start)"
                                class="rounded border border-field bg-surface px-1.5 py-1 text-xs text-fg focus:border-focus focus:ring-focus"
                                @change="moveTo(event, $event)"
                            />
                        </label>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>

<style>
.calendar-ghost {
    opacity: 0.45;
    outline: 2px dashed currentColor;
}

.calendar-drag {
    cursor: grabbing;
    box-shadow: 0 6px 16px rgb(0 0 0 / 0.25);
}
</style>
