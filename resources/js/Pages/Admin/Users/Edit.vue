<script setup>
import { computed, provide } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import FormField from '../../../Components/Admin/Fields/FormField.vue'

const props = defineProps({
    item: Object,
    fields: Array,
    title: String,
    can: { type: Object, default: () => ({}) },
})

// Inputs drawn right here; anything else (sites, roles) by the shared field component.
const plain = (field) => ['text', 'email', 'password', 'checkbox'].includes(field.type)

const readOnly = computed(() => props.can.update === false)

// Initialize form with item values
const initialData = {}
props.fields.forEach(field => {
    initialData[field.name] = props.item[field.name] ?? field.default ?? ''
})
// Password fields should be empty by default on edit
initialData.password = ''
initialData.password_confirmation = ''

const form = useForm(initialData)

// Provide form data for child components (SlugField needs fullPath)
const formData = computed(() => ({
    ...props.item,
    ...form.data()
}))
provide('formData', formData)

const submit = () => {
    form.put(route('admin.users.update', props.item.id))
}
</script>

<template>
    <AdminLayout :title="title">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <Link :href="route('admin.users.index')" class="text-gray-600 hover:text-blue-600">
                            {{ $t('menu.administrators') }}
                        </Link>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="ml-1 text-gray-500">{{ item.name }}</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="max-w-2xl">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ title }}</h1>

            <AdminCard>
                <p v-if="readOnly" data-read-only class="mb-6 rounded-md border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800">
                    {{ $t('resource.form.read_only') }}
                </p>

                <form @submit.prevent="submit" class="space-y-6">
                    <fieldset :disabled="readOnly" class="space-y-6">
                    <template v-for="field in fields" :key="field.name">
                    <FormField
                        v-if="!plain(field)"
                        :field="field"
                        v-model="form[field.name]"
                        :errors="form.errors"
                    />
                    <div v-else>
                        <label :for="field.name" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ field.label }}
                            <span v-if="field.required && field.type !== 'password'" class="text-red-500">*</span>
                        </label>

                        <!-- Checkbox for boolean -->
                        <template v-if="field.type === 'checkbox'">
                            <div class="flex items-center">
                                <input
                                    :id="field.name"
                                    v-model="form[field.name]"
                                    type="checkbox"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <label :for="field.name" class="ml-2 text-sm text-gray-700">
                                    {{ field.label }}
                                </label>
                            </div>
                        </template>

                        <!-- Text/Email/Password input -->
                        <template v-else>
                            <input
                                :id="field.name"
                                v-model="form[field.name]"
                                :type="field.type === 'password' ? 'password' : field.type === 'email' ? 'email' : 'text'"
                                :placeholder="field.type === 'password' ? $t('account.users.password_keep_placeholder') : field.placeholder"
                                :required="field.required && field.type !== 'password'"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                :class="{ 'border-red-500': form.errors[field.name] }"
                            />
                        </template>

                        <p v-if="field.hint" class="mt-1 text-sm text-gray-500">
                            {{ field.hint }}
                        </p>
                        <p v-if="field.type === 'password' && field.name === 'password'" class="mt-1 text-sm text-gray-500">
                            {{ $t('account.users.password_keep_hint') }}
                        </p>
                        <p v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">
                            {{ form.errors[field.name] }}
                        </p>
                    </div>
                    </template>
                    </fieldset>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                        <AdminButton
                            :href="route('admin.users.index')"
                            variant="secondary"
                        >
                            {{ readOnly ? $t('common.back') : $t('common.cancel') }}
                        </AdminButton>
                        <AdminButton
                            v-if="!readOnly"
                            data-action="save"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('common.save') }}
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>
        </div>
    </AdminLayout>
</template>
