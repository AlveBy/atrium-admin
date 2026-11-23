<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Sign-in log" screen of alveby/atrium-2fa: every guard's sign-ins,
 * newest first, filtered by where and by what. The module's strings come in
 * the `t` prop.
 */
import { reactive } from 'vue'
import { Link, router, Head } from '@inertiajs/vue3'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import EventsTable from '@atrium/Components/Admin/TwoFactor/EventsTable.vue'
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
    url: { type: String, required: true },
    events: { type: Array, required: true },
    newer: { type: String, default: null },
    older: { type: String, default: null },
    filters: { type: Object, required: true },
    guards: { type: Array, required: true },
    eventNames: { type: Array, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
const form = reactive({ ...props.filters })

function filter() {
    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''))
    router.get(props.url, query)
}

const field = 'block rounded-md border border-field bg-surface text-fg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <Head :title="l('menu.log')" />
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-fg">{{ l('menu.log') }}</h1>
    </div>

    <div dusk="a2f-log">
        <AdminCard>
            <form role="search" class="mb-4 flex flex-wrap items-end gap-4" @submit.prevent="filter">
                <div>
                    <label for="a2f-guard" class="block text-sm font-medium text-fg mb-1">{{ l('log.guard') }}</label>
                    <select id="a2f-guard" v-model="form.guard" dusk="a2f-guard-filter" :class="field">
                        <option value="">{{ l('log.all') }}</option>
                        <option v-for="name in guards" :key="name" :value="name">{{ l(`guards.${name}`) }}</option>
                    </select>
                </div>
                <div>
                    <label for="a2f-event" class="block text-sm font-medium text-fg mb-1">{{ l('log.what') }}</label>
                    <select id="a2f-event" v-model="form.event" dusk="a2f-event-filter" :class="field">
                        <option value="">{{ l('log.all') }}</option>
                        <option v-for="name in eventNames" :key="name" :value="name">{{ l(`events.${name}`) }}</option>
                    </select>
                </div>
                <AdminButton type="submit" variant="outline" dusk="a2f-filter">{{ l('log.filter') }}</AdminButton>
            </form>

            <EventsTable :t="t" :events="events" with-who />

            <nav v-if="newer || older" :aria-label="l('log.pages')" class="mt-4 flex gap-4 text-sm">
                <Link v-if="newer" :href="newer" dusk="a2f-newer" class="text-accent hover:underline">← {{ l('log.newer') }}</Link>
                <Link v-if="older" :href="older" dusk="a2f-older" class="text-accent hover:underline">{{ l('log.older') }} →</Link>
            </nav>
        </AdminCard>
    </div>
</template>
