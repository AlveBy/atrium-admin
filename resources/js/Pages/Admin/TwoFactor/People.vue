<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Two-factor resets" screen of alveby/atrium-2fa: the people of a
 * guard and the ways each confirms signing in, with a reset for someone who
 * lost their phone. The module's strings come in the `t` prop.
 */
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ModuleNotice from '@atrium/Components/Admin/UI/ModuleNotice.vue'
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
    url: { type: String, required: true },
    status: { type: String, default: null },
    people: { type: Array, required: true },
    newer: { type: String, default: null },
    older: { type: String, default: null },
    selected: { type: String, required: true },
    guards: { type: Array, required: true },
    search: { type: String, default: '' },
    resetUrl: { type: String, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
const page = usePage()
const errors = computed(() => Object.values(page.props.errors || {}))
const guard = ref(props.selected)
const q = ref(props.search)

function filter() {
    const query = {}

    if (props.guards.length > 1) {
        query.guard = guard.value
    }

    if (q.value !== '') {
        query.q = q.value
    }

    router.get(props.url, query)
}

function reset(person) {
    if (window.confirm(l('manage.confirm', { email: person.email }))) {
        router.post(props.resetUrl, { guard: props.selected, user: person.id }, { preserveScroll: true })
    }
}

const field = 'block w-full rounded-md border border-field bg-surface text-fg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <AdminLayout :title="l('menu.people')">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-fg">{{ l('menu.people') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ l('manage.hint') }}</p>
        </div>

        <div dusk="a2f-people">
            <ModuleNotice prefix="a2f" :status="status" :errors="errors" />

            <AdminCard>
                <form role="search" class="mb-4 flex flex-wrap items-end gap-4" @submit.prevent="filter">
                    <div v-if="guards.length > 1">
                        <label for="a2f-guard" class="block text-sm font-medium text-fg mb-1">{{ l('log.guard') }}</label>
                        <select id="a2f-guard" v-model="guard" dusk="a2f-guard-filter" :class="field">
                            <option v-for="name in guards" :key="name" :value="name">{{ l(`guards.${name}`) }}</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-48">
                        <label for="a2f-q" class="block text-sm font-medium text-fg mb-1">{{ l('manage.search') }}</label>
                        <input id="a2f-q" v-model="q" type="search" dusk="a2f-search" :class="field">
                    </div>
                    <AdminButton type="submit" variant="outline" dusk="a2f-filter">{{ l('log.filter') }}</AdminButton>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-alt">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('manage.email') }}</th>
                                <th scope="col" class="px-3 py-2 text-left font-semibold text-muted">{{ l('security.factors') }}</th>
                                <th scope="col" class="px-3 py-2"><span class="sr-only">{{ l('manage.reset') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="person in people" :key="person.id" :dusk="`a2f-person-${person.id}`">
                                <td class="px-3 py-2 text-fg">{{ person.email }}</td>
                                <td class="px-3 py-2 text-fg">
                                    <template v-if="person.factors.length">{{ person.factors.join(', ') }}</template>
                                    <span v-else class="text-muted">{{ l('security.none_short') }}</span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <AdminButton v-if="person.factors.length" variant="outline" size="sm" :dusk="`a2f-reset-${person.id}`" @click="reset(person)">
                                        {{ l('manage.reset') }}<span class="sr-only"> {{ person.email }}</span>
                                    </AdminButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="newer || older" :aria-label="l('log.pages')" class="mt-4 flex gap-4 text-sm">
                    <Link v-if="newer" :href="newer" dusk="a2f-newer" class="text-accent hover:underline">← {{ l('log.newer') }}</Link>
                    <Link v-if="older" :href="older" dusk="a2f-older" class="text-accent hover:underline">{{ l('log.older') }} →</Link>
                </nav>
            </AdminCard>
        </div>
    </AdminLayout>
</template>
