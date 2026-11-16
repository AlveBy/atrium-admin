<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
    branding: {
        type: Object,
        default: () => ({})
    },
    email: {
        type: String,
        default: ''
    },
    // Сколько секунд осталось до следующего письма.
    retryAfter: {
        type: Number,
        default: 0
    },
})

const form = useForm({ code: '' })
const resendForm = useForm({})
const cancelForm = useForm({})

const codeInput = ref(null)
const seconds = ref(props.retryAfter)
let ticker = null

const status = computed(() => usePage().props.flash?.success ?? null)
const canResend = computed(() => seconds.value <= 0 && !resendForm.processing)

const countdown = (from) => {
    seconds.value = from
    clearInterval(ticker)
    ticker = setInterval(() => {
        if (--seconds.value <= 0) clearInterval(ticker)
    }, 1000)
}

onMounted(() => {
    codeInput.value?.focus()
    if (seconds.value > 0) countdown(seconds.value)
})

onUnmounted(() => clearInterval(ticker))

const submit = () => {
    form.post(route('admin.two-factor'), {
        onError: () => form.reset('code'),
    })
}

const resend = () => {
    resendForm.post(route('admin.two-factor.resend'), {
        preserveScroll: true,
        onSuccess: () => countdown(60),
    })
}

const cancel = () => cancelForm.post(route('admin.two-factor.cancel'))
</script>

<template>
    <Head title="Подтверждение входа" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <img
                    v-if="branding.logo"
                    :src="branding.logo"
                    :alt="branding.name"
                    class="mx-auto h-12 w-auto"
                />
                <h2 v-else class="text-3xl font-bold text-gray-900">
                    {{ branding.name || 'Admin Panel' }}
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Мы отправили код на {{ email }}
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-8">
                <p v-if="status" class="mb-4 text-sm text-green-700 bg-green-50 rounded-md px-3 py-2">
                    {{ status }}
                </p>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700">
                            Код из письма
                        </label>
                        <input
                            id="code"
                            ref="codeInput"
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-center text-2xl tracking-[0.5em] font-semibold focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'border-red-500': form.errors.code }"
                            placeholder="000000"
                        />
                        <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg
                            v-if="form.processing"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        {{ form.processing ? 'Проверяем...' : 'Подтвердить' }}
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between text-sm">
                    <button
                        type="button"
                        :disabled="!canResend"
                        @click="resend"
                        class="text-blue-600 hover:text-blue-700 disabled:text-gray-400 disabled:cursor-not-allowed"
                    >
                        {{ canResend ? 'Отправить код ещё раз' : `Отправить ещё раз через ${seconds} с` }}
                    </button>

                    <button
                        type="button"
                        @click="cancel"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        Войти другим пользователем
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
