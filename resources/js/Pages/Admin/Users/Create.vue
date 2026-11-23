<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { useForm, Link, Head } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import FormField from '../../../Components/Admin/Fields/FormField.vue'

const props = defineProps({
    fields: Array,
    title: String,
})

// Initialize form with default values from fields
const initialData = {}
props.fields.forEach(field => {
    initialData[field.name] = field.default ?? ''
})

const form = useForm(initialData)

const submit = () => {
    form.post(route('admin.users.store'))
}

// Inputs drawn right here; anything else (sites, roles) by the shared field component.
const plain = (field) => ['text', 'email', 'password', 'checkbox'].includes(field.type)
</script>

<template>
    <Head :title="title" />
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <Link :href="route('admin.users.index')" class="text-muted hover:text-accent">
                        {{ $t('menu.administrators') }}
                    </Link>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-muted" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="ml-1 text-muted">{{ $t('account.users.creating') }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="max-w-2xl">
        <h1 class="text-2xl font-bold text-fg mb-6">{{ title }}</h1>

        <AdminCard>
            <form @submit.prevent="submit" class="space-y-6">
                <template v-for="field in fields" :key="field.name">
                <FormField
                    v-if="!plain(field)"
                    :field="field"
                    v-model="form[field.name]"
                    :errors="form.errors"
                />
                <div v-else>
                    <!-- Checkbox for boolean -->
                    <div v-if="field.type === 'checkbox'" class="flex items-center">
                        <input
                            :id="field.name"
                            v-model="form[field.name]"
                            type="checkbox"
                            class="h-4 w-4 text-accent focus:ring-focus border-field rounded"
                        />
                        <label :for="field.name" class="ml-2 text-sm text-fg">
                            {{ field.label }}
                        </label>
                    </div>

                    <!-- Text/Email/Password input -->
                    <template v-else>
                        <label :for="field.name" class="block text-sm font-medium text-fg mb-1">
                            {{ field.label }}
                            <span v-if="field.required" class="text-danger">*</span>
                        </label>
                        <input
                            :id="field.name"
                            v-model="form[field.name]"
                            :type="field.type === 'password' ? 'password' : field.type === 'email' ? 'email' : 'text'"
                            :placeholder="field.placeholder"
                            :required="field.required"
                            class="w-full px-3 py-2 border border-field rounded-md shadow-sm focus:ring-focus focus:border-focus"
                            :class="{ 'border-danger': form.errors[field.name] }"
                        />
                    </template>

                    <p v-if="field.hint" class="mt-1 text-sm text-muted">
                        {{ field.hint }}
                    </p>
                    <p v-if="form.errors[field.name]" class="mt-1 text-sm text-danger">
                        {{ form.errors[field.name] }}
                    </p>
                </div>
                </template>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                    <AdminButton
                        :href="route('admin.users.index')"
                        variant="secondary"
                    >
                        {{ $t('common.cancel') }}
                    </AdminButton>
                    <AdminButton
                        data-action="save"
                        type="submit"
                        :loading="form.processing"
                    >
                        {{ $t('common.create') }}
                    </AdminButton>
                </div>
            </form>
        </AdminCard>
    </div>
</template>
