<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({
    user: {
        type: Object,
        required: true
    }
})

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updateProfile = () => {
    profileForm.put(route('admin.profile.update'), {
        preserveScroll: true,
    })
}

const updatePassword = () => {
    passwordForm.put(route('admin.profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <AdminLayout :title="$t('account.profile.title')">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-fg">{{ $t('account.profile.title') }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $t('account.profile.subtitle') }}
            </p>
        </div>

        <div class="max-w-2xl space-y-6">
            <!-- Profile Information -->
            <div class="bg-surface rounded-lg shadow">
                <div class="p-6 border-b border-line">
                    <h2 class="text-lg font-medium text-fg">{{ $t('account.profile.info_title') }}</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ $t('account.profile.info_subtitle') }}
                    </p>
                </div>
                <form @submit.prevent="updateProfile" class="p-6 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-fg">
                            {{ $t('account.profile.name') }}
                        </label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus sm:text-sm"
                            :class="{ 'border-danger': profileForm.errors.name }"
                        />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-danger">
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-fg">
                            {{ $t('account.profile.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus sm:text-sm"
                            :class="{ 'border-danger': profileForm.errors.email }"
                        />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-danger">
                            {{ profileForm.errors.email }}
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="px-4 py-2 bg-accent text-on-accent text-sm font-medium rounded-md hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-focus disabled:opacity-50"
                        >
                            {{ profileForm.processing ? $t('common.saving') : $t('common.save') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Update Password -->
            <div class="bg-surface rounded-lg shadow">
                <div class="p-6 border-b border-line">
                    <h2 class="text-lg font-medium text-fg">{{ $t('account.profile.password_title') }}</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ $t('account.profile.password_subtitle') }}
                    </p>
                </div>
                <form @submit.prevent="updatePassword" class="p-6 space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-fg">
                            {{ $t('account.profile.current_password') }}
                        </label>
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus sm:text-sm"
                            :class="{ 'border-danger': passwordForm.errors.current_password }"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-danger">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-fg">
                            {{ $t('account.profile.new_password') }}
                        </label>
                        <input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus sm:text-sm"
                            :class="{ 'border-danger': passwordForm.errors.password }"
                        />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-danger">
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-fg">
                            {{ $t('account.profile.password_confirmation') }}
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus sm:text-sm"
                        />
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="px-4 py-2 bg-accent text-on-accent text-sm font-medium rounded-md hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-focus disabled:opacity-50"
                        >
                            {{ passwordForm.processing ? $t('common.saving') : $t('account.profile.change_password') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
