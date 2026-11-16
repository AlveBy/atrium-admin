<script setup>
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
    <AdminLayout title="Профиль">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Профиль</h1>
            <p class="mt-1 text-sm text-gray-600">
                Управление настройками вашего аккаунта
            </p>
        </div>

        <div class="max-w-2xl space-y-6">
            <!-- Profile Information -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Основная информация</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Обновите информацию вашего профиля
                    </p>
                </div>
                <form @submit.prevent="updateProfile" class="p-6 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Имя
                        </label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            :class="{ 'border-red-500': profileForm.errors.name }"
                        />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email
                        </label>
                        <input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            :class="{ 'border-red-500': profileForm.errors.email }"
                        />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">
                            {{ profileForm.errors.email }}
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            {{ profileForm.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Update Password -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Смена пароля</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Используйте надежный пароль для защиты аккаунта
                    </p>
                </div>
                <form @submit.prevent="updatePassword" class="p-6 space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">
                            Текущий пароль
                        </label>
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            :class="{ 'border-red-500': passwordForm.errors.current_password }"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Новый пароль
                        </label>
                        <input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            :class="{ 'border-red-500': passwordForm.errors.password }"
                        />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Подтверждение пароля
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        />
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            {{ passwordForm.processing ? 'Сохранение...' : 'Изменить пароль' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
