<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * A module's settings: the fields of its ModuleSettings class. For a module
 * with per-site settings, those of the site chosen in the header.
 */
import { computed, provide } from 'vue'
import { useForm, Link, Head } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import FormField from '../../../Components/Admin/Fields/FormField.vue'
import { initialValue } from '../../../Support/contentLocale'

const props = defineProps({
    module: Object,
    description: String,
    fields: Array,
    values: Object,
    site: { type: Object, default: null },
    title: String,
})

const initialData = {}
props.fields.forEach((field) => {
    initialData[field.name] = initialValue(field, props.values[field.name])
})

const form = useForm(initialData)

provide('formData', computed(() => form.data()))

// form.submit(): a setting named "post" or "put" would shadow form.put().
const submit = () => {
    form.submit('put', route('admin.modules.settings.update', props.module.key), { preserveScroll: true })
}
</script>

<template>
    <Head :title="title" />
    <div class="mb-6">
        <Link :href="route('admin.modules.index')" class="text-muted hover:text-accent">{{ $t('menu.modules') }}</Link>
    </div>

    <div class="max-w-4xl">
        <h1 data-page-title="admin.modules.settings" class="text-2xl font-bold text-fg">{{ title }}</h1>
        <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
        <p v-if="site" data-settings-site class="mt-1 text-sm text-muted">{{ $t('modules.per_site', { site: site.name }) }}</p>

        <AdminCard class="mt-6">
            <form data-settings-form :data-module="module.key" @submit.prevent="submit">
                <div class="space-y-6">
                    <FormField
                        v-for="field in fields"
                        :key="field.name"
                        :field="field"
                        v-model="form[field.name]"
                        :errors="form.errors"
                    />
                </div>

                <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t">
                    <AdminButton :href="route('admin.modules.index')" variant="secondary">{{ $t('common.cancel') }}</AdminButton>
                    <AdminButton data-action="save" type="submit" :loading="form.processing">{{ $t('common.save') }}</AdminButton>
                </div>
            </form>
        </AdminCard>
    </div>
</template>
