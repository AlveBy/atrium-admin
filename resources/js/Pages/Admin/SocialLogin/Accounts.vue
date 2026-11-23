<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Linked accounts" screen of alveby/atrium-social-login: the accounts at
 * other services the administrator signs in with, linked or to be linked.
 * The module's strings come in the `t` prop.
 */
import { computed, ref } from 'vue'
import { router, usePage, Head } from '@inertiajs/vue3'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ModuleNotice from '@atrium/Components/Admin/UI/ModuleNotice.vue'
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
    status: { type: String, default: null },
    linked: { type: Array, required: true },
    available: { type: Array, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
const page = usePage()
const errors = computed(() => Object.values(page.props.errors || {}))
const busy = ref(false)

function unlink(account) {
    busy.value = true
    router.delete(account.unlink_url, { preserveScroll: true, onFinish: () => { busy.value = false } })
}
</script>

<template>
    <Head :title="l('accounts.title')" />
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-fg">{{ l('accounts.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ l('accounts.hint') }}</p>
    </div>

    <div dusk="asl-accounts" class="max-w-3xl space-y-6">
        <ModuleNotice prefix="asl" :status="status" :errors="errors" />

        <AdminCard :title="l('accounts.linked_title')">
            <p v-if="!linked.length" dusk="asl-none" class="text-sm text-muted">{{ l('accounts.none') }}</p>
            <ul v-else class="divide-y divide-line">
                <li v-for="account in linked" :key="account.id" :dusk="`asl-linked-${account.provider}`" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <strong class="text-fg">{{ account.label }}</strong>
                        <div v-if="account.who" class="text-sm text-fg break-words">{{ account.who }}</div>
                        <div class="text-sm text-muted">{{ l('accounts.last_used') }}: {{ account.last_used ?? l('accounts.never') }}</div>
                    </div>
                    <AdminButton variant="outline" size="sm" :disabled="busy" :dusk="`asl-unlink-${account.provider}`" @click="unlink(account)">
                        {{ l('accounts.unlink') }}<span class="sr-only"> {{ account.label }}</span>
                    </AdminButton>
                </li>
            </ul>
        </AdminCard>

        <AdminCard v-if="available.length" :title="l('accounts.available')">
            <ul class="grid gap-2">
                <li v-for="provider in available" :key="provider.key">
                    <!-- A plain link: it leaves for the provider's own site, which an Inertia visit cannot follow. -->
                    <a :href="provider.url" :dusk="`asl-link-${provider.key}`" class="inline-flex w-full items-center justify-center rounded-md border border-field bg-surface px-4 py-2 text-sm font-medium text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus">
                        {{ l('accounts.link', { provider: provider.label }) }}
                    </a>
                </li>
            </ul>
        </AdminCard>
    </div>
</template>
