<script setup>
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

const props = defineProps({
    routeName: String,
    createLabel: String,
    fields: Array,
    fieldsByTab: Object,
    tabs: Array,
    useTabs: Boolean,
    title: String,
    adminLabel: String,
})

// Active tab
const activeTab = ref(props.tabs?.[0]?.name || 'main')

// Initialize form data
const initialData = {}
if (props.useTabs && props.fieldsByTab) {
    Object.values(props.fieldsByTab).flat().forEach(field => {
        initialData[field.name] = initialValue(field, undefined)
    })
} else {
    props.fields.forEach(field => {
        initialData[field.name] = initialValue(field, undefined)
    })
}

const form = useForm(initialData)

const allFields = () => (props.useTabs && props.fieldsByTab ? Object.values(props.fieldsByTab).flat() : props.fields) || []
const { locale: contentLocale, locales: contentLocales, showSwitcher } = useContentLocale(allFields)

// Provide form data for child components (SlugField needs the source value)
const formData = computed(() => ({ ...form.data() }))
provide('formData', formData)

// Use form.submit(method, url) rather than form.post()/form.put(): a field
// named e.g. "post" shadows the like-named method on the form object and the
// submit would fail with "form.post is not a function".
const submit = () => {
    form.submit('post', route(`${props.routeName}.store`))
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
                            <span class="ml-1 text-gray-500">{{ $t('resource.form.breadcrumb_create') }}</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="max-w-4xl">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ title }}</h1>

            <AdminCard>
                <form @submit.prevent="submit" data-admin-form="true" :data-resource="routeName" data-context="create">
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

                    <!-- Form buttons -->
                    <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t">
                        <AdminButton data-action="cancel" :href="route(`${props.routeName}.index`)" variant="secondary">
                            {{ $t('common.cancel') }}
                        </AdminButton>
                        <AdminButton data-action="save" type="submit" :loading="form.processing">
                            {{ $t('common.create') }}
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>
        </div>
    </AdminLayout>
</template>
