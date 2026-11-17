<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { useForm, usePage, Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import LocaleSwitcher from '../../../Components/Admin/Layout/LocaleSwitcher.vue'
import AtriumLogo from '../../../Components/Admin/UI/AtriumLogo.vue'
import AdminCredit from '../../../Components/Admin/UI/AdminCredit.vue'

const props = defineProps({
    branding: {
        type: Object,
        default: () => ({})
    },
    // Other ways in that the sign-in modules switched on (alveby/atrium-2fa,
    // alveby/atrium-social-login): [{ key, label, url, icon }]. The icon is
    // the module's own SVG; the label always says what the button does, so
    // nobody has to tell the services apart by their colours.
    methods: {
        type: Array,
        default: () => []
    }
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

// A way in that sent the person back here with a refusal (a social account
// with no administrator behind it) left its message on the e-mail field.
form.setError({ ...(usePage().props.errors || {}) })

const showPassword = ref(false)

const submit = () => {
    form.post(route('admin.login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head :title="$t('auth.login.title')" />

    <div class="min-h-screen flex items-center justify-center bg-page py-12 px-4 sm:px-6 lg:px-8">
        <div class="absolute top-4 right-4">
            <LocaleSwitcher />
        </div>

        <div class="max-w-md w-full">
            <!-- Logo / Branding -->
            <div class="text-center mb-8">
                <img
                    v-if="branding.logo"
                    :src="branding.logo"
                    :alt="branding.name"
                    class="mx-auto h-12 w-auto"
                />
                <h2 v-else class="flex justify-center">
                    <AtriumLogo on="surface" :size="48" :name="branding.name || 'Atrium'" />
                </h2>
                <p class="mt-2 text-sm text-muted">
                    {{ $t('auth.login.subtitle') }}
                </p>
            </div>

            <!-- Login Form -->
            <div class="bg-surface border border-line rounded-lg shadow-lg p-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-fg">
                            {{ $t('auth.login.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm placeholder-muted focus:outline-none focus:ring-focus focus:border-focus sm:text-sm"
                            :class="{ 'border-danger': form.errors.email }"
                            placeholder="admin@example.com"
                        />
                        <p v-if="form.errors.email" data-error class="mt-1 text-sm text-danger">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-fg">
                            {{ $t('auth.login.password') }}
                        </label>
                        <div class="mt-1 relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="block w-full px-3 py-2 border border-field rounded-md shadow-sm placeholder-muted focus:outline-none focus:ring-focus focus:border-focus sm:text-sm pr-10"
                                :class="{ 'border-danger': form.errors.password }"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-fg"
                            >
                                <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" data-error class="mt-1 text-sm text-danger">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 text-accent focus:ring-focus border-field rounded"
                        />
                        <label for="remember" class="ml-2 block text-sm text-fg">
                            {{ $t('auth.login.remember') }}
                        </label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-on-accent bg-accent hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-focus disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg
                            v-if="form.processing"
                            class="animate-spin -ml-1 mr-2 h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        {{ form.processing ? $t('auth.login.submitting') : $t('auth.login.submit') }}
                    </button>
                </form>

                <div v-if="methods.length" class="mt-6" dusk="login-methods">
                    <div class="flex items-center gap-3 text-sm text-muted" aria-hidden="true">
                        <span class="h-px flex-1 bg-line"></span>
                        {{ $t('auth.login.or') }}
                        <span class="h-px flex-1 bg-line"></span>
                    </div>
                    <ul class="mt-4 space-y-2">
                        <li v-for="method in methods" :key="method.key">
                            <a
                                :href="method.url"
                                :dusk="`login-method-${method.key}`"
                                class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-field rounded-md text-sm font-medium text-fg bg-surface hover:bg-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-focus"
                            >
                                <span v-if="method.icon" class="h-5 w-5 shrink-0" aria-hidden="true" v-html="method.icon"></span>
                                {{ method.label }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <AdminCredit class="mt-8 text-center" />
        </div>
    </div>
</template>
