<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { useForm, Link } from '@inertiajs/vue3'
import { ref, computed, provide } from 'vue'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import AdminTabs from '../../../Components/Admin/Tabs/AdminTabs.vue'
import AdminTabPanel from '../../../Components/Admin/Tabs/AdminTabPanel.vue'
import FormField from '../../../Components/Admin/Fields/FormField.vue'
import ContentLocaleSwitcher from '../../../Components/Admin/Fields/ContentLocaleSwitcher.vue'
import { useContentLocale, initialValue, fieldHasError } from '../../../Support/contentLocale'
import { recordActionHref } from '../../../Support/recordActions'

const props = defineProps({
    routeName: String,
    createLabel: String,
    item: Object,
    itemTitle: String,
    fields: Array,
    fieldsByTab: Object,
    tabs: Array,
    useTabs: Boolean,
    title: String,
    adminLabel: String,
    can: { type: Object, default: () => ({}) },
    // Buttons on a record that open the create form filled from it.
    actions: { type: Array, default: () => [] },
})

// With "view" but not "update" the record opens read-only: no Save, and the
// inputs are switched off (the server refuses the save anyway).
const readOnly = computed(() => props.can.update === false)

// Active tab
const activeTab = ref(props.tabs?.[0]?.name || 'main')

// Initialize form data
const initialData = {}
if (props.useTabs && props.fieldsByTab) {
    Object.values(props.fieldsByTab).flat().forEach(field => {
        initialData[field.name] = initialValue(field, props.item[field.name])
    })
} else {
    props.fields.forEach(field => {
        initialData[field.name] = initialValue(field, props.item[field.name])
    })
}

const form = useForm(initialData)

const allFields = () => (props.useTabs && props.fieldsByTab ? Object.values(props.fieldsByTab).flat() : props.fields) || []
const { locale: contentLocale, locales: contentLocales, showSwitcher } = useContentLocale(allFields)

// Provide form data for child components (SlugField reads the name it
// generates the code from, and the section the public address hangs on)
const formData = computed(() => ({
    ...props.item,
    ...form.data()
}))
provide('formData', formData)
// The module key the AI buttons send (alveby/atrium-ai).
provide('aiModule', props.routeName?.replace(/^admin\./, '') || null)

// Use form.submit(method, url) rather than form.post()/form.put(): a field
// named e.g. "post" shadows the like-named method on the form object and the
// submit would fail with "form.post is not a function".
const submit = () => {
    form.submit('put', route(`${props.routeName}.update`, props.item.id))
}

// Check for errors in tab
const hasTabErrors = (tabName) => {
    if (!props.fieldsByTab?.[tabName]) return false
    return props.fieldsByTab[tabName].some(field => fieldHasError(form.errors, field))
}

// Get error count for tab
const getTabErrorCount = (tabName) => {
    if (!props.fieldsByTab?.[tabName]) return 0
    return props.fieldsByTab[tabName].filter(field => fieldHasError(form.errors, field)).length
}

// Tabs with error badges
const tabsWithBadges = computed(() => {
    if (!props.tabs) return []
    return props.tabs.map(tab => ({
        ...tab,
        badge: getTabErrorCount(tab.name) > 0 ? String(getTabErrorCount(tab.name)) : null
    }))
})
</script>

<template>
    <AdminLayout :title="title">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <Link :href="route(`${props.routeName}.index`)" class="text-gray-600 hover:text-blue-600">
                            {{ adminLabel }}
                        </Link>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="ml-1 text-gray-500">{{ itemTitle || '#' + item.id }}</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="max-w-4xl">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                <div v-if="actions.length" class="flex gap-2">
                    <AdminButton
                        v-for="action in actions"
                        :key="action.name"
                        :data-action="action.name"
                        variant="secondary"
                        :href="recordActionHref(routeName, action, item)"
                    >
                        {{ action.label }}
                    </AdminButton>
                </div>
            </div>

            <AdminCard>
                <p v-if="readOnly" data-read-only class="mb-6 rounded-md border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800">
                    {{ $t('resource.form.read_only') }}
                </p>

                <form @submit.prevent="submit" data-admin-form="true" :data-resource="routeName" data-context="edit" :data-item-id="item.id">
                    <fieldset :disabled="readOnly" class="contents">
                    <ContentLocaleSwitcher
                        v-if="showSwitcher"
                        v-model="contentLocale"
                        :locales="contentLocales"
                        :fields="allFields()"
                        :errors="form.errors"
                    />

                    <!-- With Tabs -->
                    <template v-if="useTabs && tabs && fieldsByTab">
                        <AdminTabs :tabs="tabsWithBadges" v-model="activeTab">
                            <template #default="{ activeTab: currentTab }">
                                <AdminTabPanel
                                    v-for="tab in tabs"
                                    :key="tab.name"
                                    :name="tab.name"
                                    :activeTab="currentTab"
                                >
                                    <div class="space-y-6">
                                        <FormField
                                            v-for="field in fieldsByTab[tab.name]"
                                            :key="field.name"
                                            :field="field"
                                            v-model="form[field.name]"
                                            :errors="form.errors"
                                        />

                                        <!-- Empty tab message -->
                                        <div
                                            v-if="!fieldsByTab[tab.name]?.length"
                                            class="text-center py-12 text-gray-500"
                                        >
                                            <p>{{ $t('resource.form.empty_tab') }}</p>
                                        </div>
                                    </div>
                                </AdminTabPanel>
                            </template>
                        </AdminTabs>
                    </template>

                    <!-- Without Tabs (backward compatibility) -->
                    <template v-else>
                        <div class="space-y-6">
                            <FormField
                                v-for="field in fields"
                                :key="field.name"
                                :field="field"
                                v-model="form[field.name]"
                                :errors="form.errors"
                            />
                        </div>
                    </template>

                    </fieldset>

                    <!-- Form buttons -->
                    <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t">
                        <AdminButton data-action="cancel" :href="route(`${props.routeName}.index`)" variant="secondary">
                            {{ readOnly ? $t('common.back') : $t('common.cancel') }}
                        </AdminButton>
                        <AdminButton v-if="!readOnly" data-action="save" type="submit" :loading="form.processing">
                            {{ $t('common.save') }}
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>
        </div>
    </AdminLayout>
</template>
