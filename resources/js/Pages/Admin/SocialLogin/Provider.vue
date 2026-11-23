<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * One provider of alveby/atrium-social-login: the redirect addresses to
 * register at the provider, where its button shows, and the client id and
 * secret. The secret is never sent back to the page; an empty field keeps
 * it. The module's strings come in the `t` prop.
 */
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ModuleNotice from '@atrium/Components/Admin/UI/ModuleNotice.vue'
import { label } from '@atrium/Support/labels'

const props = defineProps({
    t: { type: Object, required: true },
    status: { type: String, default: null },
    provider: { type: Object, required: true },
    names: { type: Array, required: true },
    fields: { type: Array, required: true },
    urls: { type: Object, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)

const form = useForm({
    guards: [...props.provider.guards],
    client_id: props.provider.client_id,
    client_secret: '',
    settings: { ...props.provider.settings },
})

const save = () => form.put(props.urls.update, { preserveScroll: true })

const field = 'block w-full rounded-md border border-field bg-surface text-fg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-focus'
</script>

<template>
    <AdminLayout :title="provider.label">
        <nav class="mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li>
                    <Link :href="urls.index" class="text-muted hover:text-accent">{{ l('providers.title') }}</Link>
                </li>
                <li class="text-muted" aria-hidden="true">/</li>
                <li class="text-fg" aria-current="page">{{ provider.label }}</li>
            </ol>
        </nav>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-fg">{{ provider.label }}</h1>
            <p v-if="provider.hint" class="mt-1 text-sm text-muted">{{ provider.hint }}</p>
        </div>

        <div dusk="asl-provider-form" class="max-w-3xl space-y-6">
            <ModuleNotice prefix="asl" :status="status" />

            <AdminCard :title="l('providers.redirect')" :subtitle="l('providers.redirect_hint')">
                <div class="space-y-3">
                    <div v-for="item in names" :key="item.name">
                        <label :for="`asl-redirect-${item.name}`" class="block text-sm font-medium text-fg mb-1">{{ item.label }}</label>
                        <input :id="`asl-redirect-${item.name}`" type="text" readonly :value="item.redirect" :dusk="`asl-redirect-${item.name}`" :class="[field, 'font-mono break-all']">
                    </div>
                </div>
            </AdminCard>

            <form @submit.prevent="save">
                <AdminCard>
                    <div class="space-y-5">
                        <fieldset>
                            <legend class="text-sm font-semibold text-fg mb-2">{{ l('providers.enabled_for') }}</legend>
                            <label v-for="item in names" :key="item.name" class="flex items-center gap-2 text-sm text-fg py-1">
                                <input v-model="form.guards" type="checkbox" :value="item.name" :dusk="`asl-guard-${item.name}`" class="rounded border-field text-accent focus:ring-focus">
                                {{ item.label }}
                            </label>
                            <p v-if="form.errors.guards" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.guards }}</p>
                        </fieldset>

                        <div>
                            <label for="asl-client-id" class="block text-sm font-medium text-fg mb-1">{{ l('providers.client_id') }}</label>
                            <input id="asl-client-id" v-model="form.client_id" type="text" autocomplete="off" spellcheck="false" dusk="asl-client-id" :class="field">
                            <p v-if="form.errors.client_id" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.client_id }}</p>
                        </div>

                        <div>
                            <label for="asl-client-secret" class="block text-sm font-medium text-fg mb-1">{{ l('providers.client_secret') }}</label>
                            <input id="asl-client-secret" v-model="form.client_secret" type="password" autocomplete="new-password" spellcheck="false" aria-describedby="asl-secret-hint" dusk="asl-client-secret" :class="field">
                            <p id="asl-secret-hint" class="mt-1 text-xs text-muted">{{ provider.has_secret ? l('providers.secret_kept') : l('providers.secret_none') }}</p>
                            <p v-if="form.errors.client_secret" role="alert" class="mt-1 text-sm text-danger">{{ form.errors.client_secret }}</p>
                        </div>

                        <div v-for="name in fields" :key="name">
                            <label :for="`asl-setting-${name}`" class="block text-sm font-medium text-fg mb-1">{{ l(`providers.settings.${name}`) }}</label>
                            <input :id="`asl-setting-${name}`" v-model="form.settings[name]" type="text" spellcheck="false" :aria-describedby="`asl-setting-${name}-hint`" :dusk="`asl-setting-${name}`" :class="field">
                            <p :id="`asl-setting-${name}-hint`" class="mt-1 text-xs text-muted">{{ l(`providers.settings.${name}_hint`) }}</p>
                            <p v-if="form.errors[`settings.${name}`]" role="alert" :dusk="`asl-setting-${name}-error`" class="mt-1 text-sm text-danger">{{ form.errors[`settings.${name}`] }}</p>
                        </div>
                    </div>

                    <template #footer>
                        <AdminButton type="submit" :loading="form.processing" dusk="asl-save">{{ l('providers.save') }}</AdminButton>
                    </template>
                </AdminCard>
            </form>
        </div>
    </AdminLayout>
</template>
