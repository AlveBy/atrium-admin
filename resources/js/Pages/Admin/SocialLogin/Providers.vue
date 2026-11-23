<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Providers" screen of alveby/atrium-social-login: every provider, and
 * where its button is switched on. The module's strings come in the `t` prop.
 */
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ModuleNotice from '@atrium/Components/Admin/UI/ModuleNotice.vue'
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
    status: { type: String, default: null },
    providers: { type: Array, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
</script>

<template>
    <AdminLayout :title="l('providers.title')">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-fg">{{ l('providers.title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ l('providers.hint') }}</p>
        </div>

        <div dusk="asl-providers">
            <ModuleNotice prefix="asl" :status="status" />

            <AdminCard no-padding>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-alt">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-muted">{{ l('providers.provider') }}</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-muted">{{ l('providers.status') }}</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">{{ l('providers.edit') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="provider in providers" :key="provider.key" :dusk="`asl-provider-${provider.key}`">
                                <td class="px-4 py-3 font-semibold text-fg">{{ provider.label }}</td>
                                <td class="px-4 py-3 text-fg">
                                    <template v-if="provider.state === 'on'">
                                        <strong>✓ {{ l('providers.on') }}:</strong> {{ provider.guards.join(', ') }}
                                    </template>
                                    <span v-else class="text-muted">{{ l(`providers.${provider.state}`) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="provider.edit_url" :dusk="`asl-edit-${provider.key}`" class="inline-flex items-center rounded-md border border-field bg-surface px-2.5 py-1.5 text-xs font-medium text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus">
                                        {{ l('providers.edit') }}<span class="sr-only"> {{ provider.label }}</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminCard>
        </div>
    </AdminLayout>
</template>
